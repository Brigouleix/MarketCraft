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
        // Le hash utilisé dans migrations.sql correspond à "password" (sans "123")
        $hashMigration = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

        $this->assertTrue(password_verify('password', $hashMigration));
        $this->assertFalse(password_verify('password123', $hashMigration));
    }
}
