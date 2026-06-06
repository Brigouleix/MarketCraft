<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    private static function getLogDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs';
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $logDir = self::getLogDir();

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile   = $logDir . '/app-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ctx       = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);

        $line = "[{$timestamp}] [{$level}] [{$ip}] {$message}{$ctx}" . PHP_EOL;

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        if ($env === 'development') {
            self::write('DEBUG', $message, $context);
        }
    }

    /**
     * Log an HTTP request/response cycle.
     * Call after the response has been sent (or just before exit).
     */
    public static function request(string $method, string $uri, int $statusCode, float $durationMs): void
    {
        $context = ['status' => $statusCode, 'duration_ms' => round($durationMs, 2)];

        $authSub = $_REQUEST['_auth']['sub'] ?? null;
        if ($authSub !== null) {
            $context['user_id'] = $authSub;
        }

        self::write('REQUEST', "{$method} {$uri}", $context);
    }
}
