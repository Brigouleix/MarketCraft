<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modèle de journalisation des activités utilisateurs.
 * Utilisé pour la sécurité back-office (CDC — exigence logging).
 */
class ActivityLogModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Enregistre une entrée de log.
     *
     * @param array{
     *   utilisateur_id: int|null,
     *   action: string,
     *   entite: string|null,
     *   entite_id: int|null,
     *   ip_address: string|null,
     *   user_agent: string|null,
     *   donnees: string|null
     * } $data
     */
    public function log(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logs_activite
               (utilisateur_id, action, entite, entite_id, ip_address, user_agent, donnees)
             VALUES
               (:utilisateur_id, :action, :entite, :entite_id, :ip_address, :user_agent, :donnees)'
        );

        $stmt->execute([
            ':utilisateur_id' => $data['utilisateur_id'] ?? null,
            ':action'         => $data['action'],
            ':entite'         => $data['entite'] ?? null,
            ':entite_id'      => $data['entite_id'] ?? null,
            ':ip_address'     => isset($data['ip_address']) ? substr($data['ip_address'], 0, 45) : null,
            ':user_agent'     => isset($data['user_agent']) ? substr($data['user_agent'], 0, 500) : null,
            ':donnees'        => $data['donnees'] ?? null,
        ]);
    }

    /**
     * Récupérer les derniers logs (pour le dashboard admin).
     */
    public function getLast(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, u.nom, u.prenom, u.email
             FROM logs_activite l
             LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
             ORDER BY l.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Logs d'un utilisateur spécifique.
     */
    public function getByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logs_activite
             WHERE utilisateur_id = :user_id
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',   $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
