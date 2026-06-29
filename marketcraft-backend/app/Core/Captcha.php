<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Stateless HMAC-signed math captcha.
 *
 * Flow:
 *   1. GET /auth/captcha  → Captcha::generate()  → { question, token }
 *   2. Client shows the question and collects the user's answer.
 *   3. POST /auth/login   → includes captcha_token + captcha_answer.
 *   4. Server calls Captcha::verify($token, $answer) before processing credentials.
 *
 * The token encodes the correct answer and an expiry timestamp, signed with
 * JWT_SECRET via HMAC-SHA256. No server-side state is required.
 */
class Captcha
{
    private const TTL = 300; // 5 minutes

    private static function secret(): string
    {
        return $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: 'captcha_fallback_secret';
    }

    // ------------------------------------------------------------------
    // Generate
    // ------------------------------------------------------------------

    /**
     * Returns a question string and a signed token encoding the answer.
     *
     * @return array{ question: string, token: string }
     */
    public static function generate(): array
    {
        $a      = random_int(1, 20);
        $b      = random_int(1, 20);
        $answer = $a + $b;
        $expiry = time() + self::TTL;

        $payload = base64_encode((string) json_encode(['ans' => $answer, 'exp' => $expiry]));
        $sig     = hash_hmac('sha256', $payload, self::secret());

        return [
            'question' => "Combien font {$a} + {$b} ?",
            'token'    => $payload . '.' . $sig,
        ];
    }

    // ------------------------------------------------------------------
    // Verify
    // ------------------------------------------------------------------

    /**
     * Returns true when the user's answer matches the signed token and the
     * token has not expired.
     */
    public static function verify(string $token, string $userAnswer): bool
    {
        // Basic structure check
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$payload, $sig] = $parts;

        // Constant-time signature check
        $expected = hash_hmac('sha256', $payload, self::secret());
        if (!hash_equals($expected, $sig)) {
            return false;
        }

        // Decode payload
        $data = json_decode((string) base64_decode($payload), true);
        if (!is_array($data) || !isset($data['ans'], $data['exp'])) {
            return false;
        }

        // Expiry check
        if (time() > (int) $data['exp']) {
            return false;
        }

        // Answer check (trim whitespace, integer comparison)
        return (int) trim($userAnswer) === (int) $data['ans'];
    }
}
