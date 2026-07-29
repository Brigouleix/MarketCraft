<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Jwt;
use Tests\TestCase;

class JwtTest extends TestCase
{
    public function test_sub_est_une_chaine_et_non_un_entier(): void
    {
        // Le front compare `sub` a une chaine : un entier ferait echouer
        // la comparaison stricte cote client.
        $payload = Jwt::decode(Jwt::issueAccessToken([
            'id' => 12, 'email' => 'a@b.fr', 'role' => 'client',
        ]));

        $this->assertSame('12', $payload['sub']);
        $this->assertSame('client', $payload['role']);
        $this->assertSame('a@b.fr', $payload['email']);
    }

    public function test_un_jeton_altere_est_rejete(): void
    {
        $jeton = Jwt::issueAccessToken(['id' => 1, 'email' => 'a@b.fr', 'role' => 'client']);

        $this->assertNull(Jwt::decode($jeton . 'x'));
        $this->assertNull(Jwt::decode('nimporte.quoi.ici'));
    }

    public function test_le_jeton_de_rafraichissement_porte_son_type(): void
    {
        $payload = Jwt::decode(Jwt::issueRefreshToken([
            'id' => 1, 'email' => 'a@b.fr', 'role' => 'client',
        ]));

        $this->assertSame('refresh', $payload['type']);
        // 7 jours contre 24 h pour un jeton d acces.
        $this->assertGreaterThan(6 * 86400, $payload['exp'] - $payload['iat']);
    }

    public function test_extraction_de_l_entete_authorization(): void
    {
        $this->assertSame('abc', Jwt::fromHeader('Bearer abc'));
        $this->assertSame('abc', Jwt::fromHeader('bearer abc'));
        $this->assertNull(Jwt::fromHeader('Basic abc'));
        $this->assertNull(Jwt::fromHeader(null));
    }
}
