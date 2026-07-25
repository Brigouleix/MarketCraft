<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de commande — table `lignes_commande`.
 *
 * `prix_unitaire` et `nom_produit` sont un instantane pris au moment de
 * l'achat : une hausse de tarif ou un renommage ne doivent pas reecrire
 * l'historique des commandes passees.
 */
class LigneCommande extends BaseModel
{
    protected $table = 'lignes_commande';

    /** La table ne porte aucune colonne d'horodatage. */
    public $timestamps = false;

    protected $fillable = [
        'commande_id',
        'produit_id',
        'quantite',
        'prix_unitaire',
        'nom_produit',
    ];

    protected function casts(): array
    {
        return [
            'quantite'      => 'integer',
            'prix_unitaire' => 'decimal:2',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
