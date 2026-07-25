<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Produit;
use Illuminate\Support\Collection;

/**
 * Serialisation d'un produit.
 *
 * Le front consomme cette forme telle quelle. Chaque cle presente ici l'est
 * parce qu'une page en depend :
 *
 *   `prix`             chaine « 225.00 », jamais un nombre
 *   `images`           tableau decode, meme si la colonne est du JSON brut
 *   `est_actif`, `est_fait_main`   entiers 0/1, pas des booleens
 *   `boutique`         objet imbriquee { id, nom }
 *   `boutique_nom`     doublon a plat, encore lu par certaines vues
 *   `categorie`        nom de la categorie principale, en chaine
 *   `categorie_nom`    idem, autre nom historique
 *   `categories`       tableau [{ id, nom }] issu de la liaison N-N
 *   `nb_avis`          alias de `nombre_avis`
 *
 * Retirer l'un de ces alias vide un bloc de l'interface sans lever
 * d'erreur : le front lit la cle, trouve `undefined`, et n'affiche rien.
 */
final class ProduitResource
{
    /**
     * @param  bool $detail  Ajoute `vendeur_id`, expose uniquement sur la
     *                       fiche produit (GET /products/:id), ou il sert
     *                       au front a decider d'afficher les actions
     *                       d'edition.
     * @return array<string, mixed>
     */
    public static function make(Produit $produit, bool $detail = false): array
    {
        $donnees = [
            'id'            => (int) $produit->id,
            'boutique_id'   => (int) $produit->boutique_id,
            'categorie_id'  => $produit->categorie_id !== null ? (int) $produit->categorie_id : null,
            'nom'           => $produit->nom,
            'slug'          => $produit->slug,
            'description'   => $produit->description,
            'prix'          => $produit->prix,
            'stock'         => (int) $produit->stock,
            // Maintenus par les triggers SQL, jamais recalcules ici.
            'note_moyenne'  => $produit->note_moyenne,
            'nombre_avis'   => (int) $produit->nombre_avis,
            'nb_avis'       => (int) $produit->nombre_avis,
            'images'        => $produit->images,
            'tags'          => $produit->tags,
            'est_actif'     => (int) $produit->est_actif,
            'est_fait_main' => (int) $produit->est_fait_main,
            'created_at'    => $produit->created_at?->format('Y-m-d H:i:s'),
            'updated_at'    => $produit->updated_at?->format('Y-m-d H:i:s'),
        ];

        // `boutique` n'apparait que si la relation est chargee et non nulle,
        // comme dans l'ancien back-end.
        $boutique = $produit->relationLoaded('boutique') ? $produit->boutique : null;

        if ($boutique !== null) {
            $donnees['boutique_nom'] = $boutique->nom;
            $donnees['boutique']     = [
                'id'  => (int) $boutique->id,
                'nom' => $boutique->nom,
            ];

            if ($detail) {
                $donnees['vendeur_id'] = (int) $boutique->vendeur_id;
            }
        }

        $categorie = $produit->relationLoaded('categorie') ? $produit->categorie : null;

        if ($categorie !== null) {
            $donnees['categorie_nom'] = $categorie->nom;
            $donnees['categorie']     = $categorie->nom;
        }

        $donnees['categories'] = $produit->relationLoaded('categories')
            ? $produit->categories
                ->map(static fn ($c) => ['id' => (int) $c->id, 'nom' => $c->nom])
                ->values()
                ->all()
            : [];

        return $donnees;
    }

    /**
     * @param  iterable<Produit> $produits
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $produits, bool $detail = false): array
    {
        return Collection::make($produits)
            ->map(static fn (Produit $p) => self::make($p, $detail))
            ->values()
            ->all();
    }
}
