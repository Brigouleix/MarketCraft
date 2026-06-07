<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Dual-channel logger.
 *
 * File channel  → storage/logs/app-YYYY-MM-DD.log   (every call)
 * DB channel    → journal_activites table             (activity() calls only)
 *
 * If the DB table cannot be created or written to, all events gracefully
 * fall back to file-only logging. No request is ever broken by a log failure.
 *
 * ─── One-time MySQL repair (run in phpMyAdmin / MySQL CLI if journal is empty) ───
 *   See database/repair_journal.sql
 */
class Logger
{
    /** Prevents repeated CREATE TABLE attempts in the same request. */
    private static bool $tableReady   = false;

    /** Set to true when the DB is unavailable; all writes go to file only. */
    private static bool $dbUnavailable = false;

    // ------------------------------------------------------------------
    // File logging
    // ------------------------------------------------------------------

    private static function logDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs';
    }

    private static function writeFile(string $level, string $message, array $context = []): void
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ctx       = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);

        file_put_contents(
            $dir . '/app-' . date('Y-m-d') . '.log',
            "[{$timestamp}] [{$level}] [{$ip}] {$message}{$ctx}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    // ------------------------------------------------------------------
    // Standard file-only helpers
    // ------------------------------------------------------------------

    public static function info(string $message, array $context = []): void
    {
        self::writeFile('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::writeFile('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::writeFile('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        if ($env === 'development') {
            self::writeFile('DEBUG', $message, $context);
        }
    }

    /**
     * Log an HTTP request/response cycle (called from public/index.php).
     */
    public static function request(string $method, string $uri, int $statusCode, float $durationMs): void
    {
        $context = ['status' => $statusCode, 'duration_ms' => round($durationMs, 2)];
        $authSub = $_REQUEST['_auth']['sub'] ?? null;
        if ($authSub !== null) {
            $context['user_id'] = $authSub;
        }
        self::writeFile('REQUEST', "{$method} {$uri}", $context);
    }

    // ------------------------------------------------------------------
    // Activity logging (file + DB)
    // ------------------------------------------------------------------

    /**
     * Writes a structured activity event to the daily log file and, when
     * the DB table is available, also to journal_activites.
     */
    public static function activity(
        string $type,
        string $message,
        array  $context = [],
        ?int   $userId  = null
    ): void {
        // Always write to file
        self::writeFile(strtoupper($type), $message, array_merge(
            $context,
            $userId !== null ? ['user_id' => $userId] : []
        ));

        if (self::$dbUnavailable) {
            return;
        }

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            self::ensureTable($db);

            if (self::$dbUnavailable) {
                return; // ensureTable() marked DB as unavailable
            }

            $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
            $ctx = empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare("
                INSERT INTO journal_activites (type, message, user_id, ip_address, context)
                VALUES (:type, :message, :user_id, :ip, :context)
            ");
            $stmt->execute([
                ':type'    => $type,
                ':message' => mb_substr($message, 0, 500),
                ':user_id' => $userId,
                ':ip'      => $ip,
                ':context' => $ctx,
            ]);
        } catch (\Throwable $e) {
            self::$dbUnavailable = true;
            self::writeFile('ERROR', 'Logger DB write failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Table bootstrap (public so DashboardController can call it)
    // ------------------------------------------------------------------

    public static function ensureTable(\PDO $db): void
    {
        if (self::$tableReady || self::$dbUnavailable) {
            return;
        }

        // ── Step 1: probe ──────────────────────────────────────────────
        try {
            $db->query('SELECT 1 FROM journal_activites LIMIT 0');
            self::$tableReady = true;
            return;
        } catch (\PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);

            // 1146 = table simply doesn't exist → fall through to CREATE
            // 1932 = InnoDB engine inconsistency (ghost .frm, no tablespace)
            //        → DROP the ghost first, then CREATE
            if ($code === 1932) {
                try {
                    $db->exec('DROP TABLE IF EXISTS journal_activites');
                } catch (\Throwable) {}
            } elseif ($code !== 1146) {
                // Unexpected probe error — mark unavailable, log to file only
                self::$dbUnavailable = true;
                self::writeFile('ERROR', "journal_activites probe failed (MySQL #{$code}): " . $e->getMessage());
                return;
            }
        }

        // ── Step 2: create ─────────────────────────────────────────────
        try {
            $db->exec("
                CREATE TABLE journal_activites (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    type       VARCHAR(50)  NOT NULL,
                    message    VARCHAR(500) NOT NULL,
                    user_id    INT UNSIGNED NULL,
                    ip_address VARCHAR(45)  NULL,
                    context    JSON         NULL,
                    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type       (type),
                    INDEX idx_user_id    (user_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            self::$tableReady = true;
        } catch (\Throwable $e) {
            // 1813 = orphaned .ibd file left after a dirty DROP (common on Windows/MariaDB).
            // This requires a one-time manual cleanup — see database/repair_journal.sql.
            self::$dbUnavailable = true;
            self::writeFile(
                'ERROR',
                'Cannot create journal_activites — orphaned tablespace file detected. '
                . 'Run database/repair_journal.sql in phpMyAdmin to fix. Error: '
                . $e->getMessage()
            );
        }
    }

    /**
     * Returns true when the DB table is available for reading/writing.
     * Used by DashboardController to report setup status to the frontend.
     */
    public static function isDbAvailable(): bool
    {
        return !self::$dbUnavailable;
    }
}
