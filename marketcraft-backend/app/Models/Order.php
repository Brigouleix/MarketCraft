<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class Order
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Création d'une commande complète (commande + lignes)
    // ------------------------------------------------------------------

    /**
     * Crée une commande avec ses lignes en une transaction atomique.
     *
     * @param array $data {
     *   utilisateur_id: int,
     *   adresse_livraison_id: int|null,
     *   frais_livraison: float,
     *   note: string|null,
     *   lignes: [{ produit_id, quantite, prix_unitaire, nom_produit }]
     * }
     */
    public function create(array $data): ?array
    {
        $this->db->beginTransaction();

        try {
            // Calculer le total
            $montantTotal = array_reduce($data['lignes'], function (float $carry, array $ligne) {
                return $carry + ($ligne['prix_unitaire'] * $ligne['quantite']);
            }, 0.0);

            $montantTotal += (float) ($data['frais_livraison'] ?? 0);

            // Insérer la commande
            $stmt = $this->db->prepare(
                'INSERT INTO commandes (utilisateur_id, adresse_livraison_id, montant_total, frais_livraison, note)
                 VALUES (:uid, :addr_id, :total, :frais, :note)'
            );
            $stmt->execute([
                ':uid'     => $data['utilisateur_id'],
                ':addr_id' => $data['adresse_livraison_id'] ?? null,
                ':total'   => $montantTotal,
                ':frais'   => $data['frais_livraison'] ?? 0,
                ':note'    => $data['note'] ?? null,
            ]);

            $commandeId = (int) $this->db->lastInsertId();

            // Insérer les lignes
            $stmtLigne = $this->db->prepare(
                'INSERT INTO lignes_commande (commande_id, produit_id, quantite, prix_unitaire, nom_produit)
                 VALUES (:cmd_id, :prod_id, :qty, :prix, :nom)'
            );

            foreach ($data['lignes'] as $ligne) {
                $stmtLigne->execute([
                    ':cmd_id'  => $commandeId,
                    ':prod_id' => $ligne['produit_id'],
                    ':qty'     => (int) $ligne['quantite'],
                    ':prix'    => (float) $ligne['prix_unitaire'],
                    ':nom'     => $ligne['nom_produit'],
                ]);
            }

            $this->db->commit();

            return $this->findById($commandeId);
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    u.nom AS utilisateur_nom, u.prenom AS utilisateur_prenom, u.email AS utilisateur_email,
                    a.ligne1 AS addr_ligne1, a.ligne2 AS addr_ligne2, a.ville AS addr_ville,
                    a.code_postal AS addr_code_postal, a.pays AS addr_pays
             FROM commandes c
             JOIN utilisateurs u ON u.id = c.utilisateur_id
             LEFT JOIN adresses_livraison a ON a.id = c.adresse_livraison_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $commande = $stmt->fetch();

        if (!$commande) {
            return null;
        }

        $commande['lignes'] = $this->getLignes($id);

        return $commande;
    }

    public function findByUser(int $utilisateurId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare(
            'SELECT c.*, COUNT(lc.id) AS nb_articles
             FROM commandes c
             LEFT JOIN lignes_commande lc ON lc.commande_id = c.id
             WHERE c.utilisateur_id = :uid
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid', $utilisateurId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByUser(int $utilisateurId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM commandes WHERE utilisateur_id = :uid');
        $stmt->execute([':uid' => $utilisateurId]);
        return (int) $stmt->fetchColumn();
    }

    public function getWithLignes(int $id): ?array
    {
        return $this->findById($id);
    }

    private function getLignes(int $commandeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT lc.*, p.slug AS produit_slug, p.images AS produit_images
             FROM lignes_commande lc
             LEFT JOIN produits p ON p.id = lc.produit_id
             WHERE lc.commande_id = :cmd_id'
        );
        $stmt->execute([':cmd_id' => $commandeId]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Mise à jour du statut
    // ------------------------------------------------------------------

    public function updateStatus(int $id, string $statut, ?string $numeroSuivi = null): ?array
    {
        $allowedStatuts = ['en_attente', 'confirmee', 'en_preparation', 'expediee', 'livree', 'annulee'];

        if (!in_array($statut, $allowedStatuts, true)) {
            return null;
        }

        $fields = ['statut = :statut'];
        $params = [':id' => $id, ':statut' => $statut];

        if ($numeroSuivi !== null) {
            $fields[] = 'numero_suivi = :numero_suivi';
            $params[':numero_suivi'] = $numeroSuivi;
        }

        $sql = 'UPDATE commandes SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);

        return $this->findById($id);
    }

    // ------------------------------------------------------------------
    // Annulation
    // ------------------------------------------------------------------

    public function cancel(int $id): bool
    {
        // Ne peut annuler que si la commande n'est pas déjà expédiée ou livrée
        $stmt = $this->db->prepare(
            "UPDATE commandes
             SET statut = 'annulee'
             WHERE id = :id
               AND statut NOT IN ('expediee', 'livree', 'annulee')"
        );
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }
}
