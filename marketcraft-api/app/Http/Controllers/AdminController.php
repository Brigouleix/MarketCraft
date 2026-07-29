<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\JournalActivite;
use App\Models\Produit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Back-office d'administration.
 *
 * Alimente l'ecran React `AdminPage` : supervision (stats), moderation des
 * utilisateurs, des boutiques et des avis, et gestion des categories. Toutes
 * les routes sont derriere `role:admin` (voir routes/api.php).
 *
 * Deux formes imposees par le front :
 *   - GET /admin/categories renvoie { categories, racines } ;
 *   - DELETE /admin/categories/{id} repond 409 avec details { principale,
 *     liaisons } quand des produits sont rattaches, le client confirmant
 *     via ?force=1.
 */
class AdminController extends Controller
{
    public function __construct(private readonly ActivityLogger $journal)
    {
    }

    // ------------------------------------------------------------------
    // GET /admin/stats
    // ------------------------------------------------------------------

    public function stats(): JsonResponse
    {
        // Le chiffre d'affaires ignore les commandes annulees.
        $caGlobal = (float) Commande::query()
            ->where('statut', '!=', 'annulee')
            ->sum('montant_total');

        return $this->ok([
            'nb_utilisateurs' => User::query()->count(),
            'nb_vendeurs'     => User::query()->where('role', 'vendeur')->count(),
            'nb_clients'      => User::query()->where('role', 'client')->count(),
            'nb_boutiques'    => Boutique::query()->count(),
            'nb_produits'     => Produit::query()->where('est_actif', 1)->count(),
            'nb_commandes'    => Commande::query()->count(),
            'nb_avis'         => Avis::query()->count(),
            // Chaine a deux decimales, comme toutes les valeurs monetaires du contrat.
            'ca_global'       => number_format($caGlobal, 2, '.', ''),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /admin/users  ·  PUT /admin/users/{id}/toggle
    // ------------------------------------------------------------------

    public function users(): JsonResponse
    {
        $users = User::query()
            ->orderByDesc('id')
            ->get(['id', 'prenom', 'nom', 'email', 'role', 'est_actif']);

        return $this->ok($users);
    }

    public function toggleUser(int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if ($user === null) {
            return $this->nonTrouve('Utilisateur introuvable.');
        }

        // On ne desactive pas un administrateur depuis cet ecran : c'est le
        // moyen le plus simple de se verrouiller hors de la plateforme.
        if ($user->role === 'admin') {
            return $this->interdit("Un compte administrateur ne peut pas etre desactive ici.");
        }

        $user->est_actif = (int) $user->est_actif === 1 ? 0 : 1;
        $user->save();

        $this->journal->info(
            'admin_toggle_utilisateur',
            $user->est_actif ? 'Compte reactive' : 'Compte desactive',
            ['cible' => $id]
        );

        return $this->ok(['id' => (int) $user->id, 'est_actif' => (int) $user->est_actif], 'Statut mis a jour.');
    }

    // ------------------------------------------------------------------
    // GET /admin/boutiques  ·  PUT /admin/boutiques/{id}/toggle
    // ------------------------------------------------------------------

    public function boutiques(): JsonResponse
    {
        $boutiques = Boutique::query()
            ->with('vendeur:id,prenom,nom')
            ->withCount('produits')
            ->orderByDesc('id')
            ->get();

        $data = $boutiques->map(static fn (Boutique $b): array => [
            'id'             => (int) $b->id,
            'nom'            => $b->nom,
            'vendeur_prenom' => $b->vendeur?->prenom,
            'vendeur_nom'    => $b->vendeur?->nom,
            'nb_produits'    => (int) $b->produits_count,
            'note_moyenne'   => $b->note_moyenne,
            'est_active'     => (int) $b->est_active,
        ]);

        return $this->ok($data);
    }

    public function toggleBoutique(int $id): JsonResponse
    {
        $boutique = Boutique::query()->find($id);

        if ($boutique === null) {
            return $this->nonTrouve('Boutique introuvable.');
        }

        $boutique->est_active = (int) $boutique->est_active === 1 ? 0 : 1;
        $boutique->save();

        $this->journal->info(
            'admin_toggle_boutique',
            $boutique->est_active ? 'Boutique reactivee' : 'Boutique suspendue',
            ['cible' => $id]
        );

        return $this->ok(['id' => (int) $boutique->id, 'est_active' => (int) $boutique->est_active], 'Statut mis a jour.');
    }

    // ------------------------------------------------------------------
    // GET /admin/avis  ·  DELETE /admin/avis/{id}
    // ------------------------------------------------------------------

    public function avis(): JsonResponse
    {
        $avis = Avis::query()
            ->with(['utilisateur:id,prenom,nom', 'produit:id,nom'])
            ->orderByDesc('id')
            ->get();

        $data = $avis->map(static fn (Avis $a): array => [
            'id'            => (int) $a->id,
            'note'          => (int) $a->note,
            'titre'         => $a->titre,
            'commentaire'   => $a->commentaire,
            'auteur_prenom' => $a->utilisateur?->prenom,
            'auteur_nom'    => $a->utilisateur?->nom,
            'produit_nom'   => $a->produit?->nom,
        ]);

        return $this->ok($data);
    }

    public function deleteAvis(int $id): JsonResponse
    {
        $avis = Avis::query()->find($id);

        if ($avis === null) {
            return $this->nonTrouve('Avis introuvable.');
        }

        // La suppression declenche les triggers qui recalculent
        // produits.note_moyenne puis boutiques.note_moyenne.
        $avis->delete();

        $this->journal->avertissement(
            'admin_suppression_avis',
            'Avis supprime par un administrateur',
            ['cible' => $id]
        );

        return $this->ok(null, 'Avis supprime.');
    }

    // ------------------------------------------------------------------
    // GET /admin/categories
    // ------------------------------------------------------------------

    public function categories(): JsonResponse
    {
        $cats = Categorie::query()
            ->with('parent:id,nom')
            ->withCount('produits')
            ->orderBy('parent_id')
            ->orderBy('ordre')
            ->get();

        $categories = $cats->map(static fn (Categorie $c): array => [
            'id'          => (int) $c->id,
            'nom'         => $c->nom,
            'slug'        => $c->slug,
            'parent_nom'  => $c->parent?->nom,
            'nb_produits' => (int) $c->produits_count,
        ])->values();

        $racines = $cats->whereNull('parent_id')
            ->map(static fn (Categorie $c): array => ['id' => (int) $c->id, 'nom' => $c->nom])
            ->values();

        return $this->ok([
            'categories' => $categories,
            'racines'    => $racines,
        ]);
    }

    // ------------------------------------------------------------------
    // POST /admin/categories
    // ------------------------------------------------------------------

    public function createCategorie(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom'         => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'parent_id'   => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
        ]);

        $categorie = Categorie::create([
            'nom'         => trim($donnees['nom']),
            'slug'        => $this->slugUnique($donnees['nom']),
            'description' => $donnees['description'] ?? null,
            'parent_id'   => $donnees['parent_id'] ?? null,
            'ordre'       => (int) (Categorie::query()->max('ordre') ?? 0) + 1,
        ]);

        $this->journal->info('admin_creation_categorie', "Categorie creee : {$categorie->nom}", ['id' => (int) $categorie->id]);

        return $this->cree(
            ['id' => (int) $categorie->id, 'nom' => $categorie->nom, 'slug' => $categorie->slug],
            'Categorie creee.'
        );
    }

    // ------------------------------------------------------------------
    // PUT /admin/categories/{id}
    // ------------------------------------------------------------------

    public function updateCategorie(Request $request, int $id): JsonResponse
    {
        $categorie = Categorie::query()->find($id);

        if ($categorie === null) {
            return $this->nonTrouve('Categorie introuvable.');
        }

        $donnees = $request->validate([
            'nom'         => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'parent_id'   => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
        ]);

        if (array_key_exists('nom', $donnees)) {
            $categorie->nom = trim($donnees['nom']);
        }

        if (array_key_exists('description', $donnees)) {
            $categorie->description = $donnees['description'];
        }

        if (array_key_exists('parent_id', $donnees)) {
            // Une categorie ne peut pas etre son propre parent.
            if ($donnees['parent_id'] !== null && (int) $donnees['parent_id'] === (int) $categorie->id) {
                return $this->echec('Une categorie ne peut pas etre son propre parent.', 422);
            }
            $categorie->parent_id = $donnees['parent_id'];
        }

        $categorie->save();

        $this->journal->info('admin_modif_categorie', "Categorie modifiee : {$categorie->nom}", ['id' => (int) $categorie->id]);

        return $this->ok(['id' => (int) $categorie->id, 'nom' => $categorie->nom, 'slug' => $categorie->slug], 'Categorie mise a jour.');
    }

    // ------------------------------------------------------------------
    // DELETE /admin/categories/{id}  (?force=1)
    // ------------------------------------------------------------------

    public function deleteCategorie(Request $request, int $id): JsonResponse
    {
        $categorie = Categorie::query()->find($id);

        if ($categorie === null) {
            return $this->nonTrouve('Categorie introuvable.');
        }

        $principale = Produit::query()->where('categorie_id', $id)->count();
        $liaisons   = DB::table('produit_categorie')->where('categorie_id', $id)->count();

        // Sans ?force=1, on refuse et on renvoie l'impact pour que le front
        // demande confirmation.
        if (($principale + $liaisons) > 0 && ! $request->boolean('force')) {
            return $this->echec(
                'Cette categorie est rattachee a des produits.',
                409,
                ['principale' => $principale, 'liaisons' => $liaisons]
            );
        }

        DB::transaction(function () use ($id, $categorie): void {
            // Les produits ne sont pas supprimes : ils perdent la categorie.
            DB::table('produit_categorie')->where('categorie_id', $id)->delete();
            Produit::query()->where('categorie_id', $id)->update(['categorie_id' => null]);
            // Les sous-categories remontent a la racine plutot que d'etre orphelines.
            Categorie::query()->where('parent_id', $id)->update(['parent_id' => null]);
            $categorie->delete();
        });

        $this->journal->avertissement('admin_suppression_categorie', "Categorie supprimee : {$categorie->nom}", ['id' => $id]);

        return $this->ok(null, 'Categorie supprimee.');
    }

    // ------------------------------------------------------------------
    // GET /admin/logs  (journal d'activite, pagine)
    // ------------------------------------------------------------------

    public function logs(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 25)));

        $query = JournalActivite::query()
            ->with('utilisateur:id,prenom,nom')
            ->orderByDesc('id');

        // Filtre par niveau (info / avertissement / erreur / critique).
        $niveau = $request->query('niveau');
        if (is_string($niveau) && in_array($niveau, JournalActivite::NIVEAUX, true)) {
            $query->where('niveau', $niveau);
        }

        // Filtre texte sur l'action (ex. « connexion », « admin_ »).
        $action = $request->query('action');
        if (is_string($action) && trim($action) !== '') {
            $query->where('action', 'like', '%' . trim($action) . '%');
        }

        $total = (clone $query)->count();

        $items = $query->forPage($page, $limit)->get()->map(static fn (JournalActivite $l): array => [
            'id'          => (int) $l->id,
            'created_at'  => optional($l->created_at)->format('Y-m-d H:i:s'),
            'action'      => $l->action,
            'niveau'      => $l->niveau,
            'message'     => $l->message,
            'utilisateur' => $l->utilisateur ? trim("{$l->utilisateur->prenom} {$l->utilisateur->nom}") : null,
            'ip'          => $l->ip,
            'contexte'    => $l->contexte,
        ])->all();

        return $this->pagine($items, $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    /** Slug sans accent, unique en base : « Céramique » -> « ceramique », puis « ceramique-2 »… */
    private function slugUnique(string $nom): string
    {
        $base = Str::slug($nom);

        if ($base === '') {
            $base = 'categorie';
        }

        $slug = $base;
        $i = 2;

        while (Categorie::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
