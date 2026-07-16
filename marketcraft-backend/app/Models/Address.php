<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Address
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Enregistre une adresse de livraison et retourne son identifiant.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO adresses_livraison (utilisateur_id, nom_complet, ligne1, ligne2, ville, code_postal, pays)
             VALUES (:uid, :nom_complet, :ligne1, :ligne2, :ville, :code_postal, :pays)'
        );

        $stmt->execute([
            ':uid'         => $data['utilisateur_id'],
            ':nom_complet' => trim($data['nom_complet']),
            ':ligne1'      => trim($data['ligne1']),
            ':ligne2'      => $data['ligne2'] ?? null,
            ':ville'       => trim($data['ville']),
            ':code_postal' => trim($data['code_postal']),
            ':pays'        => $data['pays'] ?? 'France',
        ]);

        return (int) $this->db->lastInsertId();
    }
}
