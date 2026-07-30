<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProduitResource;
use App\Models\Produit;
use App\Services\RechercheIaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche produits — en langage naturel (IA) ou par mots-cles simples.
 *
 * Routes :
 *   POST /search/ai  interprete la requete via un modele de langage
 *   GET  /search     recherche directe (parametre ?q=)
 *
 * L'interpretation IA vit dans RechercheIaService ; ce controleur ne fait
 * que valider l'entree, lancer la recherche en base sur les mots-cles obtenus
 * et serialiser les produits via ProduitResource — la meme forme que le reste
 * de l'API, pour que le front reutilise ses ProductCard sans transformation.
 */
class SearchController extends Controller
{
    /** Plafond de resultats : une recherche n'est pas un listing pagine. */
    private const MAX_RESULTS = 20;

    public function __construct(private readonly RechercheIaService $ia)
    {
    }

    // ------------------------------------------------------------------
    // POST /search/ai
    // ------------------------------------------------------------------

    public function ai(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'query' => ['required', 'string', 'max:500'],
        ]);

        $query = trim($donnees['query']);

        if ($query === '') {
            return $this->echec('Le champ « query » est obligatoire.', 422);
        }

        $interpretation = $this->ia->interpreter($query);

        $produits = $this->chercher(
            $interpretation['keywords'],
            $interpretation['prix_min'],
            $interpretation['prix_max'],
        );

        return $this->ok([
            'ia_active'  => $interpretation['ia_active'],
            'ia_erreur'  => $interpretation['ia_erreur'],
            'ai_message' => $interpretation['message'],
            'keywords'   => $interpretation['keywords'],
            'products'   => $produits,
            'total'      => count($produits),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /search?q=
    // ------------------------------------------------------------------

    public function simple(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return $this->echec('Le parametre « q » est obligatoire.', 422);
        }

        if (mb_strlen($q) > 500) {
            return $this->echec('La requete ne doit pas depasser 500 caracteres.', 422);
        }

        // Recherche directe : le texte saisi sert de mot-cle unique, ce qui
        // conserve les expressions (« table basse ») que la tokenisation
        // couperait. Pas d'appel IA ici, c'est le mode rapide.
        $produits = $this->chercher([$q], null, null);

        return $this->ok([
            'keywords' => [$q],
            'products' => $produits,
            'total'    => count($produits),
        ]);
    }

    // ------------------------------------------------------------------
    // Recherche en base
    // ------------------------------------------------------------------

    /**
     * @param  string[] $keywords
     * @return array<int, array<string, mixed>>
     */
    private function chercher(array $keywords, ?float $prixMin, ?float $prixMax): array
    {
        $keywords = array_values(array_filter(
            array_map(static fn ($k) => trim((string) $k), $keywords),
            static fn (string $k) => $k !== ''
        ));

        if ($keywords === []) {
            return [];
        }

        $produits = Produit::query()
            ->actif()
            ->with(['boutique:id,nom,vendeur_id', 'categorie:id,nom', 'categories:id,nom'])
            // Un produit matche si au moins un mot-cle apparait dans son nom,
            // sa description ou ses tags (combinaison en OU).
            ->where(function (Builder $q) use ($keywords) {
                foreach ($keywords as $mot) {
                    $motif = '%' . addcslashes($mot, '%_\\') . '%';

                    $q->orWhere('produits.nom', 'LIKE', $motif)
                      ->orWhere('produits.description', 'LIKE', $motif)
                      // `tags` est une colonne JSON : le LIKE porte sur sa
                      // representation texte, suffisant pour un mot simple.
                      ->orWhere('produits.tags', 'LIKE', $motif);
                }
            })
            ->when($prixMin !== null, fn (Builder $q) => $q->where('produits.prix', '>=', $prixMin))
            ->when($prixMax !== null, fn (Builder $q) => $q->where('produits.prix', '<=', $prixMax))
            // Une suggestion en rupture n'apporte rien a l'acheteur.
            ->where('produits.stock', '>', 0)
            ->orderByDesc('produits.created_at')
            ->orderByDesc('produits.id')
            ->limit(self::MAX_RESULTS)
            ->get();

        return ProduitResource::collection($produits);
    }
}
