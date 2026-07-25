<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'activite — table `journal_activite`.
 *
 * Repond a l'exigence OWASP « Security Logging and Monitoring Failures » :
 * connexions reussies et refusees, erreurs applicatives, actions
 * d'administration. Consultable en back-office via GET /admin/logs.
 *
 * Ce que l'on n'ecrit jamais ici : mot de passe, jeton, en-tete
 * Authorization. Un journal qui fuit des identifiants est pire que pas
 * de journal du tout.
 */
class JournalActivite extends BaseModel
{
    protected $table = 'journal_activite';

    /** La table est append-only : aucune ligne n'est jamais modifiee. */
    public const UPDATED_AT = null;

    /** Niveaux, du plus anodin au plus grave. */
    public const NIVEAUX = ['info', 'avertissement', 'erreur', 'critique'];

    protected $fillable = [
        'utilisateur_id',
        'action',
        'niveau',
        'message',
        'ip',
        'user_agent',
        'contexte',
    ];

    protected function casts(): array
    {
        return [
            'contexte'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function scopeNiveau(Builder $query, string $niveau): Builder
    {
        return $query->where('niveau', $niveau);
    }
}
