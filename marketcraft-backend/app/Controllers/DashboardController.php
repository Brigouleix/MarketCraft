<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Config\Database;
use PDO;

class DashboardController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // GET /dashboard/stats  – Stats vendeur (JWT + vendeur/admin)
    // ------------------------------------------------------------------

    public function vendeurStats(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        if ($auth === null) { $this->error('Unauthorized.', 401); return; }

        $userId = (int) $auth['sub'];

        // Chiffre d'affaires total via les lignes de commande des produits de la boutique
        $ca = $this->db->prepare(
            'SELECT COALESCE(SUM(lc.prix_unitaire * lc.quantite), 0) AS ca
             FROM lignes_commande lc
             JOIN produits p ON p.id = lc.produit_id
             JOIN boutiques b ON b.id = p.boutique_id
             JOIN commandes c ON c.id = lc.commande_id
             WHERE b.vendeur_id = :uid
               AND c.statut NOT IN (\'annulee\')'
        );
        $ca->execute([':uid' => $userId]);
        $caVal = (float) $ca->fetchColumn();

        // Nombre de commandes uniques
        $nbCommandes = $this->db->prepare(
            'SELECT COUNT(DISTINCT c.id) AS nb
             FROM commandes c
             JOIN lignes_commande lc ON lc.commande_id = c.id
             JOIN produits p ON p.id = lc.produit_id
             JOIN boutiques b ON b.id = p.boutique_id
             WHERE b.vendeur_id = :uid
               AND c.statut NOT IN (\'annulee\')'
        );
        $nbCommandes->execute([':uid' => $userId]);
        $nbCmd = (int) $nbCommandes->fetchColumn();

        // Nombre de produits actifs
        $nbProduits = $this->db->prepare(
            'SELECT COUNT(*) FROM produits p
             JOIN boutiques b ON b.id = p.boutique_id
             WHERE b.vendeur_id = :uid AND p.est_actif = 1'
        );
        $nbProduits->execute([':uid' => $userId]);
        $nbProd = (int) $nbProduits->fetchColumn();

        // Note moyenne
        $note = $this->db->prepare(
            'SELECT COALESCE(AVG(a.note), 0) AS note
             FROM avis a
             JOIN produits p ON p.id = a.produit_id
             JOIN boutiques b ON b.id = p.boutique_id
             WHERE b.vendeur_id = :uid'
        );
        $note->execute([':uid' => $userId]);
        $noteMoy = (float) $note->fetchColumn();

        // Commandes récentes (10 dernières)
        $recent = $this->db->prepare(
            'SELECT DISTINCT c.id, c.statut, c.montant_total, c.created_at,
                    u.nom AS client_nom, u.prenom AS client_prenom
             FROM commandes c
             JOIN lignes_commande lc ON lc.commande_id = c.id
             JOIN produits p ON p.id = lc.produit_id
             JOIN boutiques b ON b.id = p.boutique_id
             JOIN utilisateurs u ON u.id = c.utilisateur_id
             WHERE b.vendeur_id = :uid
             ORDER BY c.created_at DESC
             LIMIT 10'
        );
        $recent->execute([':uid' => $userId]);
        $recentOrders = $recent->fetchAll();

        // CA par mois (6 derniers mois)
        $caParMois = $this->db->prepare(
            'SELECT DATE_FORMAT(c.created_at, \'%Y-%m\') AS mois,
                    COALESCE(SUM(lc.prix_unitaire * lc.quantite), 0) AS ca
             FROM lignes_commande lc
             JOIN produits p ON p.id = lc.produit_id
             JOIN boutiques b ON b.id = p.boutique_id
             JOIN commandes c ON c.id = lc.commande_id
             WHERE b.vendeur_id = :uid
               AND c.statut NOT IN (\'annulee\')
               AND c.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY mois
             ORDER BY mois ASC'
        );
        $caParMois->execute([':uid' => $userId]);
        $evolution = $caParMois->fetchAll();

        // Top produits (5 mieux vendus)
        $topProd = $this->db->prepare(
            'SELECT p.id, p.nom, p.prix,
                    SUM(lc.quantite) AS nb_vendus,
                    SUM(lc.prix_unitaire * lc.quantite) AS revenu
             FROM lignes_commande lc
             JOIN produits p ON p.id = lc.produit_id
             JOIN boutiques b ON b.id = p.boutique_id
             JOIN commandes c ON c.id = lc.commande_id
             WHERE b.vendeur_id = :uid
               AND c.statut NOT IN (\'annulee\')
             GROUP BY p.id
             ORDER BY nb_vendus DESC
             LIMIT 5'
        );
        $topProd->execute([':uid' => $userId]);
        $topProduits = $topProd->fetchAll();

        $this->success([
            'ca'              => $caVal,
            'nb_commandes'    => $nbCmd,
            'nb_produits'     => $nbProd,
            'note_moyenne'    => round($noteMoy, 2),
            'commandes_recentes' => $recentOrders,
            'evolution_ca'    => $evolution,
            'top_produits'    => $topProduits,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /dashboard/acheteur  – Stats acheteur (JWT)
    // ------------------------------------------------------------------

    public function acheteurStats(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        if ($auth === null) { $this->error('Unauthorized.', 401); return; }

        $userId = (int) $auth['sub'];

        // Total dépensé
        $total = $this->db->prepare(
            'SELECT COALESCE(SUM(montant_total), 0) AS total
             FROM commandes
             WHERE utilisateur_id = :uid AND statut NOT IN (\'annulee\')'
        );
        $total->execute([':uid' => $userId]);
        $totalDepense = (float) $total->fetchColumn();

        // Nombre de commandes
        $nb = $this->db->prepare(
            'SELECT COUNT(*) FROM commandes
             WHERE utilisateur_id = :uid AND statut NOT IN (\'annulee\')'
        );
        $nb->execute([':uid' => $userId]);
        $nbCommandes = (int) $nb->fetchColumn();

        // Panier moyen
        $panierMoyen = $nbCommandes > 0 ? $totalDepense / $nbCommandes : 0;

        // Nombre d'avis déposés
        $nbAvis = $this->db->prepare(
            'SELECT COUNT(*) FROM avis WHERE utilisateur_id = :uid'
        );
        $nbAvis->execute([':uid' => $userId]);
        $nbAvisVal = (int) $nbAvis->fetchColumn();

        // Historique des commandes (avec lignes)
        $historique = $this->db->prepare(
            'SELECT c.id, c.statut, c.montant_total, c.created_at,
                    COUNT(lc.id) AS nb_articles
             FROM commandes c
             LEFT JOIN lignes_commande lc ON lc.commande_id = c.id
             WHERE c.utilisateur_id = :uid
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT 20'
        );
        $historique->execute([':uid' => $userId]);
        $commandes = $historique->fetchAll();

        // Dépenses par mois (6 derniers mois)
        $depMois = $this->db->prepare(
            'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS mois,
                    COALESCE(SUM(montant_total), 0) AS total
             FROM commandes
             WHERE utilisateur_id = :uid
               AND statut NOT IN (\'annulee\')
               AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY mois
             ORDER BY mois ASC'
        );
        $depMois->execute([':uid' => $userId]);
        $depenses = $depMois->fetchAll();

        // Catégories préférées (top 5)
        $cats = $this->db->prepare(
            'SELECT cat.nom AS categorie,
                    SUM(lc.quantite) AS nb_achats,
                    SUM(lc.prix_unitaire * lc.quantite) AS total_depense
             FROM lignes_commande lc
             JOIN commandes c ON c.id = lc.commande_id
             JOIN produits p ON p.id = lc.produit_id
             LEFT JOIN categories cat ON cat.id = p.categorie_id
             WHERE c.utilisateur_id = :uid
               AND c.statut NOT IN (\'annulee\')
             GROUP BY cat.id
             ORDER BY nb_achats DESC
             LIMIT 5'
        );
        $cats->execute([':uid' => $userId]);
        $categories = $cats->fetchAll();

        $this->success([
            'total_depense'   => $totalDepense,
            'nb_commandes'    => $nbCommandes,
            'panier_moyen'    => round($panierMoyen, 2),
            'nb_avis'         => $nbAvisVal,
            'commandes'       => $commandes,
            'depenses_mois'   => $depenses,
            'categories_pref' => $categories,
        ]);
    }
}
