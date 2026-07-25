<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Boutique — table `boutiques`. Une seule par vendeur (cle unique). */
class Boutique extends BaseModel
{
    protected $table = 'boutiques';

    protected $fillable = [
        'vendeur_id',
        'nom',
        'slug',
        'description',
        'logo_url',
        'banniere_url',
        'est_active',
        'note_moyenne',
    ];

    protected function casts(): array
    {
        return [
            // Renvoyee en chaine (« 4.50 »), comme toutes les valeurs
            // decimales du contrat.
            'note_moyenne' => 'decimal:2',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'boutique_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('est_active', 1);
    }
}
