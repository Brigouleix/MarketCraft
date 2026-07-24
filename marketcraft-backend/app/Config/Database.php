<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Connexion PDO a la base MySQL/MariaDB (singleton).
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private string $host;
    private string $dbname;
    private string $user;
    private string $pass;
    private string $charset;

    private function __construct()
    {
        $this->host    = $_ENV['DB_HOST']    ?? getenv('DB_HOST')    ?: 'localhost';
        $this->dbname  = $_ENV['DB_NAME']    ?? getenv('DB_NAME')    ?: 'marketcraft_3eme_dev';
        $this->user    = $_ENV['DB_USER']    ?? getenv('DB_USER')    ?: 'root';
        $this->pass    = $_ENV['DB_PASS']    ?? getenv('DB_PASS')    ?: '';
        $this->charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $this->connect();
    }

    private function connect(): void
    {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->connection = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            // Le detail de l'erreur est journalise, jamais renvoye au client :
            // il exposerait l'hote, le nom de la base et l'utilisateur.
            error_log('Database connection failed: ' . $e->getMessage());

            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed.',
            ]);
            exit;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    // Empeche le clonage et la deserialisation du singleton
    private function __clone() {}

    public function __wakeup(): never
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }
}
