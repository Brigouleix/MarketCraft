<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Avis;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Avis de demonstration.
 *
 * Chaque avis correspond a un produit reellement commande par son auteur,
 * comme l'exige l'API. L'insertion declenche les triggers qui recalculent
 * `produits.note_moyenne`, `produits.nombre_avis`, puis en cascade
 * `boutiques.note_moyenne` : ces colonnes ne sont jamais renseignees ici.
 */
class AvisSeeder extends Seeder
{
    public function run(): void
    {
        $avis = [
            [
                'produit'     => 'bol-noyer-cire',
                'email'       => 'jules.lemoine@example.com',
                'note'        => 5,
                'titre'       => 'Superbe qualité !',
                'commentaire' => 'Le bol est magnifique et très bien fini. Je recommande vivement cet artisan.',
            ],
            [
                'produit'     => 'cadre-photo-rustique',
                'email'       => 'jules.lemoine@example.com',
                'note'        => 4,
                'titre'       => 'Très joli cadre',
                'commentaire' => 'Beau produit, livraison rapide. Le bois flotté donne un charme naturel.',
            ],
            [
                'produit'     => 'mug-gres-bleu-ocean',
                'email'       => 'camille.petit@example.com',
                'note'        => 5,
                'titre'       => 'Parfait pour le café',
                'commentaire' => 'Le mug est lourd et solide, exactement ce que je cherchais. Belle couleur.',
            ],
        ];

        $produits = Produit::query()->pluck('id', 'slug');
        $clients  = User::query()->pluck('id', 'email');

        foreach ($avis as $donnees) {
            $produitId = $produits[$donnees['produit']] ?? null;
            $clientId  = $clients[$donnees['email']] ?? null;

            if ($produitId === null || $clientId === null) {
                continue;
            }

            Avis::query()->updateOrCreate(
                ['produit_id' => $produitId, 'utilisateur_id' => $clientId],
                [
                    'note'        => $donnees['note'],
                    'titre'       => $donnees['titre'],
                    'commentaire' => $donnees['commentaire'],
                    'est_verifie' => 1,
                ]
            );
        }
    }
}
