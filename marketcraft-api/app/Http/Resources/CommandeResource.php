<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Commande;
use Illuminate\Support\Collection;

/**
 * Serialisation d'une commande.
 *
 * Deux formes, volontairement differentes :
 *
 *   - le listing (`resume`) ajoute `nb_articles` et n'emporte pas les
 *     lignes : la page « mes commandes » n'en a pas besoin, et les charger
 *     multiplierait les requetes ;
 *   - le detail emporte les lignes, l'adresse aplatie en `addr_*` et
 *     l'acheteur en `utilisateur_*`.
 */
final class CommandeResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Commande $commande): array
    {
        $donnees = self::base($commande);

        $acheteur = $commande->relationLoaded('utilisateur') ? $commande->utilisateur : null;

        if ($acheteur !== null) {
            $donnees['utilisateur_nom']    = $acheteur->nom;
            $donnees['utilisateur_prenom'] = $acheteur->prenom;
            $donnees['utilisateur_email']  = $acheteur->email;
        }

        // L'adresse est aplatie avec le prefixe `addr_`, forme attendue par
        // la page de suivi et par la facture.
        $adresse = $commande->relationLoaded('adresse') ? $commande->adresse : null;

        $donnees['addr_ligne1']      = $adresse?->ligne1;
        $donnees['addr_ligne2']      = $adresse?->ligne2;
        $donnees['addr_ville']       = $adresse?->ville;
        $donnees['addr_code_postal'] = $adresse?->code_postal;
        $donnees['addr_pays']        = $adresse?->pays;

        $donnees['lignes'] = $commande->relationLoaded('lignes')
            ? $commande->lignes->map(static function ($ligne) {
                return [
                    'id'             => (int) $ligne->id,
                    'commande_id'    => (int) $ligne->commande_id,
                    'produit_id'     => (int) $ligne->produit_id,
                    'quantite'       => (int) $ligne->quantite,
                    'prix_unitaire'  => $ligne->prix_unitaire,
                    'nom_produit'    => $ligne->nom_produit,
                    'produit_slug'   => $ligne->relationLoaded('produit') ? $ligne->produit?->slug : null,
                    'produit_images' => $ligne->relationLoaded('produit') ? $ligne->produit?->images : null,
                ];
            })->values()->all()
            : [];

        return $donnees;
    }

    /**
     * Forme allegee du listing.
     *
     * @return array<string, mixed>
     */
    public static function resume(
        Commande $commande,
        bool $avecClient = false,
        bool $avecLignes = false,
    ): array {
        $donnees = self::base($commande);

        $donnees['nb_articles'] = (int) ($commande->nb_articles ?? 0);

        // Forme allegee des lignes : de quoi construire un lien vers la
        // fiche produit, rien de plus. Le detail complet reste sur
        // GET /orders/:id.
        if ($avecLignes && $commande->relationLoaded('lignes')) {
            $donnees['lignes'] = $commande->lignes->map(static fn ($ligne) => [
                'produit_id'     => (int) $ligne->produit_id,
                'nom_produit'    => $ligne->nom_produit,
                'quantite'       => (int) $ligne->quantite,
                'produit_slug'   => $ligne->relationLoaded('produit') ? $ligne->produit?->slug : null,
                'produit_images' => $ligne->relationLoaded('produit') ? $ligne->produit?->images : null,
            ])->values()->all();
        }

        // Le nom du client n'est expose que sur la vue « ventes » d'un
        // vendeur, qui a besoin de savoir a qui expedier. La liste des
        // commandes d'un acheteur n'a aucune raison de le porter.
        if ($avecClient) {
            $acheteur = $commande->relationLoaded('utilisateur') ? $commande->utilisateur : null;

            $donnees['client_nom']    = $acheteur?->nom;
            $donnees['client_prenom'] = $acheteur?->prenom;
        }

        return $donnees;
    }

    /**
     * @return array<string, mixed>
     */
    private static function base(Commande $commande): array
    {
        return [
            'id'                   => (int) $commande->id,
            'utilisateur_id'       => (int) $commande->utilisateur_id,
            'adresse_livraison_id' => $commande->adresse_livraison_id !== null
                ? (int) $commande->adresse_livraison_id
                : null,
            'statut'               => $commande->statut,
            'montant_total'        => $commande->montant_total,
            'frais_livraison'      => $commande->frais_livraison,
            'note'                 => $commande->note,
            'numero_suivi'         => $commande->numero_suivi,
            'date_livraison'       => $commande->date_livraison?->format('Y-m-d H:i:s'),
            'created_at'           => $commande->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $commande->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  iterable<Commande> $commandes
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $commandes): array
    {
        return Collection::make($commandes)
            ->map(static fn (Commande $c) => self::resume($c))
            ->values()
            ->all();
    }
}
