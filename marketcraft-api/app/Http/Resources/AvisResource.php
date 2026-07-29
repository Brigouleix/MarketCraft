<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Avis;
use Illuminate\Support\Collection;

/**
 * Serialisation d'un avis.
 *
 * L'auteur est aplati en `auteur_nom` / `auteur_prenom` / `auteur_avatar`.
 * Son email n'est jamais expose : les avis sont publics.
 */
final class AvisResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Avis $avis): array
    {
        $donnees = [
            'id'             => (int) $avis->id,
            'produit_id'     => (int) $avis->produit_id,
            'utilisateur_id' => (int) $avis->utilisateur_id,
            'note'           => (int) $avis->note,
            'titre'          => $avis->titre,
            'commentaire'    => $avis->commentaire,
            'est_verifie'    => (int) $avis->est_verifie,
            'created_at'     => $avis->created_at?->format('Y-m-d H:i:s'),
        ];

        $auteur = $avis->relationLoaded('utilisateur') ? $avis->utilisateur : null;

        if ($auteur !== null) {
            $donnees['auteur_nom']    = $auteur->nom;
            $donnees['auteur_prenom'] = $auteur->prenom;
            $donnees['auteur_avatar'] = $auteur->avatar_url;
        }

        return $donnees;
    }

    /**
     * @param  iterable<Avis> $avis
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $avis): array
    {
        return Collection::make($avis)
            ->map(static fn (Avis $a) => self::make($a))
            ->values()
            ->all();
    }
}
