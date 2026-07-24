<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Categorie — acces a la table `categories`.
 *
 * Les categories sont hierarchisees sur deux niveaux via `parent_id` :
 * deux racines (« Objet » et « Materiau ») regroupent chacune leurs
 * sous-categories. L'administration permet de choisir le rattachement.
 */
class Categorie
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Retourne toutes les categories, triees par ordre d'affichage.
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, parent_id, nom, slug, description, image_url
             FROM categories
             ORDER BY ordre ASC, nom ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Idem findAll(), enrichi du nombre de produits rattaches a chaque
     * categorie (categorie principale + liaisons N-N, sans doublon).
     * Reserve a l'administration.
     */
    public function findAllWithCounts(): array
    {
        $stmt = $this->db->query(
            'SELECT c.id, c.parent_id, c.nom, c.slug, c.description,
                    c.image_url, c.ordre, c.created_at,
                    pa.nom AS parent_nom,
                    (
                        SELECT COUNT(DISTINCT p.id)
                        FROM produits p
                        LEFT JOIN produit_categorie pc ON pc.produit_id = p.id
                        WHERE p.categorie_id = c.id OR pc.categorie_id = c.id
                    ) AS nb_produits
             FROM categories c
             LEFT JOIN categories pa ON pa.id = c.parent_id
             ORDER BY COALESCE(pa.ordre, c.ordre) ASC, c.parent_id IS NOT NULL, c.ordre ASC, c.nom ASC'
        );

        return array_map(
            static function (array $row): array {
                $row['nb_produits'] = (int) $row['nb_produits'];
                return $row;
            },
            $stmt->fetchAll()
        );
    }

    /**
     * Categories racines (parent_id IS NULL) : « Objet », « Materiau »…
     * Sert a alimenter le selecteur de rattachement cote administration.
     */
    public function findRoots(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nom, slug
             FROM categories
             WHERE parent_id IS NULL
             ORDER BY ordre ASC, nom ASC'
        );

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, parent_id, nom, slug, description, image_url, ordre, created_at
             FROM categories
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Nombre de produits qui seraient impactes par la suppression :
     * - `principale` : produits dont c'est la categorie principale
     *   (mis a NULL par la contrainte ON DELETE SET NULL) ;
     * - `liaisons`   : lignes de produit_categorie supprimees en cascade.
     */
    public function countProduits(int $id): array
    {
        $principale = $this->db->prepare(
            'SELECT COUNT(*) FROM produits WHERE categorie_id = :id'
        );
        $principale->execute([':id' => $id]);

        $liaisons = $this->db->prepare(
            'SELECT COUNT(*) FROM produit_categorie WHERE categorie_id = :id'
        );
        $liaisons->execute([':id' => $id]);

        return [
            'principale' => (int) $principale->fetchColumn(),
            'liaisons'   => (int) $liaisons->fetchColumn(),
        ];
    }

    /**
     * Cree une categorie et retourne son identifiant.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (parent_id, nom, slug, description, image_url, ordre)
             VALUES (:parent_id, :nom, :slug, :description, :image_url, :ordre)'
        );

        $stmt->execute([
            ':parent_id'   => isset($data['parent_id']) && $data['parent_id'] !== ''
                ? (int) $data['parent_id'] : null,
            ':nom'         => $data['nom'],
            ':slug'        => $this->generateSlug($data['nom']),
            ':description' => $data['description'] ?? null,
            ':image_url'   => $data['image_url'] ?? null,
            ':ordre'       => (int) ($data['ordre'] ?? 0),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met a jour une categorie. Seuls les champs fournis sont modifies ;
     * le slug est regenere uniquement si le nom change.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('nom', $data)) {
            $fields[] = 'nom = :nom';
            $params[':nom'] = $data['nom'];

            $fields[] = 'slug = :slug';
            $params[':slug'] = $this->generateSlug($data['nom'], $id);
        }

        foreach (['description', 'image_url'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (array_key_exists('ordre', $data)) {
            $fields[] = 'ordre = :ordre';
            $params[':ordre'] = (int) $data['ordre'];
        }

        if (array_key_exists('parent_id', $data)) {
            $fields[] = 'parent_id = :parent_id';
            $params[':parent_id'] = ($data['parent_id'] === null || $data['parent_id'] === '')
                ? null : (int) $data['parent_id'];
        }

        if ($fields === []) {
            return false;
        }

        $sql  = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Verifie qu'un nom n'est pas deja porte par une autre categorie.
     */
    public function nomExists(string $nom, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id FROM categories WHERE nom = :nom AND id != :id LIMIT 1'
            );
            $stmt->execute([':nom' => $nom, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM categories WHERE nom = :nom LIMIT 1');
            $stmt->execute([':nom' => $nom]);
        }

        return (bool) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Slug
    // ------------------------------------------------------------------

    private function generateSlug(string $nom, ?int $excludeId = null): string
    {
        $slug = mb_strtolower(trim($nom));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'categorie';
        }

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
            $stmt = $this->db->prepare(
                'SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1'
            );
            $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
        }

        return (bool) $stmt->fetchColumn();
    }
}
