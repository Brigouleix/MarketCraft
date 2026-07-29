<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tableaux de bord.
 *
 * GET /dashboard/stats     — indicateurs du vendeur connecté (sa boutique).
 * GET /dashboard/acheteur  — statistiques d'achat du client connecté.
 *
 * Tous les montants sont calculés à partir des lignes de commande réelles,
 * en excluant les commandes annulées.
 */
class DashboardController extends Controller
{
    // ------------------------------------------------------------------
    // GET /dashboard/stats  (vendeur / admin)
    // ------------------------------------------------------------------

    public function vendeur(Request $request): JsonResponse
    {
        $user     = $request->user();
        $boutique = Boutique::query()->where('vendeur_id', $user->id)->first();

        if ($boutique === null) {
            return $this->ok([
                'ca'                 => '0.00',
                'nb_commandes'       => 0,
                'note_moyenne'       => '0.00',
                'nb_produits'        => 0,
                'top_produits'       => [],
                'commandes_recentes' => [],
            ]);
        }

        $bid = (int) $boutique->id;

        // Lignes portant sur un produit de la boutique, hors commandes annulées.
        $lignes = fn () => DB::table('lignes_commande as l')
            ->join('produits as p', 'p.id', '=', 'l.produit_id')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('p.boutique_id', $bid)
            ->where('c.statut', '!=', 'annulee');

        $ca          = (float) $lignes()->sum(DB::raw('l.prix_unitaire * l.quantite'));
        $nbCommandes = (int) $lignes()->distinct()->count('c.id');

        $topProduits = $lignes()
            ->select(
                'p.id',
                'p.nom',
                DB::raw('SUM(l.quantite) as nb_vendus'),
                DB::raw('SUM(l.prix_unitaire * l.quantite) as revenu')
            )
            ->groupBy('p.id', 'p.nom')
            ->orderByDesc('nb_vendus')
            ->limit(5)
            ->get()
            ->map(static fn ($r) => [
                'id'        => (int) $r->id,
                'nom'       => $r->nom,
                'nb_vendus' => (int) $r->nb_vendus,
                'revenu'    => (float) $r->revenu,
            ]);

        $recentes = Commande::query()
            ->whereIn('id', function ($q) use ($bid) {
                $q->select('l.commande_id')
                    ->from('lignes_commande as l')
                    ->join('produits as p', 'p.id', '=', 'l.produit_id')
                    ->where('p.boutique_id', $bid);
            })
            ->with('utilisateur:id,prenom,nom')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(static fn (Commande $c) => [
                'id'            => (int) $c->id,
                'client_prenom' => $c->utilisateur?->prenom,
                'client_nom'    => $c->utilisateur?->nom,
                'created_at'    => optional($c->created_at)->format('Y-m-d H:i:s'),
                'montant_total' => $c->montant_total,
                'statut'        => $c->statut,
            ]);

        return $this->ok([
            'ca'                 => number_format($ca, 2, '.', ''),
            'nb_commandes'       => $nbCommandes,
            'note_moyenne'       => $boutique->note_moyenne,
            'nb_produits'        => Produit::query()->where('boutique_id', $bid)->where('est_actif', 1)->count(),
            'top_produits'       => $topProduits,
            'commandes_recentes' => $recentes,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /dashboard/acheteur  (client connecté)
    // ------------------------------------------------------------------

    public function acheteur(Request $request): JsonResponse
    {
        $uid = (int) $request->user()->id;

        $base         = fn () => Commande::query()->where('utilisateur_id', $uid)->where('statut', '!=', 'annulee');
        $totalDepense = (float) $base()->sum('montant_total');
        $nbCommandes  = (int) $base()->count();
        $panierMoyen  = $nbCommandes > 0 ? $totalDepense / $nbCommandes : 0.0;

        $depensesMois = $base()
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"), DB::raw('SUM(montant_total) as total'))
            ->groupBy('mois')
            ->orderBy('mois')
            ->get()
            ->map(static fn ($r) => ['mois' => $r->mois, 'total' => (float) $r->total]);

        $categoriesPref = DB::table('lignes_commande as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->join('produits as p', 'p.id', '=', 'l.produit_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.categorie_id')
            ->where('c.utilisateur_id', $uid)
            ->where('c.statut', '!=', 'annulee')
            ->select('cat.nom as categorie', DB::raw('SUM(l.prix_unitaire * l.quantite) as total_depense'))
            ->groupBy('cat.nom')
            ->orderByDesc('total_depense')
            ->limit(5)
            ->get()
            ->map(static fn ($r) => ['categorie' => $r->categorie, 'total_depense' => (float) $r->total_depense]);

        $commandes = Commande::query()
            ->where('utilisateur_id', $uid)
            ->withCount('lignes')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(static fn (Commande $c) => [
                'id'            => (int) $c->id,
                'created_at'    => optional($c->created_at)->format('Y-m-d H:i:s'),
                'nb_articles'   => (int) $c->lignes_count,
                'montant_total' => $c->montant_total,
                'statut'        => $c->statut,
            ]);

        return $this->ok([
            'total_depense'   => number_format($totalDepense, 2, '.', ''),
            'nb_commandes'    => $nbCommandes,
            'panier_moyen'    => number_format($panierMoyen, 2, '.', ''),
            'nb_avis'         => Avis::query()->where('utilisateur_id', $uid)->count(),
            'depenses_mois'   => $depensesMois,
            'categories_pref' => $categoriesPref,
            'commandes'       => $commandes,
        ]);
    }
}
