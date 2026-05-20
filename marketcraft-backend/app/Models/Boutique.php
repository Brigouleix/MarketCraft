<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Boutique
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom, u.email AS vendeur_email
             FROM boutiques b
             JOIN utilisateurs u ON u.id = b.vendeur_id
             WHERE b.id = :id AND b.est_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.*, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom
             FROM boutiques b
             JOIN utilisateurs u ON u.id = b.vendeur_id
             WHERE b.slug = :slug AND b.est_active = 1
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByVendeur(int $vendeurId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM boutiques WHERE vendeur_id = :vid ORDER BY created_at DESC'
        );
        $stmt->execute([':vid' => $vendeurId]);
        return $stmt->fetchAll();
    }

    public function findAll(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare(
            'SELECT b.*, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom,
                    COUNT(p.id) AS nb_produits
             FROM boutiques b
             JOIN utilisateurs u ON u.id = b.vendeur_id
             LEFT JOIN produits p ON p.boutique_id = b.id AND p.est_actif = 1
             WHERE b.est_active = 1
             GROUP BY b.id
             ORDER BY b.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM boutiques WHERE est_active = 1')->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Création
    // ------------------------------------------------------------------

    public function create(array $data): ?array
    {
        $slug = $this->generateSlug($data['nom']);

        $stmt = $this->db->prepare(
            'INSERT INTO boutiques (vendeur_id, nom, slug, description, logo_url, banniere_url)
             VALUES (:vendeur_id, :nom, :slug, :description, :logo_url, :banniere_url)'
        );

        $stmt->execute([
            ':vendeur_id'   => $data['vendeur_id'],
            ':nom'          => trim($data['nom']),
            ':slug'         => $slug,
            ':description'  => $data['description'] ?? null,
            ':logo_url'     => $data['logo_url'] ?? null,
            ':banniere_url' => $data['banniere_url'] ?? null,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    // ------------------------------------------------------------------
    // Mise à jour
    // ------------------------------------------------------------------

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['nom', 'description', 'logo_url', 'banniere_url'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        // Regénérer le slug si le nom change
        if (array_key_exists('nom', $data)) {
            $fields[] = 'slug = :slug';
            $params[':slug'] = $this->generateSlug($data['nom'], $id);
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $sql = 'UPDATE boutiques SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);

        return $this->findById($id);
    }

    // ------------------------------------------------------------------
    // Suppression (soft delete)
    // ------------------------------------------------------------------

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE boutiques SET est_active = 0 WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Boutique avec ses produits
    // ------------------------------------------------------------------

    public function getWithProduits(int $id, int $limit = 12): ?array
    {
        $boutique = $this->findById($id);
        if ($boutique === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT p.*, c.nom AS categorie_nom
             FROM produits p
             LEFT JOIN categories c ON c.id = p.categorie_id
             WHERE p.boutique_id = :bid AND p.est_actif = 1
             ORDER BY p.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':bid', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $boutique['produits'] = $stmt->fetchAll();

        return $boutique;
    }

    // ------------------------------------------------------------------
    // Slug unique
    // ------------------------------------------------------------------

    private function generateSlug(string $nom, ?int $excludeId = null): string
    {
        $slug = mb_strtolower(trim($nom));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $base = $slug;
        $i    = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT id FROM boutiques WHERE slug = :slug AND id != :id LIMIT 1');
            $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM boutiques WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
        }

        return (bool) $stmt->fetchColumn();
    }
}
