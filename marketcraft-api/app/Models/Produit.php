<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produit — table `produits`.
 *
 * `note_moyenne` et `nombre_avis` sont maintenus par les triggers SQL
 * (trg_avis_after_insert / update / delete). L'application les lit, elle
 * ne les recalcule pas : deux sources de verite pour la meme valeur
 * finissent toujours par diverger.
 */
class Produit extends BaseModel
{
    protected $table = 'produits';

    protected $fillable = [
        'boutique_id',
        'categorie_id',
        'nom',
        'slug',
        'description',
        'prix',
        'stock',
        'images',
        'tags',
        'est_actif',
        'est_fait_main',
    ];

    protected function casts(): array
    {
        return [
            // Colonnes JSON : le contrat expose un tableau decode.
            'images'       => 'array',
            'tags'         => 'array',
            // Renvoye en chaine (« 225.00 »), jamais en nombre : le front
            // formate la valeur telle quelle.
            'prix'         => 'decimal:2',
            'note_moyenne' => 'decimal:2',
            'stock'        => 'integer',
            'nombre_avis'  => 'integer',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_id');
    }

    /** Categorie principale : la premiere selectionnee a la creation. */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    /** Ensemble des categories, via la table de liaison N-N. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Categorie::class, 'produit_categorie', 'produit_id', 'categorie_id');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class, 'produit_id');
    }

    public function lignesCommande(): HasMany
    {
        return $this->hasMany(LigneCommande::class, 'produit_id');
    }

    // ------------------------------------------------------------------
    // Portees
    // ------------------------------------------------------------------

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('produits.est_actif', 1);
    }

    /**
     * Le vendeur proprietaire, atteint par la boutique. Sert aux controles
     * de propriete sur PUT et DELETE.
     */
    public function vendeurId(): ?int
    {
        return $this->boutique?->vendeur_id !== null
            ? (int) $this->boutique->vendeur_id
            : null;
    }
}
