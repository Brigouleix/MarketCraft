<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BoutiqueResource;
use App\Http\Resources\ProduitResource;
use App\Models\Boutique;
use App\Models\Produit;
use App\Services\ActivityLogger;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoutiqueController extends Controller
{
    /** Nombre de produits joints au detail d'une boutique. */
    private const PRODUITS_PAR_BOUTIQUE = 12;

    public function __construct(private readonly ActivityLogger $journal)
    {
    }

    // ------------------------------------------------------------------
    // GET /boutiques
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = max(1, min(50, (int) $request->query('limit', 20)));

        // Une boutique sans produit actif n'apparait pas au catalogue :
        // une vitrine vide degrade la page d'accueil.
        $base = Boutique::query()
            ->active()
            ->whereHas('produits', fn ($q) => $q->where('est_actif', 1));

        $total = (clone $base)->count();

        $boutiques = $base
            ->with('vendeur:id,nom,prenom')
            ->withCount(['produits as nb_produits' => fn ($q) => $q->where('est_actif', 1)])
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get();

        return $this->pagine(BoutiqueResource::collection($boutiques), $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // GET /boutiques/me
    // ------------------------------------------------------------------

    /**
     * Boutique du vendeur connecte.
     *
     * Renvoie l'enveloppe sans cle `data` lorsque le vendeur n'en a pas
     * encore : c'est la forme historique, et le front la lit comme une
     * absence de boutique pour proposer l'ecran de creation.
     */
    public function me(Request $request): JsonResponse
    {
        $boutique = Boutique::query()
            ->with('vendeur:id,nom,prenom,email')
            ->where('vendeur_id', $request->user()->id)
            ->first();

        return $this->ok(
            $boutique !== null ? BoutiqueResource::make($boutique, avecEmail: true) : null
        );
    }

    // ------------------------------------------------------------------
    // GET /boutiques/:id
    // ------------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $boutique = Boutique::query()
            ->active()
            ->with('vendeur:id,nom,prenom,email')
            ->find($id);

        if ($boutique === null) {
            return $this->nonTrouve('Boutique not found.');
        }

        $produits = Produit::query()
            ->actif()
            ->where('boutique_id', $boutique->id)
            ->with(['boutique:id,nom,vendeur_id', 'categorie:id,nom', 'categories:id,nom'])
            ->orderByDesc('created_at')
            ->limit(self::PRODUITS_PAR_BOUTIQUE)
            ->get();

        $donnees = BoutiqueResource::make($boutique, avecEmail: true);
        $donnees['produits'] = ProduitResource::collection($produits);

        return $this->ok($donnees);
    }

    // ------------------------------------------------------------------
    // POST /boutiques
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom'          => ['required', 'string', 'min:3', 'max:150'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'logo_url'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'banniere_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // Regle de gestion : au plus une boutique par vendeur. Le controle
        // est double, ici pour le message clair et en base par une cle
        // unique, seule a resister a deux envois simultanes.
        if (Boutique::query()->where('vendeur_id', $user->id)->exists()) {
            return $this->echec('Vous possedez deja une boutique.', 409);
        }

        $boutique = DB::transaction(function () use ($donnees, $user) {
            $boutique = Boutique::create([
                'vendeur_id'   => (int) $user->id,
                'nom'          => trim($donnees['nom']),
                'slug'         => Slug::unique($donnees['nom'], 'boutiques'),
                'description'  => $donnees['description'] ?? null,
                'logo_url'     => $donnees['logo_url'] ?? null,
                'banniere_url' => $donnees['banniere_url'] ?? null,
                'est_active'   => 1,
            ]);

            // Ouvrir une boutique fait de son titulaire un vendeur. Sans
            // cette promotion, l'utilisateur ne pourrait pas y deposer de
            // produit : POST /products exige le role vendeur.
            if ($user->role === 'client') {
                $user->role = 'vendeur';
                $user->save();
            }

            return $boutique;
        });

        $this->journal->info(
            'boutique_creee',
            "Boutique #{$boutique->id} creee",
            ['boutique_id' => (int) $boutique->id],
            (int) $user->id
        );

        $boutique->load('vendeur:id,nom,prenom,email');

        return $this->cree(BoutiqueResource::make($boutique, avecEmail: true), 'Boutique created.');
    }

    // ------------------------------------------------------------------
    // PUT /boutiques/:id
    // ------------------------------------------------------------------

    public function update(Request $request, int $id): JsonResponse
    {
        $boutique = Boutique::query()->find($id);

        if ($boutique === null) {
            return $this->nonTrouve('Boutique not found.');
        }

        if (! $this->peutModifier($request, $boutique)) {
            return $this->interdit('Forbidden. You do not own this boutique.');
        }

        $donnees = $request->validate([
            'nom'          => ['sometimes', 'string', 'min:3', 'max:150'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'logo_url'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'banniere_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        foreach (['description', 'logo_url', 'banniere_url'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $boutique->{$champ} = $donnees[$champ];
            }
        }

        if (array_key_exists('nom', $donnees)) {
            $boutique->nom  = trim($donnees['nom']);
            $boutique->slug = Slug::unique($donnees['nom'], 'boutiques', (int) $boutique->id);
        }

        $boutique->save();
        $boutique->load('vendeur:id,nom,prenom,email');

        return $this->ok(
            BoutiqueResource::make($boutique, avecEmail: true),
            'Boutique updated successfully.'
        );
    }

    // ------------------------------------------------------------------
    // DELETE /boutiques/:id
    // ------------------------------------------------------------------

    public function destroy(Request $request, int $id): JsonResponse
    {
        $boutique = Boutique::query()->find($id);

        if ($boutique === null) {
            return $this->nonTrouve('Boutique not found.');
        }

        if (! $this->peutModifier($request, $boutique)) {
            return $this->interdit('Forbidden. You do not own this boutique.');
        }

        // Desactivation : les produits, commandes et paiements rattaches
        // doivent rester consultables.
        $boutique->est_active = 0;
        $boutique->save();

        $this->journal->avertissement(
            'boutique_supprimee',
            "Boutique #{$boutique->id} desactivee",
            ['boutique_id' => (int) $boutique->id],
            (int) $request->user()->id
        );

        return $this->ok(null, 'Boutique deleted successfully.');
    }

    private function peutModifier(Request $request, Boutique $boutique): bool
    {
        $user = $request->user();

        return $user->estAdmin() || (int) $boutique->vendeur_id === (int) $user->id;
    }
}
