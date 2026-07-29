<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Seeder;

/**
 * Hierarchie des categories sur deux niveaux.
 *
 * Deux racines, « Objet » et « Materiau », alimentent les deux onglets de
 * filtres du front. C'est cette separation qui donne son sens au croisement
 * ET entre les parametres `categorie` et `materiau` : « Ceramique » (objet)
 * combine a « Argile » (materiau) renvoie les ceramiques EN argile.
 *
 * Les libelles portent leurs accents, les slugs n'en portent jamais : un
 * slug accentue se retrouve percent-encode dans les URL de filtres.
 */
class CategorieSeeder extends Seeder
{
    public function run(): void
    {
        $objet = $this->racine('Objet', 'objet', 'Nature de la piece artisanale', 0);
        $materiau = $this->racine('Materiau', 'materiau', 'Matiere principale de fabrication', 1);

        $objets = [
            ['Bijoux',            'bijoux',            'Bijoux artisanaux faits main',                    2],
            ['Textile',           'textile',           'Vêtements et décorations textiles',               3],
            ['Décoration Maison', 'decoration-maison', 'Objets décoratifs pour embellir votre intérieur', 4],
            ['Menuiserie',        'menuiserie',        'Ouvrages et agencements en bois',                 5],
            ['Poterie',           'poterie',           'Poteries et terres cuites',                       6],
            ['Accessoires',       'accessoires',       'Accessoires et petite maroquinerie',              7],
            ['Couture',           'couture',           'Créations cousues, sacs et linge',                8],
        ];

        $materiaux = [
            ['Bois',   'bois',   'Objets et meubles en bois travaillés à la main',  1],
            ['Métal',  'metal',  'Fer, acier, laiton, cuivre',                      2],
            ['Or',     'or',     'Or massif ou plaqué',                             3],
            ['Argent', 'argent', 'Argent massif et argent 925',                     4],
            ['Argile',    'argile',    'Terre cuite, grès, porcelaine',            5],
            // La céramique est une matière, pas un type d'objet : c'est
            // « Poterie » qui désigne l'objet. Les deux se croisent donc
            // dans le filtre plutôt que de se concurrencer.
            ['Céramique', 'ceramique', 'Terre cuite émaillée, grès, porcelaine',   11],
            ['Béton',  'beton',  'Béton ciré, béton minéral',                       6],
            ['Verre',  'verre',  'Verre soufflé, vitrail, verre fondu',             7],
            ['Cuir',   'cuir',   'Cuir tanné et travaillé à la main',               8],
            ['Pierre', 'pierre', 'Marbre, ardoise, pierre naturelle',               9],
            ['Résine', 'resine', 'Résine époxy et résines de coulée',              10],
        ];

        foreach ($objets as [$nom, $slug, $description, $ordre]) {
            $this->enfant($objet->id, $nom, $slug, $description, $ordre);
        }

        foreach ($materiaux as [$nom, $slug, $description, $ordre]) {
            $this->enfant($materiau->id, $nom, $slug, $description, $ordre);
        }
    }

    private function racine(string $nom, string $slug, string $description, int $ordre): Categorie
    {
        return Categorie::query()->updateOrCreate(
            ['slug' => $slug],
            ['parent_id' => null, 'nom' => $nom, 'description' => $description, 'ordre' => $ordre]
        );
    }

    private function enfant(int $parentId, string $nom, string $slug, string $description, int $ordre): void
    {
        Categorie::query()->updateOrCreate(
            ['slug' => $slug],
            ['parent_id' => $parentId, 'nom' => $nom, 'description' => $description, 'ordre' => $ordre]
        );
    }
}
