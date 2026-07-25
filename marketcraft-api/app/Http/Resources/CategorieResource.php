<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Categorie;
use Illuminate\Support\Collection;

/**
 * Serialisation d'une categorie.
 *
 * `parent_id` est indispensable : le front s'en sert pour repartir les
 * categories entre ses deux onglets de filtres, « Objet » et « Materiau ».
 * Sans lui, le panneau de filtres s'affiche a plat, sans erreur visible.
 */
final class CategorieResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Categorie $categorie): array
    {
        return [
            'id'          => (int) $categorie->id,
            'parent_id'   => $categorie->parent_id !== null ? (int) $categorie->parent_id : null,
            'nom'         => $categorie->nom,
            'slug'        => $categorie->slug,
            'description' => $categorie->description,
            'image_url'   => $categorie->image_url,
        ];
    }

    /**
     * @param  iterable<Categorie> $categories
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $categories): array
    {
        return Collection::make($categories)
            ->map(static fn (Categorie $c) => self::make($c))
            ->values()
            ->all();
    }
}
