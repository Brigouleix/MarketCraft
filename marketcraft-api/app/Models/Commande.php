<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Commande — table `commandes`. */
class Commande extends BaseModel
{
    protected $table = 'commandes';

    /** Statuts autorises, dans leur ordre de progression. */
    public const STATUTS = [
        'en_attente',
        'confirmee',
        'en_preparation',
        'expediee',
        'livree',
        'annulee',
    ];

    /** Une commande deja partie ou livree ne peut plus etre annulee. */
    public const STATUTS_NON_ANNULABLES = ['expediee', 'livree', 'annulee'];

    protected $fillable = [
        'utilisateur_id',
        'adresse_livraison_id',
        'statut',
        'montant_total',
        'frais_livraison',
        'note',
        'numero_suivi',
        'date_livraison',
    ];

    protected function casts(): array
    {
        return [
            'montant_total'   => 'decimal:2',
            'frais_livraison' => 'decimal:2',
            'date_livraison'  => 'datetime',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function adresse(): BelongsTo
    {
        return $this->belongsTo(Adresse::class, 'adresse_livraison_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneCommande::class, 'commande_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'commande_id');
    }

    public function estAnnulable(): bool
    {
        return ! in_array($this->statut, self::STATUTS_NON_ANNULABLES, true);
    }
}
