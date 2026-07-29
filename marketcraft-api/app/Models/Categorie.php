<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categorie — table `categories`.
 *
 * Hierarchie sur deux niveaux via `parent_id`, auto-referent : deux racines,
 * « Objet » et « Materiau », regroupent leurs sous-categories. Le front
 * s'appuie sur `parent_id` pour construire ses deux onglets de filtres ;
 * l'omettre d'une reponse casse le regroupement sans lever d'erreur.
 */
class Categorie extends BaseModel
{
    protected $table = 'categories';

    /** La table ne porte pas de colonne `updated_at`. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'parent_id',
        'nom',
        'slug',
        'description',
        'image_url',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function produits(): BelongsToMany
    {
        // La table de liaison ne porte ni identifiant ni horodatage :
        // pas de withTimestamps() ici.
        return $this->belongsToMany(Produit::class, 'produit_categorie', 'categorie_id', 'produit_id');
    }

    public function scopeRacines(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
