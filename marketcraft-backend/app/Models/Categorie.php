<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Categorie
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Retourne toutes les catégories, triées par ordre d'affichage.
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
}
