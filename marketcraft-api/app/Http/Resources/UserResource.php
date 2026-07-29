<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;

/**
 * Serialisation d'un utilisateur.
 *
 * `password_hash` est absent par construction : la cle n'est jamais
 * ajoutee, plutot que retiree apres coup. Un `unset()` en fin de methode
 * s'oublie le jour ou l'on ajoute une variante de la reponse.
 */
final class UserResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user): array
    {
        return [
            'id'         => (int) $user->id,
            'nom'        => $user->nom,
            'prenom'     => $user->prenom,
            'email'      => $user->email,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url,
            'telephone'  => $user->telephone,
            'est_actif'  => (int) $user->est_actif,
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
