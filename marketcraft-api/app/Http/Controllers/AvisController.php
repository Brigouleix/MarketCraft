<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AvisResource;
use App\Models\Avis;
use App\Models\Commande;
use App\Models\Produit;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvisController extends Controller
{
    public function __construct(private readonly ActivityLogger $journal)
    {
    }

    // ------------------------------------------------------------------
    // GET /products/:id/avis   (public)
    // ------------------------------------------------------------------

    /**
     * Liste des avis d'un produit.
     *
     * La reponse porte une cle `stats` en plus de l'enveloppe paginee :
     * moyenne, total et repartition par note. C'est une quatrieme forme,
     * heritee de l'existant, que le front utilise pour dessiner
     * l'histogramme des etoiles.
     */
    public function indexByProduct(Request $request, int $id): JsonResponse
    {
        if (! Produit::query()->actif()->whereKey($id)->exists()) {
            return $this->nonTrouve('Product not found.');
        }

        $page  = max(1, (int) $request->query('page', 1));
        $limit = max(1, min(50, (int) $request->query('limit', 20)));

        $total = Avis::query()->where('produit_id', $id)->count();

        $avis = Avis::query()
            ->where('produit_id', $id)
            ->with('utilisateur:id,nom,prenom,avatar_url')
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get();

        return $this->pagine(
            AvisResource::collection($avis),
            $total,
            $page,
            $limit,
            ['stats' => $this->statistiques($id)]
        );
    }

    // ------------------------------------------------------------------
    // POST /products/:id/avis
    // ------------------------------------------------------------------

    public function store(Request $request, int $id): JsonResponse
    {
        $produit = Produit::query()->actif()->find($id);

        if ($produit === null) {
            return $this->nonTrouve('Product not found.');
        }

        $donnees = $request->validate([
            'note'        => ['required', 'integer', 'between:1,5'],
            'titre'       => ['sometimes', 'nullable', 'string', 'max:150'],
            'commentaire' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();

        // Achat verifie : seul un acheteur du produit peut le noter. Sans
        // cette condition, la note moyenne se pilote depuis n'importe quel
        // compte cree pour l'occasion.
        if (! $this->aAchete((int) $user->id, $id)) {
            return $this->interdit(
                'Vous ne pouvez laisser un avis que sur un produit que vous avez commande.'
            );
        }

        if (Avis::query()->where('produit_id', $id)->where('utilisateur_id', $user->id)->exists()) {
            return $this->echec('You have already reviewed this product.', 409);
        }

        try {
            $avis = Avis::create([
                'produit_id'     => $id,
                'utilisateur_id' => (int) $user->id,
                'note'           => (int) $donnees['note'],
                // strip_tags plutot qu'echappement HTML : le contenu est
                // rendu par React, qui echappe deja a l'affichage. Stocker
                // du &quot; rendrait le texte illisible en base.
                'titre'          => isset($donnees['titre'])
                    ? strip_tags(trim((string) $donnees['titre'])) : null,
                'commentaire'    => isset($donnees['commentaire'])
                    ? strip_tags(trim((string) $donnees['commentaire'])) : null,
                'est_verifie'    => 1,
            ]);
        } catch (QueryException $e) {
            // Filet pour deux envois simultanes : la cle unique en base
            // tranche la ou le test applicatif ci-dessus laisse passer.
            if ($this->estViolationUnicite($e)) {
                return $this->echec('You have already reviewed this product.', 409);
            }

            throw $e;
        }

        $this->journal->info(
            'avis_publie',
            "Avis sur le produit #{$id}",
            ['produit_id' => $id, 'note' => (int) $donnees['note']],
            (int) $user->id
        );

        $avis->load('utilisateur:id,nom,prenom,avatar_url');

        return $this->cree(AvisResource::make($avis), 'Review posted.');
    }

    // ------------------------------------------------------------------
    // DELETE /avis/:id
    // ------------------------------------------------------------------

    public function destroy(Request $request, int $id): JsonResponse
    {
        $avis = Avis::query()->find($id);

        if ($avis === null) {
            return $this->nonTrouve('Review not found.');
        }

        $user = $request->user();

        if (! $user->estAdmin() && (int) $avis->utilisateur_id !== (int) $user->id) {
            return $this->interdit('Forbidden. You cannot delete this review.');
        }

        // Suppression reelle, et non desactivation : les triggers SQL
        // recalculent alors la note du produit puis celle de la boutique.
        $avis->delete();

        $this->journal->avertissement(
            'avis_supprime',
            "Avis #{$id} supprime",
            ['avis_id' => $id, 'par_admin' => $user->estAdmin()],
            (int) $user->id
        );

        return $this->ok(null, 'Review deleted successfully.');
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    /**
     * @return array{moyenne: float|null, total: int, repartition: array<int, int>}
     */
    private function statistiques(int $produitId): array
    {
        $lignes = Avis::query()
            ->where('produit_id', $produitId)
            ->selectRaw('note, COUNT(*) as total')
            ->groupBy('note')
            ->pluck('total', 'note');

        $total = (int) $lignes->sum();

        // Somme ponderee : sum(note x effectif), d'ou la moyenne.
        $somme = 0;
        foreach ($lignes as $note => $effectif) {
            $somme += (int) $note * (int) $effectif;
        }

        return [
            'moyenne'     => $total > 0 ? round($somme / $total, 2) : null,
            'total'       => $total,
            'repartition' => [
                5 => (int) ($lignes[5] ?? 0),
                4 => (int) ($lignes[4] ?? 0),
                3 => (int) ($lignes[3] ?? 0),
                2 => (int) ($lignes[2] ?? 0),
                1 => (int) ($lignes[1] ?? 0),
            ],
        ];
    }

    /** Une commande annulee ne vaut pas achat. */
    private function aAchete(int $utilisateurId, int $produitId): bool
    {
        return Commande::query()
            ->where('utilisateur_id', $utilisateurId)
            ->where('statut', '!=', 'annulee')
            ->whereHas('lignes', fn ($q) => $q->where('produit_id', $produitId))
            ->exists();
    }

    private function estViolationUnicite(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            || str_contains($e->getMessage(), 'uq_avis_produit_user');
    }
}
