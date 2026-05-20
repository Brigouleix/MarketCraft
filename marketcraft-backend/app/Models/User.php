<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM utilisateurs WHERE email = :email AND est_actif = 1 LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM utilisateurs WHERE id = :id AND est_actif = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->toArray($row) : null;
    }

    public function findAll(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare(
            'SELECT * FROM utilisateurs ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'toArray'], $stmt->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM utilisateurs WHERE est_actif = 1')->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Création
    // ------------------------------------------------------------------

    public function create(array $data): ?array
    {
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare(
            'INSERT INTO utilisateurs (nom, prenom, email, password_hash, role, telephone, avatar_url)
             VALUES (:nom, :prenom, :email, :password_hash, :role, :telephone, :avatar_url)'
        );

        $stmt->execute([
            ':nom'           => trim($data['nom']),
            ':prenom'        => trim($data['prenom']),
            ':email'         => strtolower(trim($data['email'])),
            ':password_hash' => $passwordHash,
            ':role'          => $data['role'] ?? 'client',
            ':telephone'     => $data['telephone'] ?? null,
            ':avatar_url'    => $data['avatar_url'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    // ------------------------------------------------------------------
    // Mise à jour
    // ------------------------------------------------------------------

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['nom', 'prenom', 'telephone', 'avatar_url'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        // Mise à jour du mot de passe si fourni
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $sql = 'UPDATE utilisateurs SET ' . implode(', ', $fields) . ' WHERE id = :id AND est_actif = 1';
        $this->db->prepare($sql)->execute($params);

        return $this->findById($id);
    }

    // ------------------------------------------------------------------
    // Suppression (soft delete)
    // ------------------------------------------------------------------

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE utilisateurs SET est_actif = 0 WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Vérification du mot de passe
    // ------------------------------------------------------------------

    public function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    // ------------------------------------------------------------------
    // Filtrer les données sensibles
    // ------------------------------------------------------------------

    /**
     * Retourne le tableau utilisateur sans le hash du mot de passe.
     */
    public function toArray(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    // ------------------------------------------------------------------
    // Trouver par email avec données sensibles (brute-force check)
    // ------------------------------------------------------------------
    public function findByEmailRaw(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM utilisateurs WHERE email = :email AND est_actif = 1 LIMIT 1'
        );
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Brute-force : incrémenter les tentatives
    // ------------------------------------------------------------------
    public function incrementLoginAttempts(int $id): void
    {
        $this->db->prepare(
            'UPDATE utilisateurs SET login_attempts = login_attempts + 1 WHERE id = :id'
        )->execute([':id' => $id]);
    }

    // ------------------------------------------------------------------
    // Brute-force : verrouiller le compte pour N minutes
    // ------------------------------------------------------------------
    public function lockAccount(int $id, int $minutes = 15): void
    {
        $this->db->prepare(
            'UPDATE utilisateurs
             SET login_attempts = 5,
                 locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE)
             WHERE id = :id'
        )->execute([':minutes' => $minutes, ':id' => $id]);
    }

    // ------------------------------------------------------------------
    // Brute-force : réinitialiser après succès
    // ------------------------------------------------------------------
    public function resetLoginAttempts(int $id): void
    {
        $this->db->prepare(
            'UPDATE utilisateurs
             SET login_attempts = 0,
                 locked_until   = NULL,
                 last_login_at  = NOW()
             WHERE id = :id'
        )->execute([':id' => $id]);
    }

    // ------------------------------------------------------------------
    // Vérification d'unicité email
    // ------------------------------------------------------------------

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM utilisateurs WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => strtolower(trim($email))]);
        return (bool) $stmt->fetchColumn();
    }
}
