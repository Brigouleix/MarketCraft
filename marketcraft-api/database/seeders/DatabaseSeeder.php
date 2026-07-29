<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Jeu de donnees de demonstration.
 *
 * L'ordre est impose par les cles etrangeres : utilisateurs et categories
 * d'abord, puis boutiques et produits, enfin commandes et avis.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UtilisateurSeeder::class,
            CategorieSeeder::class,
            BoutiqueSeeder::class,
            ProduitSeeder::class,
            CommandeSeeder::class,
            AvisSeeder::class,
        ]);
    }
}
