<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Tentative de connexion — table `tentatives_connexion`.
 *
 * Support du verrouillage de compte : 5 echecs consecutifs bloquent
 * l'adresse pendant 15 minutes. Une table plutot qu'un cache, pour deux
 * raisons : la trace survit a un vidage de cache ou a un redemarrage, et
 * elle reste consultable pour justifier un blocage aupres d'un utilisateur.
 *
 * L'email est conserve tel qu'il a ete saisi (normalise en minuscules),
 * jamais le mot de passe essaye.
 */
class TentativeConnexion extends BaseModel
{
    protected $table = 'tentatives_connexion';

    public const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'ip',
        'reussie',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
