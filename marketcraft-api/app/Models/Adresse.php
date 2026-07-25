<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Adresse de livraison — table `adresses_livraison`. */
class Adresse extends BaseModel
{
    protected $table = 'adresses_livraison';

    /** La table ne porte pas de colonne `updated_at`. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'utilisateur_id',
        'nom_complet',
        'ligne1',
        'ligne2',
        'ville',
        'code_postal',
        'pays',
        'est_principale',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
