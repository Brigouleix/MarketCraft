<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paiement — table `paiements`.
 *
 * La plateforme n'est reliee a aucun prestataire reel : les transactions
 * sont simulees et prefixees « SIM- ». Le passage au statut « libere »
 * est du ressort de la procedure stockee sp_liberer_paiements_echus(),
 * declenchee a J+14 par un event MySQL.
 */
class Paiement extends BaseModel
{
    protected $table = 'paiements';

    public const METHODES = ['carte', 'virement', 'paypal', 'cheque'];

    public const STATUTS = ['en_attente', 'valide', 'refuse', 'rembourse', 'libere'];

    protected $fillable = [
        'commande_id',
        'methode',
        'statut',
        'montant',
        'transaction_id',
        'date_liberation',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'montant'         => 'decimal:2',
            'payload'         => 'array',
            'date_liberation' => 'datetime',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }
}
