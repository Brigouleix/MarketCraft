<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Avis
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Création
    // ------------------------------------------------------------------

    public function create(array $data): ?array
    {
        // Un utilisateur ne peut poster qu'un seul avis par produit (contrainte UNIQUE en BDD)
        $stmt = $this->db->prepare(
            'INSERT INTO avis (produit_id, utilisateur_id, note, titre, commentaire)
             VALUES (:produit_id, :utilisateur_id, :note, :titre, :commentaire)'
        );

        $stmt->execute([
            ':produit_id'     => $data['produit_id'],
            ':utilisateur_id' => $data['utilisateur_id'],
            ':note'           => (int) $data['note'],
            ':titre'          => $data['titre']       ?? null,
            ':commentaire'    => $data['commentaire'] ?? null,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.nom AS auteur_nom, u.prenom AS auteur_prenom, u.avatar_url AS auteur_avatar
             FROM avis a
             JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByProduct(int $produitId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare(
            'SELECT a.*, u.nom AS auteur_nom, u.prenom AS auteur_prenom, u.avatar_url AS auteur_avatar
             FROM avis a
             JOIN utilisateurs u ON u.id = a.utilisateur_id
             WHERE a.produit_id = :pid
             ORDER BY a.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':pid', $produitId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByProduct(int $produitId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM avis WHERE produit_id = :pid');
        $stmt->execute([':pid' => $produitId]);
        return (int) $stmt->fetchColumn();
    }

    public function findByUser(int $utilisateurId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare(
            'SELECT a.*, p.nom AS produit_nom, p.slug AS produit_slug
             FROM avis a
             JOIN produits p ON p.id = a.produit_id
             WHERE a.utilisateur_id = :uid
             ORDER BY a.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid', $utilisateurId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Note moyenne d'un produit
    // ------------------------------------------------------------------

    public function getAvgNote(int $produitId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
               ROUND(AVG(note), 2) AS moyenne,
               COUNT(*) AS total,
               SUM(CASE WHEN note = 5 THEN 1 ELSE 0 END) AS n5,
               SUM(CASE WHEN note = 4 THEN 1 ELSE 0 END) AS n4,
               SUM(CASE WHEN note = 3 THEN 1 ELSE 0 END) AS n3,
               SUM(CASE WHEN note = 2 THEN 1 ELSE 0 END) AS n2,
               SUM(CASE WHEN note = 1 THEN 1 ELSE 0 END) AS n1
             FROM avis
             WHERE produit_id = :pid'
        );
        $stmt->execute([':pid' => $produitId]);

        $row = $stmt->fetch();

        return [
            'moyenne'      => $row['moyenne'] ? (float) $row['moyenne'] : null,
            'total'        => (int) $row['total'],
            'repartition'  => [
                5 => (int) $row['n5'],
                4 => (int) $row['n4'],
                3 => (int) $row['n3'],
                2 => (int) $row['n2'],
                1 => (int) $row['n1'],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Vérification d'unicité
    // ------------------------------------------------------------------

    public function userAlreadyReviewed(int $produitId, int $utilisateurId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM avis WHERE produit_id = :pid AND utilisateur_id = :uid LIMIT 1'
        );
        $stmt->execute([':pid' => $produitId, ':uid' => $utilisateurId]);
        return (bool) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Suppression
    // ------------------------------------------------------------------

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM avis WHERE id = :id');
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }
}
