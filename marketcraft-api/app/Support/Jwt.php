<?php

declare(strict_types=1);

namespace App\Support;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use RuntimeException;

/**
 * Emission et verification des jetons JWT.
 *
 * Le front lit `sub` et `role` dans le payload. `sub` est une CHAINE,
 * pas un entier : le caster en int cote emission casserait la comparaison
 * stricte faite cote client.
 */
final class Jwt
{
    /**
     * Jeton d'acces (24 h par defaut).
     *
     * @param array{id: int|string, email: string, role: string} $user
     */
    public static function issueAccessToken(array $user): string
    {
        return self::encode($user, (int) config('jwt.ttl'));
    }

    /**
     * Jeton de rafraichissement (7 jours par defaut). Le marqueur
     * `type: refresh` empeche de le presenter comme un jeton d'acces.
     *
     * @param array{id: int|string, email: string, role: string} $user
     */
    public static function issueRefreshToken(array $user): string
    {
        return self::encode($user, (int) config('jwt.refresh_ttl'), ['type' => 'refresh']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function encode(array $user, int $ttl, array $extra = []): string
    {
        $now = time();

        $payload = [
            'iss'   => (string) config('jwt.issuer'),
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + $ttl,
            'sub'   => (string) $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ] + $extra;

        return FirebaseJwt::encode($payload, self::secret(), (string) config('jwt.algo'));
    }

    /**
     * Decode un jeton. Retourne null si la signature est invalide, si le
     * jeton a expire ou s'il est malforme — jamais d'exception vers le haut.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $token): ?array
    {
        try {
            $decoded = FirebaseJwt::decode(
                $token,
                new Key(self::secret(), (string) config('jwt.algo'))
            );

            return (array) $decoded;
        } catch (ExpiredException | SignatureInvalidException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extrait le jeton de l'en-tete Authorization: Bearer <token>.
     */
    public static function fromHeader(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) === 1
            ? $m[1]
            : null;
    }

    private static function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '' || mb_strlen($secret) < 32) {
            throw new RuntimeException(
                'JWT_SECRET absent ou trop court (32 caracteres minimum). '
                . 'Renseignez-le dans .env.'
            );
        }

        return $secret;
    }
}
