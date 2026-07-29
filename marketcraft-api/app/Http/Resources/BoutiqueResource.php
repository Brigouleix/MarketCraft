<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Boutique;
use Illuminate\Support\Collection;

/**
 * Serialisation d'une boutique.
 *
 * Les informations du vendeur sont aplaties en `vendeur_nom`,
 * `vendeur_prenom`, `vendeur_email` : c'est la forme historique, et le
 * front les lit a plat. `vendeur_email` n'est expose que sur le detail
 * d'une boutique, pas dans le listing public — inutile d'offrir un
 * annuaire d'adresses a un aspirateur de pages.
 */
final class BoutiqueResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Boutique $boutique, bool $avecEmail = false): array
    {
        $donnees = [
            'id'           => (int) $boutique->id,
            'vendeur_id'   => (int) $boutique->vendeur_id,
            'nom'          => $boutique->nom,
            'slug'         => $boutique->slug,
            'description'  => $boutique->description,
            'logo_url'     => $boutique->logo_url,
            'banniere_url' => $boutique->banniere_url,
            'est_active'   => (int) $boutique->est_active,
            'note_moyenne' => $boutique->note_moyenne,
            'created_at'   => $boutique->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $boutique->updated_at?->format('Y-m-d H:i:s'),
        ];

        $vendeur = $boutique->relationLoaded('vendeur') ? $boutique->vendeur : null;

        if ($vendeur !== null) {
            $donnees['vendeur_nom']    = $vendeur->nom;
            $donnees['vendeur_prenom'] = $vendeur->prenom;

            if ($avecEmail) {
                $donnees['vendeur_email'] = $vendeur->email;
            }
        }

        // Renseigne uniquement par le listing, qui compte les produits actifs.
        if ($boutique->nb_produits !== null) {
            $donnees['nb_produits'] = (int) $boutique->nb_produits;
        }

        return $donnees;
    }

    /**
     * @param  iterable<Boutique> $boutiques
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $boutiques): array
    {
        return Collection::make($boutiques)
            ->map(static fn (Boutique $b) => self::make($b))
            ->values()
            ->all();
    }
}
