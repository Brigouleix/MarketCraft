<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genere une cle de signature JWT et l'ecrit dans .env.
 *
 * Sans cette commande, la tentation est d'inventer une chaine a la main :
 * elle sera trop courte ou trop previsible, et un JWT signe avec un secret
 * faible se forge hors ligne.
 */
class GenerateJwtSecret extends Command
{
    protected $signature = 'jwt:secret {--force : Ecrase une cle existante}';

    protected $description = 'Genere JWT_SECRET et l ecrit dans le fichier .env';

    public function handle(): int
    {
        $chemin = base_path('.env');

        if (! file_exists($chemin)) {
            $this->error('Fichier .env introuvable. Copiez .env.example en .env d abord.');

            return self::FAILURE;
        }

        $contenu = (string) file_get_contents($chemin);
        $actuel  = $this->valeurActuelle($contenu);

        if ($actuel !== '' && ! $this->option('force')) {
            $this->warn('JWT_SECRET est deja renseigne. Utilisez --force pour le remplacer.');
            $this->line('Attention : remplacer la cle invalide tous les jetons en circulation.');

            return self::SUCCESS;
        }

        // 64 octets aleatoires en hexadecimal : 128 caracteres, bien au-dela
        // du minimum de 32 impose par App\Support\Jwt.
        $secret = bin2hex(random_bytes(64));

        $contenu = preg_match('/^JWT_SECRET=.*$/m', $contenu)
            ? (string) preg_replace('/^JWT_SECRET=.*$/m', "JWT_SECRET={$secret}", $contenu)
            : rtrim($contenu, "\n") . "\nJWT_SECRET={$secret}\n";

        file_put_contents($chemin, $contenu);

        $this->info('JWT_SECRET genere et ecrit dans .env.');

        return self::SUCCESS;
    }

    private function valeurActuelle(string $contenu): string
    {
        return preg_match('/^JWT_SECRET=(.*)$/m', $contenu, $m) === 1
            ? trim($m[1])
            : '';
    }
}
