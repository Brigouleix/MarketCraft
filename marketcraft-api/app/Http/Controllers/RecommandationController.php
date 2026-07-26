<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProduitResource;
use App\Models\Produit;
use App\Services\RecommandationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Module IA — recommandation personnalisee (option C du CDC).
 *
 * Trois entrees pour une meme mecanique :
 *
 *   GET  /products/:id/similar          route deja appelee par le front
 *                                       (ProductDetailPage), qui repondait
 *                                       404 faute d'implementation
 *   GET  /products/:id/recommendations  nom retenu au brief, meme reponse
 *   POST /cart/recommendations          a partir du contenu du panier
 *
 * `similar` et `recommendations` sont volontairement identiques : le front
 * actuel appelle la premiere, le brief documente la seconde. Les separer
 * obligerait a modifier le React, ce que le projet s'interdit.
 */
class RecommandationController extends Controller
{
    private const LIMITE_DEFAUT = 4;
    private const LIMITE_MAX = 12;

    public function __construct(private readonly RecommandationService $service)
    {
    }

    // ------------------------------------------------------------------
    // GET /products/:id/similar
    // GET /products/:id/recommendations
    // ------------------------------------------------------------------

    public function pourProduit(Request $request, int $id): JsonResponse
    {
        $produit = Produit::query()
            ->actif()
            ->with(['categorie:id,nom', 'categories:id,nom', 'boutique:id,nom,vendeur_id'])
            ->find($id);

        if ($produit === null) {
            return $this->nonTrouve('Product not found.');
        }

        $resultat = $this->service->pourProduit($produit, $this->limite($request));

        return $this->ok($this->formater($resultat));
    }

    // ------------------------------------------------------------------
    // POST /cart/recommendations
    // ------------------------------------------------------------------

    public function pourPanier(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'produit_ids'   => ['sometimes', 'array'],
            'produit_ids.*' => ['integer'],
            'lignes'        => ['sometimes', 'array'],
            'lignes.*.produit_id' => ['integer'],
        ]);

        // Le front envoie tantot une liste d'identifiants, tantot les
        // lignes du panier : on accepte les deux formes.
        $ids = $donnees['produit_ids']
            ?? array_map(
                static fn (array $l) => (int) ($l['produit_id'] ?? 0),
                $donnees['lignes'] ?? []
            );

        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i) => $i > 0));

        if ($ids === []) {
            return $this->echec('Le panier est vide : aucun produit a analyser.', 422);
        }

        $resultat = $this->service->pourPanier($ids, $this->limite($request));

        return $this->ok($this->formater($resultat));
    }

    // ------------------------------------------------------------------
    // GET /me/recommendations
    // ------------------------------------------------------------------

    /**
     * Suggestions fondees sur l'historique d'achat du client connecte.
     *
     * Repond a la partie « personnalisee » de l'option C : les
     * recommandations different d'un client a l'autre parce qu'elles
     * partent de ce qu'il a reellement commande.
     */
    public function pourHistorique(Request $request): JsonResponse
    {
        $resultat = $this->service->pourHistorique(
            (int) $request->user()->id,
            $this->limite($request)
        );

        return $this->ok($this->formater($resultat));
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    /**
     * `ia_active` distingue une vraie recommandation d'un repli : le front
     * l'affiche sous forme de badge. `source` precise le mode exact, utile
     * en demonstration comme au debogage.
     *
     * @param  array{produits: array<int, Produit>, ia_active: bool, motifs: array<int, string>, source: string} $resultat
     * @return array<string, mixed>
     */
    private function formater(array $resultat): array
    {
        $produits = array_map(
            function (Produit $p) use ($resultat) {
                $donnees = ProduitResource::make($p);
                // Le motif n'existe que si l'IA a repondu.
                $donnees['motif'] = $resultat['motifs'][(int) $p->id] ?? null;

                return $donnees;
            },
            $resultat['produits']
        );

        return [
            'ia_active' => $resultat['ia_active'],
            'source'    => $resultat['source'],
            'products'  => $produits,
            // Doublon assume : ProductDetailPage lit `data.products`,
            // d'autres vues lisent `data.produits`.
            'produits'  => $produits,
        ];
    }

    private function limite(Request $request): int
    {
        $limite = (int) $request->query('limit', self::LIMITE_DEFAUT);

        return max(1, min(self::LIMITE_MAX, $limite));
    }
}
