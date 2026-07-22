<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Config\Database;
use PDO;

/**
 * AdminController — Espace d'administration de la plateforme.
 *
 * Toutes les routes exigent le rôle « admin » (contrôle en tête de chaque
 * action, en plus du middleware d'authentification déclaré sur la route).
 *
 * Routes :
 *   GET    /admin/stats              – Statistiques globales de la plateforme
 *   GET    /admin/users              – Liste des utilisateurs
 *   PUT    /admin/users/:id/toggle   – Activer / désactiver un compte
 *   GET    /admin/boutiques          – Liste des boutiques
 *   PUT    /admin/boutiques/:id/toggle – Activer / suspendre une boutique
 *   GET    /admin/avis               – Liste des avis (modération)
 *   DELETE /admin/avis/:id           – Supprimer un avis
 */
class AdminController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Garde d'accès : réservé au rôle admin.
     * Renvoie true si l'accès est autorisé, sinon émet la réponse d'erreur.
     */
    private function requireAdmin(): bool
    {
        if (Auth::getCurrentUser() === null) {
            $this->error('Unauthorized.', 401);
            return false;
        }
        if (!Auth::hasRole('admin')) {
            $this->error('Forbidden. Administrator access required.', 403);
            return false;
        }
        return true;
    }

    // ------------------------------------------------------------------
    // GET /admin/stats
    // ------------------------------------------------------------------

    public function stats(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $scalar = fn(string $sql): int => (int) $this->db->query($sql)->fetchColumn();

        $nbUtilisateurs = $scalar('SELECT COUNT(*) FROM utilisateurs');
        $nbVendeurs     = $scalar("SELECT COUNT(*) FROM utilisateurs WHERE role = 'vendeur'");
        $nbClients      = $scalar("SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'");
        $nbBoutiques    = $scalar('SELECT COUNT(*) FROM boutiques');
        $nbProduits     = $scalar('SELECT COUNT(*) FROM produits WHERE est_actif = 1');
        $nbCommandes    = $scalar("SELECT COUNT(*) FROM commandes WHERE statut NOT IN ('annulee')");
        $nbAvis         = $scalar('SELECT COUNT(*) FROM avis');

        $caGlobal = (float) $this->db
            ->query("SELECT COALESCE(SUM(montant_total), 0) FROM commandes WHERE statut NOT IN ('annulee')")
            ->fetchColumn();

        $this->success([
            'nb_utilisateurs' => $nbUtilisateurs,
            'nb_vendeurs'     => $nbVendeurs,
            'nb_clients'      => $nbClients,
            'nb_boutiques'    => $nbBoutiques,
            'nb_produits'     => $nbProduits,
            'nb_commandes'    => $nbCommandes,
            'nb_avis'         => $nbAvis,
            'ca_global'       => round($caGlobal, 2),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /admin/users
    // ------------------------------------------------------------------

    public function users(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $stmt = $this->db->query(
            'SELECT id, nom, prenom, email, role, est_actif, created_at
             FROM utilisateurs
             ORDER BY created_at DESC'
        );

        $this->success($stmt->fetchAll());
    }

    // ------------------------------------------------------------------
    // PUT /admin/users/:id/toggle  – bascule est_actif
    // ------------------------------------------------------------------

    public function toggleUser(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id   = (int) ($params['id'] ?? 0);
        $auth = Auth::getCurrentUser();

        // Un admin ne peut pas se désactiver lui-même (sécurité anti-verrouillage)
        if ($id === (int) $auth['sub']) {
            $this->error('You cannot deactivate your own account.', 422);
            return;
        }

        $user = $this->db->prepare('SELECT id, est_actif FROM utilisateurs WHERE id = :id');
        $user->execute([':id' => $id]);
        $row = $user->fetch();

        if ($row === false) {
            $this->error('User not found.', 404);
            return;
        }

        $nouveau = $row['est_actif'] ? 0 : 1;
        $upd = $this->db->prepare('UPDATE utilisateurs SET est_actif = :actif WHERE id = :id');
        $upd->execute([':actif' => $nouveau, ':id' => $id]);

        $this->success(['id' => $id, 'est_actif' => $nouveau], 'User status updated.');
    }

    // ------------------------------------------------------------------
    // GET /admin/boutiques
    // ------------------------------------------------------------------

    public function boutiques(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $stmt = $this->db->query(
            'SELECT b.id, b.nom, b.slug, b.est_active, b.note_moyenne, b.created_at,
                    u.nom AS vendeur_nom, u.prenom AS vendeur_prenom,
                    (SELECT COUNT(*) FROM produits p WHERE p.boutique_id = b.id) AS nb_produits
             FROM boutiques b
             JOIN utilisateurs u ON u.id = b.vendeur_id
             ORDER BY b.created_at DESC'
        );

        $this->success($stmt->fetchAll());
    }

    // ------------------------------------------------------------------
    // PUT /admin/boutiques/:id/toggle  – bascule est_active
    // ------------------------------------------------------------------

    public function toggleBoutique(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = (int) ($params['id'] ?? 0);

        $bout = $this->db->prepare('SELECT id, est_active FROM boutiques WHERE id = :id');
        $bout->execute([':id' => $id]);
        $row = $bout->fetch();

        if ($row === false) {
            $this->error('Boutique not found.', 404);
            return;
        }

        $nouveau = $row['est_active'] ? 0 : 1;
        $upd = $this->db->prepare('UPDATE boutiques SET est_active = :actif WHERE id = :id');
        $upd->execute([':actif' => $nouveau, ':id' => $id]);

        $this->success(['id' => $id, 'est_active' => $nouveau], 'Boutique status updated.');
    }

    // ------------------------------------------------------------------
    // GET /admin/avis  – tous les avis, pour modération
    // ------------------------------------------------------------------

    public function avis(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $stmt = $this->db->query(
            'SELECT a.id, a.note, a.titre, a.commentaire, a.est_verifie, a.created_at,
                    p.id AS produit_id, p.nom AS produit_nom,
                    u.prenom AS auteur_prenom, u.nom AS auteur_nom
             FROM avis a
             JOIN produits p ON p.id = a.produit_id
             JOIN utilisateurs u ON u.id = a.utilisateur_id
             ORDER BY a.created_at DESC'
        );

        $this->success($stmt->fetchAll());
    }

    // ------------------------------------------------------------------
    // DELETE /admin/avis/:id
    // ------------------------------------------------------------------

    public function deleteAvis(array $params = []): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db->prepare('DELETE FROM avis WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            $this->error('Avis not found.', 404);
            return;
        }

        // Les triggers de la base recalculent automatiquement la note du produit.
        $this->success(null, 'Avis deleted.');
    }
}
