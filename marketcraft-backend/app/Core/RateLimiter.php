<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File-based rate limiter.
 *
 * Tracks failed attempts per key (typically "login:{ip}") in JSON files stored
 * under storage/rate_limits/. After MAX_ATTEMPTS failures inside the sliding
 * window, the key is blocked for BLOCK_DURATION seconds.
 *
 * No database required; the directory is created automatically.
 */
class RateLimiter
{
    private const MAX_ATTEMPTS    = 5;
    private const BLOCK_DURATION  = 15 * 60; // 15 minutes
    private const WINDOW_DURATION = 15 * 60; // sliding window

    // ------------------------------------------------------------------
    // Storage helpers
    // ------------------------------------------------------------------

    private static function dir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/rate_limits';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . md5($key) . '.json';
    }

    private static function load(string $key): array
    {
        $path = self::path($key);
        if (!file_exists($path)) {
            return ['attempts' => [], 'blocked_until' => null];
        }
        return json_decode((string) file_get_contents($path), true)
            ?? ['attempts' => [], 'blocked_until' => null];
    }

    private static function save(string $key, array $data): void
    {
        file_put_contents(self::path($key), json_encode($data), LOCK_EX);
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Returns true if the key is currently blocked.
     */
    public static function isBlocked(string $key): bool
    {
        $data = self::load($key);
        return isset($data['blocked_until'])
            && $data['blocked_until'] !== null
            && time() < $data['blocked_until'];
    }

    /**
     * Seconds remaining in the current block (0 if not blocked).
     */
    public static function remainingSeconds(string $key): int
    {
        $data = self::load($key);
        if (isset($data['blocked_until']) && $data['blocked_until'] !== null && time() < $data['blocked_until']) {
            return $data['blocked_until'] - time();
        }
        return 0;
    }

    /**
     * Records one failed attempt and returns the total count inside the window.
     * Sets a block if MAX_ATTEMPTS is reached.
     */
    public static function hit(string $key): int
    {
        $data = self::load($key);
        $now  = time();

        // Expire a previous block that has passed
        if (isset($data['blocked_until']) && $data['blocked_until'] !== null && $now >= $data['blocked_until']) {
            $data = ['attempts' => [], 'blocked_until' => null];
        }

        // Drop attempts outside the sliding window
        $data['attempts'] = array_values(array_filter(
            $data['attempts'] ?? [],
            static fn(int $t) => ($now - $t) < self::WINDOW_DURATION
        ));
        $data['attempts'][] = $now;

        $count = count($data['attempts']);

        if ($count >= self::MAX_ATTEMPTS) {
            $data['blocked_until'] = $now + self::BLOCK_DURATION;
        }

        self::save($key, $data);
        return $count;
    }

    /**
     * Clears all tracked attempts for a key (call on successful login).
     */
    public static function reset(string $key): void
    {
        $path = self::path($key);
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
