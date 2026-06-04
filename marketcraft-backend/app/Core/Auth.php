<?php

declare(strict_types=1);

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use App\Models\User;

class Auth
{
    private static string $algorithm = 'HS256';

    // ------------------------------------------------------------------
    // Clé secrète
    // ------------------------------------------------------------------

    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';

        if (empty($secret)) {
            // En production ce cas ne doit jamais arriver
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }

        return $secret;
    }

    private static function getTtl(): int
    {
        $ttl = $_ENV['JWT_TTL'] ?? getenv('JWT_TTL') ?: 86400; // 24 h par défaut
        return (int) $ttl;
    }

    // ------------------------------------------------------------------
    // Génération du token
    // ------------------------------------------------------------------

    /**
     * Génère un JWT signé pour l'utilisateur donné.
     *
     * @param array $user Tableau avec au moins : id, email, role
     */
    public static function generateToken(array $user): string
    {
        $now = time();

        $payload = [
            'iss'   => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'marketcraft',
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + self::getTtl(),
            'sub'   => (string) $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];

        return JWT::encode($payload, self::getSecret(), self::$algorithm);
    }

    // ------------------------------------------------------------------
    // Génération du refresh token (durée plus longue : 30 jours)
    // ------------------------------------------------------------------

    public static function generateRefreshToken(array $user): string
    {
        $now = time();

        $payload = [
            'iss'  => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'marketcraft',
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + 86400 * 30,
            'sub'  => (string) $user['id'],
            'email'=> $user['email'],
            'role' => $user['role'],
            'type' => 'refresh',
        ];

        return JWT::encode($payload, self::getSecret(), self::$algorithm);
    }

    // ------------------------------------------------------------------
    // Validation du token (depuis le header Authorization)
    // ------------------------------------------------------------------

    /**
     * Décode et valide le JWT présent dans le header Authorization.
     *
     * @return array|null Payload décodé ou null si invalide
     */
    public static function validateToken(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($header)) {
            // Essai via le header réécrit par Apache (mod_rewrite)
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (empty($header)) {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key(self::getSecret(), self::$algorithm));
            return (array) $decoded;
        } catch (ExpiredException) {
            return null;
        } catch (SignatureInvalidException) {
            return null;
        } catch (BeforeValidException) {
            return null;
        } catch (\Exception) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Middleware : authenticate()
    // ------------------------------------------------------------------

    /**
     * Middleware JWT.
     * Si le token est invalide, envoie une réponse 401 et retourne false.
     * Sinon, stocke le payload dans $_REQUEST['_auth'] et retourne true.
     */
    public static function authenticate(): bool
    {
        $payload = self::validateToken();

        if ($payload === null) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => 'Unauthorized. A valid Bearer token is required.',
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        // Stocker dans $_REQUEST pour accès depuis les controllers
        $_REQUEST['_auth'] = $payload;
        return true;
    }

    // ------------------------------------------------------------------
    // Récupérer l'utilisateur courant depuis le token stocké
    // ------------------------------------------------------------------

    /**
     * Retourne le payload JWT de l'utilisateur authentifié (après middleware).
     */
    public static function getCurrentUser(): ?array
    {
        return $_REQUEST['_auth'] ?? null;
    }

    /**
     * Vérifie si l'utilisateur courant a le rôle requis.
     */
    public static function hasRole(string ...$roles): bool
    {
        $user = self::getCurrentUser();
        if ($user === null) {
            return false;
        }

        return in_array($user['role'] ?? '', $roles, true);
    }

    /**
     * Vérifie si l'utilisateur courant est propriétaire de la ressource
     * ou possède le rôle admin.
     */
    public static function isOwnerOrAdmin(int $ownerId): bool
    {
        $user = self::getCurrentUser();
        if ($user === null) {
            return false;
        }

        return (int) ($user['sub'] ?? 0) === $ownerId || $user['role'] === 'admin';
    }
}
