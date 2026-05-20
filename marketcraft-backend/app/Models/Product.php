<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Recherche avec pagination et filtres
    // ------------------------------------------------------------------

    /**
     * Retourne les produits actifs avec pagination et filtres optionnels.
     *
     * @param int         $page
     * @param int         $limit
     * @param string|null $search     Recherche FULLTEXT sur nom + description
     * @param int|null    $categorieId
     * @param int|null    $boutiqueId
     * @param float|null  $prixMin
     * @param float|null  $prixMax
     * @param string      $sort       Colonne de tri
     * @param string      $order      ASC ou DESC
     */
    public function findAll(
        int     $page        = 1,
        int     $limit       = 20,
        ?string $search      = null,
        ?int    $categorieId = null,
        ?int    $boutiqueId  = null,
        ?float  $prixMin     = null,
        ?float  $prixMax     = null,
        string  $sort        = 'created_at',
        string  $order       = 'DESC'
    ): array {
        $offset = ($page - 1) * $limit;
        $where  = ['p.est_actif = 1'];
        $params = [];

        if (!empty($search)) {
            $where[]          = '(p.nom LIKE :search OR p.description LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        if ($categorieId !== null) {
            $where[]              = 'p.categorie_id = :cat_id';
            $params[':cat_id']    = $categorieId;
        }

        if ($boutiqueId !== null) {
            $where[]              = 'p.boutique_id = :bout_id';
            $params[':bout_id']   = $boutiqueId;
        }

        if ($prixMin !== null) {
            $where[]              = 'p.prix >= :prix_min';
            $params[':prix_min']  = $prixMin;
        }

        if ($prixMax !== null) {
            $where[]              = 'p.prix <= :prix_max';
            $params[':prix_max']  = $prixMax;
        }

        // Whitelist des colonnes de tri
        $allowedSort  = ['created_at', 'prix', 'nom', 'stock'];
        $allowedOrder = ['ASC', 'DESC'];
        $sortCol  = in_array($sort, $allowedSort, true)   ? $sort  : 'created_at';
        $orderDir = in_array(strtoupper($order), $allowedOrder, true) ? strtoupper($order) : 'DESC';

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT p.*, b.nom AS boutique_nom, c.nom AS categorie_nom,
                       COALESCE(AVG(a.note), 0) AS note_moyenne,
                       COUNT(DISTINCT a.id) AS nb_avis
                FROM produits p
                LEFT JOIN boutiques b ON b.id = p.boutique_id
                LEFT JOIN categories c ON c.id = p.categorie_id
                LEFT JOIN avis a ON a.produit_id = p.id
                WHERE {$whereClause}
                GROUP BY p.id
                ORDER BY p.{$sortCol} {$orderDir}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Compte les produits correspondant aux filtres (pour la pagination).
     */
    public function countAll(
        ?string $search      = null,
        ?int    $categorieId = null,
        ?int    $boutiqueId  = null,
        ?float  $prixMin     = null,
        ?float  $prixMax     = null
    ): int {
        $where  = ['est_actif = 1'];
        $params = [];

        if (!empty($search)) {
            $where[]           = '(nom LIKE :search OR description LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        if ($categorieId !== null) {
            $where[]           = 'categorie_id = :cat_id';
            $params[':cat_id'] = $categorieId;
        }
        if ($boutiqueId !== null) {
            $where[]            = 'boutique_id = :bout_id';
            $params[':bout_id'] = $boutiqueId;
        }
        if ($prixMin !== null) {
            $where[]             = 'prix >= :prix_min';
            $params[':prix_min'] = $prixMin;
        }
        if ($prixMax !== null) {
            $where[]             = 'prix <= :prix_max';
            $params[':prix_max'] = $prixMax;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM produits WHERE {$whereClause}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, b.nom AS boutique_nom, b.vendeur_id,
                    c.nom AS categorie_nom,
                    COALESCE(AVG(a.note), 0) AS note_moyenne,
                    COUNT(DISTINCT a.id) AS nb_avis
             FROM produits p
             LEFT JOIN boutiques b ON b.id = p.boutique_id
             LEFT JOIN categories c ON c.id = p.categorie_id
             LEFT JOIN avis a ON a.produit_id = p.id
             WHERE p.id = :id AND p.est_actif = 1
             GROUP BY p.id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getByBoutique(int $boutiqueId, int $page = 1, int $limit = 20): array
    {
        return $this->findAll($page, $limit, null, null, $boutiqueId);
    }

    public function search(string $query, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.nom, p.prix, p.images, b.nom AS boutique_nom
             FROM produits p
             LEFT JOIN boutiques b ON b.id = p.boutique_id
             WHERE p.est_actif = 1
               AND (p.nom LIKE :q OR p.description LIKE :q2)
             ORDER BY p.nom ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':q',  '%' . $query . '%');
        $stmt->bindValue(':q2', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Création
    // ------------------------------------------------------------------

    public function create(array $data): ?array
    {
        $slug = $this->generateSlug($data['nom']);

        $stmt = $this->db->prepare(
            'INSERT INTO produits
               (boutique_id, categorie_id, nom, slug, description, prix, stock, images, tags, est_fait_main)
             VALUES
               (:boutique_id, :categorie_id, :nom, :slug, :description, :prix, :stock, :images, :tags, :est_fait_main)'
        );

        $stmt->execute([
            ':boutique_id'   => $data['boutique_id'],
            ':categorie_id'  => $data['categorie_id'] ?? null,
            ':nom'           => trim($data['nom']),
            ':slug'          => $slug,
            ':description'   => $data['description'] ?? null,
            ':prix'          => (float) $data['prix'],
            ':stock'         => (int) ($data['stock'] ?? 0),
            ':images'        => isset($data['images']) ? json_encode($data['images']) : null,
            ':tags'          => isset($data['tags'])   ? json_encode($data['tags'])   : null,
            ':est_fait_main' => (int) ($data['est_fait_main'] ?? 1),
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

        $allowed = ['nom', 'description', 'prix', 'stock', 'categorie_id', 'est_actif', 'est_fait_main'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (array_key_exists('images', $data)) {
            $fields[] = 'images = :images';
            $params[':images'] = json_encode($data['images']);
        }

        if (array_key_exists('tags', $data)) {
            $fields[] = 'tags = :tags';
            $params[':tags'] = json_encode($data['tags']);
        }

        if (array_key_exists('nom', $data)) {
            $slug = $this->generateSlug($data['nom'], $id);
            $fields[] = 'slug = :slug';
            $params[':slug'] = $slug;
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $sql = 'UPDATE produits SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);

        return $this->findById($id);
    }

    // ------------------------------------------------------------------
    // Suppression (soft delete)
    // ------------------------------------------------------------------

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE produits SET est_actif = 0 WHERE id = :id');
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Décrémenter le stock
    // ------------------------------------------------------------------

    public function decrementStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE produits SET stock = stock - :qty WHERE id = :id AND stock >= :qty2'
        );
        return $stmt->execute([':qty' => $quantity, ':id' => $id, ':qty2' => $quantity])
            && $stmt->rowCount() > 0;
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
            $stmt = $this->db->prepare('SELECT id FROM produits WHERE slug = :slug AND id != :id LIMIT 1');
            $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM produits WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
        }

        return (bool) $stmt->fetchColumn();
    }
}
