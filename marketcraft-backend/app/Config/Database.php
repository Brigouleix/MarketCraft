<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $host    = $_ENV['DB_HOST']    ?? getenv('DB_HOST')    ?: 'localhost';
        $name    = $_ENV['DB_NAME']    ?? getenv('DB_NAME')    ?: 'marketcraft';
        $user    = $_ENV['DB_USER']    ?? getenv('DB_USER')    ?: 'root';
        $pass    = $_ENV['DB_PASS']    ?? getenv('DB_PASS')    ?: '';
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed: ' . $e->getMessage(),
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
}
