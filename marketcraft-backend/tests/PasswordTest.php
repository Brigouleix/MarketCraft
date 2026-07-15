<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class PasswordTest extends TestCase
{
    public function test_password_hash_et_verify(): void
    {
        $plain = 'password123';
        $hash  = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->assertTrue(password_verify($plain, $hash));
        $this->assertFalse(password_verify('mauvais_mdp', $hash));
    }

    public function test_deux_hash_du_meme_mot_de_passe_sont_differents(): void
    {
        $plain = 'password123';
        $hash1 = password_hash($plain, PASSWORD_BCRYPT);
        $hash2 = password_hash($plain, PASSWORD_BCRYPT);

        // bcrypt génère un sel aléatoire, donc les hash diffèrent
        $this->assertNotSame($hash1, $hash2);
        // Mais les deux vérifient quand même le bon mot de passe
        $this->assertTrue(password_verify($plain, $hash1));
        $this->assertTrue(password_verify($plain, $hash2));
    }

    public function test_hash_du_fichier_migrations_correspond_a_password(): void
    {
        // Lit le hash directement dans migrations.sql : il doit correspondre
        // au mot de passe annoncé dans le commentaire du seed ("password123").
        $sql = file_get_contents(__DIR__ . '/../migrations.sql');
        $this->assertNotFalse($sql);

        preg_match('/\$2y\$\d{2}\$[.\/A-Za-z0-9]{53}/', $sql, $matches);
        $this->assertNotEmpty($matches, 'Aucun hash bcrypt trouvé dans migrations.sql');

        $hashMigration = $matches[0];

        $this->assertTrue(password_verify('password123', $hashMigration));
        $this->assertFalse(password_verify('password', $hashMigration));
    }
}
