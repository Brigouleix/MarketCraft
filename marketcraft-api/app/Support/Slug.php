<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generation de slugs uniques.
 *
 * Str::slug translittere les accents (« Céramique » devient « ceramique »),
 * ce qui reproduit le comportement de l'ancien back-end.
 */
final class Slug
{
    /**
     * @param  string   $table      Table portant la colonne `slug`.
     * @param  int|null $excludeId  Id a ignorer, lors d'une mise a jour.
     */
    public static function unique(string $nom, string $table, ?int $excludeId = null): string
    {
        $base = Str::slug($nom);

        if ($base === '') {
            $base = 'element';
        }

        $slug = $base;
        $i    = 1;

        // Boucle bornee : au-dela, on suffixe par une chaine aleatoire
        // plutot que de balayer indefiniment la table.
        while (self::existe($slug, $table, $excludeId)) {
            if ($i > 50) {
                return $base . '-' . Str::lower(Str::random(6));
            }

            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private static function existe(string $slug, string $table, ?int $excludeId): bool
    {
        $query = DB::table($table)->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
