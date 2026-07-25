<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProduitResource;
use App\Models\Boutique;
use App\Models\Produit;
use App\Services\ActivityLogger;
use App\Services\ProduitQuery;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProduitController extends Controller
{
    public function __construct(
        private readonly ProduitQuery $filtres,
        private readonly ActivityLogger $journal,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /products
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        [$query, $page, $limit] = $this->filtres->depuisRequete($request);

        // Le comptage porte sur une copie de la requete : appeler count()
        // sur l'originale detruirait ses tris et ses relations chargees.
        $total = (clone $query)->count();

        $produits = $query
            ->forPage($page, $limit)
            ->get();

        return $this->pagine(ProduitResource::collection($produits), $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // GET /products/:id
    // ------------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $produit = Produit::query()
            ->actif()
            ->with(['boutique:id,nom,vendeur_id', 'categorie:id,nom', 'categories:id,nom'])
            ->find($id);

        if ($produit === null) {
            return $this->nonTrouve('Product not found.');
        }

        return $this->ok(ProduitResource::make($produit, detail: true));
    }

    // ------------------------------------------------------------------
    // POST /products   (vendeur ou admin)
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom'             => ['required', 'string', 'min:3', 'max:200'],
            'prix'            => ['required', 'numeric', 'min:0'],
            'boutique_id'     => ['required', 'integer'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'stock'           => ['sometimes', 'integer', 'min:0'],
            'images'          => ['sometimes', 'nullable', 'array'],
            'images.*'        => ['string', 'max:500'],
            'tags'            => ['sometimes', 'nullable', 'array'],
            'categorie_id'    => ['sometimes', 'nullable', 'integer'],
            'categorie_ids'   => ['sometimes', 'array'],
            'categorie_ids.*' => ['integer'],
            'est_fait_main'   => ['sometimes', 'boolean'],
        ]);

        $boutique = Boutique::query()->find((int) $donnees['boutique_id']);

        if ($boutique === null) {
            return $this->nonTrouve('Boutique not found.');
        }

        $user = $request->user();

        // Un vendeur ne depose que dans sa propre boutique. Le role seul ne
        // suffit pas : sans ce controle, tout vendeur publierait chez les
        // autres.
        if (! $user->estAdmin() && (int) $boutique->vendeur_id !== (int) $user->id) {
            return $this->interdit('Forbidden. This boutique does not belong to you.');
        }

        $categorieIds = $this->normaliserCategories($donnees);

        $produit = DB::transaction(function () use ($donnees, $categorieIds) {
            $produit = Produit::create([
                'boutique_id'   => (int) $donnees['boutique_id'],
                'categorie_id'  => $categorieIds[0] ?? null,
                'nom'           => trim($donnees['nom']),
                'slug'          => Slug::unique($donnees['nom'], 'produits'),
                'description'   => $donnees['description'] ?? null,
                'prix'          => (float) $donnees['prix'],
                'stock'         => (int) ($donnees['stock'] ?? 0),
                'images'        => $donnees['images'] ?? null,
                'tags'          => $donnees['tags'] ?? null,
                'est_actif'     => 1,
                'est_fait_main' => (int) ($donnees['est_fait_main'] ?? 1),
            ]);

            $produit->categories()->sync($categorieIds);

            return $produit;
        });

        $this->journal->info(
            'produit_cree',
            "Produit #{$produit->id} cree",
            ['produit_id' => (int) $produit->id, 'boutique_id' => (int) $boutique->id],
            (int) $user->id
        );

        return $this->cree($this->recharger($produit), 'Product created.');
    }

    // ------------------------------------------------------------------
    // PUT /products/:id   (proprietaire ou admin)
    // ------------------------------------------------------------------

    public function update(Request $request, int $id): JsonResponse
    {
        $produit = Produit::query()->with('boutique')->find($id);

        if ($produit === null) {
            return $this->nonTrouve('Product not found.');
        }

        if (! $this->peutModifier($request, $produit)) {
            return $this->interdit('Forbidden. You do not own this product.');
        }

        $donnees = $request->validate([
            'nom'             => ['sometimes', 'string', 'min:3', 'max:200'],
            'prix'            => ['sometimes', 'numeric', 'min:0'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'stock'           => ['sometimes', 'integer', 'min:0'],
            'images'          => ['sometimes', 'nullable', 'array'],
            'images.*'        => ['string', 'max:500'],
            'tags'            => ['sometimes', 'nullable', 'array'],
            'categorie_id'    => ['sometimes', 'nullable', 'integer'],
            'categorie_ids'   => ['sometimes', 'array'],
            'categorie_ids.*' => ['integer'],
            'est_actif'       => ['sometimes', 'boolean'],
            'est_fait_main'   => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($produit, $donnees, $request) {
            foreach (['description', 'stock', 'tags', 'est_actif', 'est_fait_main'] as $champ) {
                if (array_key_exists($champ, $donnees)) {
                    $produit->{$champ} = $donnees[$champ];
                }
            }

            if (array_key_exists('prix', $donnees)) {
                $produit->prix = (float) $donnees['prix'];
            }

            if (array_key_exists('images', $donnees)) {
                $produit->images = $donnees['images'];
            }

            // Le slug suit le nom : une fiche renommee doit rester
            // accessible par une URL coherente.
            if (array_key_exists('nom', $donnees)) {
                $produit->nom  = trim($donnees['nom']);
                $produit->slug = Slug::unique($donnees['nom'], 'produits', (int) $produit->id);
            }

            if ($request->has('categorie_ids') || $request->has('categorie_id')) {
                $ids = $this->normaliserCategories($donnees);
                $produit->categories()->sync($ids);
                $produit->categorie_id = $ids[0] ?? null;
            }

            $produit->save();
        });

        $this->journal->info(
            'produit_modifie',
            "Produit #{$produit->id} modifie",
            ['produit_id' => (int) $produit->id],
            (int) $request->user()->id
        );

        return $this->ok($this->recharger($produit), 'Product updated successfully.');
    }

    // ------------------------------------------------------------------
    // DELETE /products/:id   (proprietaire ou admin)
    // ------------------------------------------------------------------

    public function destroy(Request $request, int $id): JsonResponse
    {
        $produit = Produit::query()->with('boutique')->find($id);

        if ($produit === null) {
            return $this->nonTrouve('Product not found.');
        }

        if (! $this->peutModifier($request, $produit)) {
            return $this->interdit('Forbidden. You do not own this product.');
        }

        // Desactivation, pas suppression : les lignes de commande portent
        // une contrainte RESTRICT sur produit_id, et un historique d'achat
        // ne doit pas disparaitre parce qu'un vendeur retire sa fiche.
        $produit->est_actif = 0;
        $produit->save();

        $this->journal->avertissement(
            'produit_supprime',
            "Produit #{$produit->id} desactive",
            ['produit_id' => (int) $produit->id],
            (int) $request->user()->id
        );

        return $this->ok(null, 'Product deleted successfully.');
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    private function peutModifier(Request $request, Produit $produit): bool
    {
        $user = $request->user();

        return $user->estAdmin()
            || (int) ($produit->boutique?->vendeur_id ?? 0) === (int) $user->id;
    }

    /**
     * @param  array<string, mixed> $donnees
     * @return int[]
     */
    private function normaliserCategories(array $donnees): array
    {
        // `categorie_ids` prime sur `categorie_id` : le front envoie l'un
        // ou l'autre selon l'ecran. La premiere de la liste devient la
        // categorie principale.
        $ids = [];

        if (isset($donnees['categorie_ids']) && is_array($donnees['categorie_ids'])) {
            $ids = $donnees['categorie_ids'];
        } elseif (! empty($donnees['categorie_id'])) {
            $ids = [$donnees['categorie_id']];
        }

        $ids = array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0);

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private function recharger(Produit $produit): array
    {
        $frais = Produit::query()
            ->with(['boutique:id,nom,vendeur_id', 'categorie:id,nom', 'categories:id,nom'])
            ->find($produit->id);

        return ProduitResource::make($frais, detail: true);
    }
}
