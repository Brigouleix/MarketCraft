<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoutiqueSeeder extends Seeder
{
    public function run(): void
    {
        $boutiques = [
            [
                'email'       => 'paul.martin@example.com',
                'nom'         => "L'Atelier de Paul",
                'slug'        => 'atelier-de-paul',
                'description' => 'Créations en bois fait main, sculptures et objets décoratifs uniques.',
            ],
            [
                'email'       => 'sophie.bernard@example.com',
                'nom'         => 'Sophie Céramiques',
                'slug'        => 'sophie-ceramiques',
                'description' => 'Poteries et céramiques artisanales inspirées de la nature.',
            ],
        ];

        foreach ($boutiques as $donnees) {
            $vendeur = User::query()->where('email', $donnees['email'])->first();

            if ($vendeur === null) {
                continue;
            }

            // updateOrCreate sur `vendeur_id` : la cle unique interdit une
            // seconde boutique pour le meme vendeur, et le seeder doit
            // pouvoir etre rejoue.
            Boutique::query()->updateOrCreate(
                ['vendeur_id' => $vendeur->id],
                [
                    'nom'         => $donnees['nom'],
                    'slug'        => $donnees['slug'],
                    'description' => $donnees['description'],
                    'est_active'  => 1,
                ]
            );
        }
    }
}
