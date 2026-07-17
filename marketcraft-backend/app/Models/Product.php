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
     * @param int             $page
     * @param int             $limit
     * @param string|null     $search    Recherche sur nom + description
     * @param int|string|null $categorie Id numérique ou slug (préfixe accepté : 'ceramique' matche 'ceramique-poterie')
     * @param int|null        $boutiqueId
     * @param float|null      $prixMin
     * @param float|null      $prixMax
     * @param string          $sort      Colonne de tri
     * @param string          $order     ASC ou DESC
     * @param float|null      $noteMin   Note moyenne minimale (1 à 5)
     */
    public function findAll(
        int             $page       = 1,
        int             $limit      = 20,
        ?string         $search     = null,
        int|string|null $categorie  = null,
        ?int            $boutiqueId = null,
        ?float          $prixMin    = null,
        ?float          $prixMax    = null,
        string          $sort       = 'created_at',
        string          $order      = 'DESC',
        ?float          $noteMin    = null
    ): array {
        $offset = ($page - 1) * $limit;

        [$whereClause, $having, $params] = $this->buildFilters(
            $search, $categorie, $boutiqueId, $prixMin, $prixMax, $noteMin
        );

        // Whitelist des colonnes de tri (colonnes brutes ou agrégats calculés)
        $sortMap = [
            'created_at' => 'p.created_at',
            'prix'       => 'p.prix',
            'nom'        => 'p.nom',
            'stock'      => 'p.stock',
            'note'       => 'note_moyenne',
            'nb_avis'    => 'nb_avis',
        ];
        $sortCol  = $sortMap[$sort] ?? 'p.created_at';
        $orderDir = in_array(strtoupper($order), ['ASC', 'DESC'], true) ? strtoupper($order) : 'DESC';

        $sql = "SELECT p.*, b.nom AS boutique_nom, c.nom AS categorie_nom,
                       COALESCE(AVG(a.note), 0) AS note_moyenne,
                       COUNT(DISTINCT a.id) AS nb_avis,
                       GROUP_CONCAT(DISTINCT CONCAT(c2.id, ':', c2.nom) SEPARATOR '||') AS categories_concat
                FROM produits p
                LEFT JOIN boutiques b ON b.id = p.boutique_id
                LEFT JOIN categories c ON c.id = p.categorie_id
                LEFT JOIN produit_categorie pc ON pc.produit_id = p.id
                LEFT JOIN categories c2 ON c2.id = pc.categorie_id
                LEFT JOIN avis a ON a.produit_id = p.id
                WHERE {$whereClause}
                GROUP BY p.id
                {$having}
                ORDER BY {$sortCol} {$orderDir}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /**
     * Normalise une ligne produit pour les clients de l'API :
     * décode les colonnes JSON et expose les alias attendus par le front
     * (objet boutique imbriqué, nom de catégorie).
     */
    private function hydrate(array $row): array
    {
        foreach (['images', 'tags'] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true) ?? [];
            }
        }

        if (!empty($row['boutique_nom'])) {
            $row['boutique'] = [
                'id'  => (int) ($row['boutique_id'] ?? 0),
                'nom' => $row['boutique_nom'],
            ];
        }

        if (!isset($row['categorie']) && !empty($row['categorie_nom'])) {
            $row['categorie'] = $row['categorie_nom'];
        }

        // Liste complète des catégories (table de liaison produit_categorie)
        $row['categories'] = [];
        if (!empty($row['categories_concat'])) {
            foreach (explode('||', $row['categories_concat']) as $pair) {
                [$catId, $catNom] = array_pad(explode(':', $pair, 2), 2, '');
                if ($catId !== '') {
                    $row['categories'][] = ['id' => (int) $catId, 'nom' => $catNom];
                }
            }
        }
        unset($row['categories_concat']);

        return $row;
    }

    /**
     * Compte les produits correspondant aux filtres (pour la pagination).
     */
    public function countAll(
        ?string         $search     = null,
        int|string|null $categorie  = null,
        ?int            $boutiqueId = null,
        ?float          $prixMin    = null,
        ?float          $prixMax    = null,
        ?float          $noteMin    = null
    ): int {
        [$whereClause, $having, $params] = $this->buildFilters(
            $search, $categorie, $boutiqueId, $prixMin, $prixMax, $noteMin
        );

        $sql = "SELECT COUNT(*) FROM (
                    SELECT p.id
                    FROM produits p
                    LEFT JOIN categories c ON c.id = p.categorie_id
                    LEFT JOIN avis a ON a.produit_id = p.id
                    WHERE {$whereClause}
                    GROUP BY p.id
                    {$having}
                ) AS filtered";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Construit la clause WHERE, la clause HAVING et les paramètres liés,
     * partagés entre findAll() et countAll().
     *
     * @return array{0: string, 1: string, 2: array}
     */
    private function buildFilters(
        ?string         $search,
        int|string|null $categorie,
        ?int            $boutiqueId,
        ?float          $prixMin,
        ?float          $prixMax,
        ?float          $noteMin
    ): array {
        $where  = ['p.est_actif = 1'];
        $params = [];

        if (!empty($search)) {
            $where[]            = '(p.nom LIKE :search OR p.description LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        if ($categorie !== null && $categorie !== '') {
            // Le produit matche s'il est rattaché à la catégorie via la table
            // de liaison (couvre aussi la catégorie principale, migrée dedans)
            if (is_numeric($categorie)) {
                $where[] = 'EXISTS (SELECT 1 FROM produit_categorie pcf
                                     WHERE pcf.produit_id = p.id AND pcf.categorie_id = :cat_id)';
                $params[':cat_id'] = (int) $categorie;
            } else {
                $where[] = 'EXISTS (SELECT 1 FROM produit_categorie pcf
                                     JOIN categories cf ON cf.id = pcf.categorie_id
                                     WHERE pcf.produit_id = p.id AND cf.slug LIKE :cat_slug)';
                $params[':cat_slug'] = $categorie . '%';
            }
        }

        if ($boutiqueId !== null) {
            $where[]            = 'p.boutique_id = :bout_id';
            $params[':bout_id'] = $boutiqueId;
        }

        if ($prixMin !== null) {
            $where[]             = 'p.prix >= :prix_min';
            $params[':prix_min'] = $prixMin;
        }

        if ($prixMax !== null) {
            $where[]             = 'p.prix <= :prix_max';
            $params[':prix_max'] = $prixMax;
        }

        $having = '';
        if ($noteMin !== null && $noteMin > 0) {
            $having              = 'HAVING COALESCE(AVG(a.note), 0) >= :note_min';
            $params[':note_min'] = $noteMin;
        }

        return [implode(' AND ', $where), $having, $params];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, b.nom AS boutique_nom, b.vendeur_id,
                    c.nom AS categorie_nom,
                    COALESCE(AVG(a.note), 0) AS note_moyenne,
                    COUNT(DISTINCT a.id) AS nb_avis,
                    GROUP_CONCAT(DISTINCT CONCAT(c2.id, \':\', c2.nom) SEPARATOR \'||\') AS categories_concat
             FROM produits p
             LEFT JOIN boutiques b ON b.id = p.boutique_id
             LEFT JOIN categories c ON c.id = p.categorie_id
             LEFT JOIN produit_categorie pc ON pc.produit_id = p.id
             LEFT JOIN categories c2 ON c2.id = pc.categorie_id
             LEFT JOIN avis a ON a.produit_id = p.id
             WHERE p.id = :id AND p.est_actif = 1
             GROUP BY p.id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
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

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    // ------------------------------------------------------------------
    // Création
    // ------------------------------------------------------------------

    public function create(array $data): ?array
    {
        $slug = $this->generateSlug($data['nom']);

        // Catégories multiples : la première devient la catégorie principale
        $categorieIds = $this->normalizeCategorieIds($data);

        $stmt = $this->db->prepare(
            'INSERT INTO produits
               (boutique_id, categorie_id, nom, slug, description, prix, stock, images, tags, est_fait_main)
             VALUES
               (:boutique_id, :categorie_id, :nom, :slug, :description, :prix, :stock, :images, :tags, :est_fait_main)'
        );

        $stmt->execute([
            ':boutique_id'   => $data['boutique_id'],
            ':categorie_id'  => $categorieIds[0] ?? null,
            ':nom'           => trim($data['nom']),
            ':slug'          => $slug,
            ':description'   => $data['description'] ?? null,
            ':prix'          => (float) $data['prix'],
            ':stock'         => (int) ($data['stock'] ?? 0),
            ':images'        => isset($data['images']) ? json_encode($data['images']) : null,
            ':tags'          => isset($data['tags'])   ? json_encode($data['tags'])   : null,
            ':est_fait_main' => (int) ($data['est_fait_main'] ?? 1),
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->syncCategories($id, $categorieIds);

        return $this->findById($id);
    }

    /**
     * Extrait la liste des ids de catégories depuis les données reçues :
     * `categorie_ids` (tableau) prioritaire, sinon `categorie_id` (scalaire).
     *
     * @return int[] Ids uniques, sans zéros ni doublons
     */
    private function normalizeCategorieIds(array $data): array
    {
        $ids = [];
        if (isset($data['categorie_ids']) && is_array($data['categorie_ids'])) {
            $ids = $data['categorie_ids'];
        } elseif (!empty($data['categorie_id'])) {
            $ids = [$data['categorie_id']];
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn(int $id) => $id > 0);

        return array_values(array_unique($ids));
    }

    /**
     * Remplace les liaisons produit ↔ catégories par la liste fournie.
     *
     * @param int[] $categorieIds
     */
    private function syncCategories(int $productId, array $categorieIds): void
    {
        $this->db->prepare('DELETE FROM produit_categorie WHERE produit_id = :id')
                 ->execute([':id' => $productId]);

        if (empty($categorieIds)) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO produit_categorie (produit_id, categorie_id) VALUES (:pid, :cid)'
        );
        foreach ($categorieIds as $catId) {
            $stmt->execute([':pid' => $productId, ':cid' => $catId]);
        }
    }

    // ------------------------------------------------------------------
    // Mise à jour
    // ------------------------------------------------------------------

    public function update(int $id, array $data): ?array
    {
        // Catégories multiples : synchroniser la liaison et aligner la
        // catégorie principale sur la première de la liste
        if (isset($data['categorie_ids']) && is_array($data['categorie_ids'])) {
            $categorieIds = $this->normalizeCategorieIds($data);
            $this->syncCategories($id, $categorieIds);
            $data['categorie_id'] = $categorieIds[0] ?? null;
        } elseif (array_key_exists('categorie_id', $data)) {
            $this->syncCategories($id, $this->normalizeCategorieIds($data));
        }

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
