<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Database\Seeder;

/**
 * Catalogue de demonstration.
 *
 * Chaque produit porte DEUX categories : une d'objet et une de materiau.
 * C'est indispensable pour montrer le croisement des filtres — avec une
 * seule categorie par produit, cocher « Céramique » puis « Argile »
 * renverrait toujours une liste vide et le comportement passerait pour
 * un bug.
 *
 * Les images reprennent les noms de fichiers du jeu de donnees d'origine.
 * Ces fichiers n'existent pas dans public/uploads : le front detecte des
 * valeurs qui ne sont pas des URL exploitables et affiche son visuel de
 * remplacement. Deposer de vraies images dans public/uploads et remplacer
 * ces valeurs par des URL absolues suffit a les faire apparaitre.
 */
class ProduitSeeder extends Seeder
{
    public function run(): void
    {
        $produits = [
            [
                'boutique'    => 'atelier-de-paul',
                'nom'         => 'Bol en noyer ciré',
                'slug'        => 'bol-noyer-cire',
                'description' => "Bol tourné à la main en noyer massif, finition cire d'abeille naturelle. Diamètre 20 cm.",
                'prix'        => 45.00,
                'stock'       => 12,
                'images'      => ['bol-noyer-1.jpg', 'bol-noyer-2.jpg'],
                'categories'  => ['decoration-maison', 'bois'],
            ],
            [
                'boutique'    => 'atelier-de-paul',
                'nom'         => 'Planche à découper chêne',
                'slug'        => 'planche-decoupe-chene',
                'description' => 'Planche à découper en chêne massif avec poignée sculptée. 35x25 cm, épaisseur 3 cm.',
                'prix'        => 65.00,
                'stock'       => 0,
                'images'      => ['planche-1.jpg'],
                'categories'  => ['menuiserie', 'bois'],
            ],
            [
                'boutique'    => 'atelier-de-paul',
                'nom'         => 'Cadre photo rustique',
                'slug'        => 'cadre-photo-rustique',
                'description' => 'Cadre photo en bois flotté récupéré, format 15x20 cm. Finition naturelle.',
                'prix'        => 28.00,
                'stock'       => 20,
                'images'      => ['cadre-1.jpg', 'cadre-2.jpg'],
                'categories'  => ['decoration-maison', 'bois'],
            ],
            [
                'boutique'    => 'sophie-ceramiques',
                'nom'         => 'Mug grès bleu océan',
                'slug'        => 'mug-gres-bleu-ocean',
                'description' => 'Mug en grès émaillé à la main, nuances de bleu. Contenance 350 ml. Passe au lave-vaisselle.',
                'prix'        => 38.00,
                'stock'       => 15,
                'images'      => ['mug-bleu-1.jpg'],
                'categories'  => ['poterie', 'ceramique'],
            ],
            [
                'boutique'    => 'sophie-ceramiques',
                'nom'         => 'Vase effilé terracotta',
                'slug'        => 'vase-effile-terracotta',
                'description' => 'Vase effilé en terracotta non émaillée, hauteur 30 cm. Idéal pour fleurs séchées.',
                'prix'        => 55.00,
                'stock'       => 6,
                'images'      => ['vase-terra-1.jpg', 'vase-terra-2.jpg'],
                'categories'  => ['poterie', 'argile'],
            ],
            [
                'boutique'    => 'sophie-ceramiques',
                'nom'         => 'Assiette creuse fleurie',
                'slug'        => 'assiette-creuse-fleurie',
                'description' => 'Assiette creuse peinte à la main avec motifs floraux. Diamètre 22 cm. Faite main unique.',
                'prix'        => 42.00,
                'stock'       => 10,
                'images'      => ['assiette-1.jpg'],
                'categories'  => ['decoration-maison', 'ceramique'],
            ],
        ];

        $boutiques  = Boutique::query()->pluck('id', 'slug');
        $categories = Categorie::query()->pluck('id', 'slug');

        foreach ($produits as $donnees) {
            $boutiqueId = $boutiques[$donnees['boutique']] ?? null;

            if ($boutiqueId === null) {
                continue;
            }

            $ids = array_values(array_filter(array_map(
                static fn (string $slug) => $categories[$slug] ?? null,
                $donnees['categories']
            )));

            $produit = Produit::query()->updateOrCreate(
                ['slug' => $donnees['slug']],
                [
                    'boutique_id'   => $boutiqueId,
                    // La premiere de la liste est la categorie principale.
                    'categorie_id'  => $ids[0] ?? null,
                    'nom'           => $donnees['nom'],
                    'description'   => $donnees['description'],
                    'prix'          => $donnees['prix'],
                    'stock'         => $donnees['stock'],
                    'images'        => $donnees['images'],
                    'est_actif'     => 1,
                    'est_fait_main' => 1,
                ]
            );

            $produit->categories()->sync($ids);
        }
    }
}
