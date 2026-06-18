<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        // Injecter les variables d'env nécessaires à Auth
        $_ENV['JWT_SECRET'] = 'test_secret_at_least_32_chars_long_ok';
        $_ENV['JWT_TTL']    = '3600';
        $_ENV['APP_URL']    = 'http://localhost:8000';
    }

    // ------------------------------------------------------------------
    // generateToken
    // ------------------------------------------------------------------

    public function test_generate_token_retourne_une_chaine(): void
    {
        $user  = ['id' => 1, 'email' => 'paul@test.com', 'role' => 'vendeur'];
        $token = Auth::generateToken($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        // Un JWT a exactement 3 segments séparés par des points
        $this->assertCount(3, explode('.', $token));
    }

    public function test_validate_token_decode_le_payload(): void
    {
        $user  = ['id' => 42, 'email' => 'camille@test.com', 'role' => 'client'];
        $token = Auth::generateToken($user);

        // Simuler le header Authorization
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$token}";

        $payload = Auth::validateToken();

        $this->assertNotNull($payload);
        $this->assertSame('42', $payload['sub']);
        $this->assertSame('camille@test.com', $payload['email']);
        $this->assertSame('client', $payload['role']);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function test_validate_token_retourne_null_si_invalide(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token.invalide.ici';
        $payload = Auth::validateToken();
        $this->assertNull($payload);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function test_validate_token_retourne_null_si_absent(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $payload = Auth::validateToken();
        $this->assertNull($payload);
    }

    // ------------------------------------------------------------------
    // generateRefreshToken
    // ------------------------------------------------------------------

    public function test_generate_refresh_token_retourne_un_jwt(): void
    {
        $user  = ['id' => 5, 'email' => 'jules@test.com', 'role' => 'client'];
        $token = Auth::generateRefreshToken($user);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token));
    }

    public function test_refresh_token_a_type_refresh(): void
    {
        $user    = ['id' => 5, 'email' => 'jules@test.com', 'role' => 'client'];
        $refresh = Auth::generateRefreshToken($user);

        // Décoder le payload (partie centrale du JWT, base64url)
        $parts   = explode('.', $refresh);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertSame('refresh', $payload['type']);
    }

    // ------------------------------------------------------------------
    // hasRole / isOwnerOrAdmin
    // ------------------------------------------------------------------

    public function test_has_role_retourne_true_si_role_correspond(): void
    {
        $_REQUEST['_auth'] = ['sub' => '1', 'email' => 'x@x.com', 'role' => 'vendeur'];

        $this->assertTrue(Auth::hasRole('vendeur'));
        $this->assertTrue(Auth::hasRole('admin', 'vendeur'));
        $this->assertFalse(Auth::hasRole('client'));

        unset($_REQUEST['_auth']);
    }

    public function test_is_owner_or_admin_retourne_true_pour_le_proprietaire(): void
    {
        $_REQUEST['_auth'] = ['sub' => '7', 'email' => 'x@x.com', 'role' => 'client'];

        $this->assertTrue(Auth::isOwnerOrAdmin(7));
        $this->assertFalse(Auth::isOwnerOrAdmin(99));

        unset($_REQUEST['_auth']);
    }

    public function test_is_owner_or_admin_retourne_true_pour_admin(): void
    {
        $_REQUEST['_auth'] = ['sub' => '3', 'email' => 'x@x.com', 'role' => 'admin'];

        $this->assertTrue(Auth::isOwnerOrAdmin(999)); // pas propriétaire mais admin

        unset($_REQUEST['_auth']);
    }
}
