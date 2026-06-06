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
    // GET /dashboard/stats  (JWT + vendeur/admin)
    // ------------------------------------------------------------------

    public function stats(array $params = []): void
    {
        if (!Auth::hasRole('vendeur', 'admin')) {
            $this->error('Forbidden. Vendor role required.', 403);
            return;
        }

        $auth   = Auth::getCurrentUser();
        $userId = (int) $auth['sub'];

        try {
            // Chiffre d'affaires des boutiques du vendeur
            $stCA = $this->db->prepare("
                SELECT COALESCE(SUM(pay.montant), 0)
                FROM paiements pay
                JOIN commandes c ON pay.commande_id = c.id
                JOIN lignes_commande lc ON lc.commande_id = c.id
                JOIN produits pr ON pr.id = lc.produit_id
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid
                  AND c.statut != 'annulee'
                  AND pay.statut = 'reussi'
            ");
            $stCA->execute([':uid' => $userId]);
            $ca = (float) $stCA->fetchColumn();

            // Nombre de commandes distinctes
            $stOrders = $this->db->prepare("
                SELECT COUNT(DISTINCT c.id)
                FROM commandes c
                JOIN lignes_commande lc ON lc.commande_id = c.id
                JOIN produits pr ON pr.id = lc.produit_id
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid AND c.statut != 'annulee'
            ");
            $stOrders->execute([':uid' => $userId]);
            $nbCommandes = (int) $stOrders->fetchColumn();

            // Nombre de produits actifs
            $stProds = $this->db->prepare("
                SELECT COUNT(pr.id)
                FROM produits pr
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid AND pr.est_actif = 1
            ");
            $stProds->execute([':uid' => $userId]);
            $nbProduits = (int) $stProds->fetchColumn();

            // Note moyenne sur tous les produits du vendeur
            $stNote = $this->db->prepare("
                SELECT COALESCE(AVG(a.note), 0)
                FROM avis a
                JOIN produits pr ON pr.id = a.produit_id
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid
            ");
            $stNote->execute([':uid' => $userId]);
            $noteMoyenne = round((float) $stNote->fetchColumn(), 1);

            // 5 dernières commandes contenant des produits du vendeur
            $stRecent = $this->db->prepare("
                SELECT DISTINCT c.id, c.total, c.statut, c.created_at,
                       u.nom AS client_nom, u.prenom AS client_prenom,
                       (SELECT COUNT(*) FROM lignes_commande WHERE commande_id = c.id) AS nb_articles
                FROM commandes c
                JOIN utilisateurs u ON u.id = c.utilisateur_id
                JOIN lignes_commande lc ON lc.commande_id = c.id
                JOIN produits pr ON pr.id = lc.produit_id
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid
                ORDER BY c.created_at DESC
                LIMIT 5
            ");
            $stRecent->execute([':uid' => $userId]);
            $recentOrders = $stRecent->fetchAll();

            Logger::info('Dashboard stats fetched', ['user_id' => $userId]);

            $this->success([
                'ca'            => $ca,
                'nb_commandes'  => $nbCommandes,
                'nb_produits'   => $nbProduits,
                'note_moyenne'  => $noteMoyenne,
                'recent_orders' => $recentOrders,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Dashboard stats error: ' . $e->getMessage(), ['user_id' => $userId]);
            $this->error('Failed to fetch dashboard stats.', 500);
        }
    }

    // ------------------------------------------------------------------
    // GET /vendor/orders  (JWT + vendeur/admin)
    // Commandes liées aux boutiques du vendeur courant
    // ------------------------------------------------------------------

    public function vendorOrders(array $params = []): void
    {
        if (!Auth::hasRole('vendeur', 'admin')) {
            $this->error('Forbidden. Vendor role required.', 403);
            return;
        }

        $auth   = Auth::getCurrentUser();
        $userId = (int) $auth['sub'];
        $page   = max(1, (int) ($_GET['page']  ?? 1));
        $limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT c.id, c.total, c.statut, c.created_at,
                       u.nom AS client_nom, u.prenom AS client_prenom,
                       (SELECT COUNT(*) FROM lignes_commande WHERE commande_id = c.id) AS nb_articles
                FROM commandes c
                JOIN utilisateurs u ON u.id = c.utilisateur_id
                JOIN lignes_commande lc ON lc.commande_id = c.id
                JOIN produits pr ON pr.id = lc.produit_id
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll();

            $countStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT c.id)
                FROM commandes c
                JOIN lignes_commande lc ON lc.commande_id = c.id
                JOIN produits pr ON pr.id = lc.produit_id
                JOIN boutiques b ON b.id = pr.boutique_id
                WHERE b.vendeur_id = :uid
            ");
            $countStmt->execute([':uid' => $userId]);
            $total = (int) $countStmt->fetchColumn();

            $this->paginated($orders, $total, $page, $limit);
        } catch (\Throwable $e) {
            Logger::error('Vendor orders error: ' . $e->getMessage(), ['user_id' => $userId]);
            $this->error('Failed to fetch vendor orders.', 500);
        }
    }
}
