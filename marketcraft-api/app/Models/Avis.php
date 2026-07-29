<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis — table `avis`.
 *
 * Nom singulier et pluriel confondus : sans `$table` explicite, Eloquent
 * chercherait la table « avis » a partir de « Avi ». Un seul avis par
 * couple (produit, utilisateur), garanti par une cle unique en base.
 *
 * Toute ecriture ici declenche les triggers qui recalculent
 * `produits.note_moyenne`, `produits.nombre_avis` puis, en cascade,
 * `boutiques.note_moyenne`.
 */
class Avis extends BaseModel
{
    protected $table = 'avis';

    /** La table ne porte pas de colonne `updated_at`. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'produit_id',
        'utilisateur_id',
        'note',
        'titre',
        'commentaire',
        'est_verifie',
    ];

    protected function casts(): array
    {
        return [
            'note'       => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
