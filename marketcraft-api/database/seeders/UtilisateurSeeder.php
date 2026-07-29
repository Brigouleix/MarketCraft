<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adresse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes de demonstration.
 *
 * Mot de passe commun : Password123
 * Il respecte la politique de complexite appliquee a l'inscription
 * (8 caracteres, majuscule, minuscule, chiffre) — pratique pour montrer
 * la regle en soutenance sans avoir a la contourner.
 *
 * Le hachage est calcule a l'execution, avec bcrypt cout 12 : aucun
 * condensat n'est ecrit en dur dans le depot.
 */
class UtilisateurSeeder extends Seeder
{
    public function run(): void
    {
        $motDePasse = Hash::make('Password123');

        $comptes = [
            ['nom' => 'Dupont',  'prenom' => 'Marie',   'email' => 'marie.dupont@example.com',   'role' => 'admin'],
            ['nom' => 'Martin',  'prenom' => 'Paul',    'email' => 'paul.martin@example.com',    'role' => 'vendeur'],
            ['nom' => 'Bernard', 'prenom' => 'Sophie',  'email' => 'sophie.bernard@example.com', 'role' => 'vendeur'],
            ['nom' => 'Lemoine', 'prenom' => 'Jules',   'email' => 'jules.lemoine@example.com',  'role' => 'client'],
            ['nom' => 'Petit',   'prenom' => 'Camille', 'email' => 'camille.petit@example.com',  'role' => 'client'],
        ];

        foreach ($comptes as $compte) {
            User::query()->updateOrCreate(
                ['email' => $compte['email']],
                $compte + ['password_hash' => $motDePasse, 'est_actif' => 1]
            );
        }

        $adresses = [
            ['jules.lemoine@example.com',  'Jules Lemoine', '12 rue des Fleurs',     'Lyon',  '69001'],
            ['camille.petit@example.com',  'Camille Petit', '8 avenue des Artisans', 'Paris', '75011'],
        ];

        foreach ($adresses as [$email, $nomComplet, $ligne1, $ville, $codePostal]) {
            $utilisateur = User::query()->where('email', $email)->first();

            if ($utilisateur === null) {
                continue;
            }

            Adresse::query()->updateOrCreate(
                ['utilisateur_id' => $utilisateur->id, 'ligne1' => $ligne1],
                [
                    'nom_complet'    => $nomComplet,
                    'ville'          => $ville,
                    'code_postal'    => $codePostal,
                    'pays'           => 'France',
                    'est_principale' => 1,
                ]
            );
        }
    }
}
