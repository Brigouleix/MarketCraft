<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Payment
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
        $stmt = $this->db->prepare(
            'INSERT INTO paiements (commande_id, methode, statut, montant, transaction_id, payload)
             VALUES (:commande_id, :methode, :statut, :montant, :transaction_id, :payload)'
        );

        $stmt->execute([
            ':commande_id'    => $data['commande_id'],
            ':methode'        => $data['methode'] ?? 'carte',
            ':statut'         => $data['statut']  ?? 'en_attente',
            ':montant'        => (float) $data['montant'],
            ':transaction_id' => $data['transaction_id'] ?? null,
            ':payload'        => isset($data['payload']) ? json_encode($data['payload']) : null,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM paiements WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->decode($row) : null;
    }

    public function findByCommande(int $commandeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM paiements WHERE commande_id = :cid ORDER BY created_at DESC'
        );
        $stmt->execute([':cid' => $commandeId]);

        return array_map([$this, 'decode'], $stmt->fetchAll());
    }

    // ------------------------------------------------------------------
    // Mise à jour du statut
    // ------------------------------------------------------------------

    public function updateStatus(int $id, string $statut, ?string $transactionId = null): ?array
    {
        $allowedStatuts = ['en_attente', 'valide', 'refuse', 'rembourse'];

        if (!in_array($statut, $allowedStatuts, true)) {
            return null;
        }

        $fields = ['statut = :statut'];
        $params = [':id' => $id, ':statut' => $statut];

        if ($transactionId !== null) {
            $fields[] = 'transaction_id = :txn_id';
            $params[':txn_id'] = $transactionId;
        }

        $sql = 'UPDATE paiements SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);

        return $this->findById($id);
    }

    // ------------------------------------------------------------------
    // Dernier paiement validé pour une commande
    // ------------------------------------------------------------------

    public function getLatestValidForCommande(int $commandeId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM paiements
             WHERE commande_id = :cid AND statut = 'valide'
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute([':cid' => $commandeId]);
        $row = $stmt->fetch();

        return $row ? $this->decode($row) : null;
    }

    // ------------------------------------------------------------------
    // Décodage du champ JSON payload
    // ------------------------------------------------------------------

    private function decode(array $row): array
    {
        if (isset($row['payload']) && is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true);
        }

        return $row;
    }
}
