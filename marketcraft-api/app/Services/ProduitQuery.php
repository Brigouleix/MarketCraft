<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Construction de la requete de listing des produits.
 *
 * Regle de combinaison des filtres de categorie, a ne pas inverser :
 *
 *   - a l'interieur de `categorie`, les valeurs se combinent en OU ;
 *   - a l'interieur de `materiau`, idem ;
 *   - entre `categorie` et `materiau`, la combinaison est un ET.
 *
 * « Ceramique » + « Argile » renvoie donc les ceramiques EN argile, pas
 * l'union des deux ensembles. Les deux filtres alimentent les deux onglets
 * du panneau de filtres cote front, et l'utilisateur attend un croisement.
 *
 * Chaque groupe devient une sous-requete EXISTS distincte sur la table de
 * liaison : c'est ce qui produit le ET entre groupes sans dedoublonnage
 * ni GROUP BY.
 */
class ProduitQuery
{
    /**
     * Colonnes de tri autorisees. Toute valeur hors de cette liste retombe
     * sur le defaut : `sort` vient de l'URL et ne doit jamais atteindre le
     * SQL sans filtrage.
     */
    private const TRIS_AUTORISES = [
        'created_at' => 'produits.created_at',
        'prix'       => 'produits.prix',
        'nom'        => 'produits.nom',
        'stock'      => 'produits.stock',
        'note'       => 'produits.note_moyenne',
        'nb_avis'    => 'produits.nombre_avis',
    ];

    /** Tris « metier » envoyes par le front, prioritaires sur sort/order. */
    private const TRIS_METIER = [
        'prix_asc'  => ['prix', 'ASC'],
        'prix_desc' => ['prix', 'DESC'],
        'recent'    => ['created_at', 'DESC'],
        'populaire' => ['nb_avis', 'DESC'],
    ];

    /**
     * @return array{0: Builder, 1: int, 2: int}  [requete, page, limite]
     */
    public function depuisRequete(Request $request): array
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = $this->limite($request);

        $query = Produit::query()
            ->actif()
            ->with(['boutique:id,nom,vendeur_id', 'categorie:id,nom', 'categories:id,nom']);

        $this->appliquerRecherche($query, $request->query('search'));
        $this->appliquerCategories($query, $request->query('categorie'));
        $this->appliquerCategories($query, $request->query('materiau'));
        $this->appliquerBoutique($query, $request->query('boutique_id') ?? $request->query('boutique'));
        $this->appliquerPrix($query, $request->query('prix_min'), $request->query('prix_max'));
        $this->appliquerNote($query, $request->query('note_min'));
        $this->appliquerTri($query, $request);

        return [$query, $page, $limit];
    }

    private function limite(Request $request): int
    {
        $brut = $request->query('per_page') ?? $request->query('limit');

        $limit = $brut !== null && $brut !== ''
            ? (int) $brut
            : (int) config('marketcraft.pagination.default_limit', 20);

        // Plafond : sans lui, `?limit=999999` transforme un listing en
        // deni de service.
        return max(1, min((int) config('marketcraft.pagination.max_limit', 100), $limit));
    }

    private function appliquerRecherche(Builder $query, mixed $search): void
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return;
        }

        // Les jokers saisis par l'utilisateur sont echappes : sans cela,
        // une recherche sur « % » remonterait tout le catalogue.
        $motif = '%' . addcslashes($search, '%_\\') . '%';

        $query->where(function (Builder $q) use ($motif) {
            $q->where('produits.nom', 'LIKE', $motif)
              ->orWhere('produits.description', 'LIKE', $motif);
        });
    }

    /**
     * Un groupe de categories : ids et/ou slugs separes par des virgules,
     * combines en OU. Chaque appel ajoute une clause EXISTS supplementaire,
     * d'ou le ET entre groupes.
     */
    private function appliquerCategories(Builder $query, mixed $valeur): void
    {
        $valeurs = $this->decouper($valeur);

        if ($valeurs === []) {
            return;
        }

        $ids   = [];
        $slugs = [];

        foreach ($valeurs as $v) {
            if (ctype_digit($v)) {
                $ids[] = (int) $v;
            } else {
                $slugs[] = $v;
            }
        }

        $query->whereExists(function ($sous) use ($ids, $slugs) {
            $sous->selectRaw('1')
                ->from('produit_categorie as pc')
                ->join('categories as c', 'c.id', '=', 'pc.categorie_id')
                ->whereColumn('pc.produit_id', 'produits.id')
                ->where(function ($w) use ($ids, $slugs) {
                    if ($ids !== []) {
                        $w->orWhereIn('pc.categorie_id', $ids);
                    }
                    if ($slugs !== []) {
                        $w->orWhereIn('c.slug', $slugs);
                    }
                });
        });
    }

    private function appliquerBoutique(Builder $query, mixed $boutiqueId): void
    {
        if ($boutiqueId === null || $boutiqueId === '' || ! ctype_digit((string) $boutiqueId)) {
            return;
        }

        $query->where('produits.boutique_id', (int) $boutiqueId);
    }

    private function appliquerPrix(Builder $query, mixed $min, mixed $max): void
    {
        if (is_numeric($min)) {
            $query->where('produits.prix', '>=', (float) $min);
        }

        if (is_numeric($max)) {
            $query->where('produits.prix', '<=', (float) $max);
        }
    }

    private function appliquerNote(Builder $query, mixed $noteMin): void
    {
        if (! is_numeric($noteMin) || (float) $noteMin <= 0) {
            return;
        }

        // Lit la colonne maintenue par les triggers, sans recalcul.
        $query->where('produits.note_moyenne', '>=', (float) $noteMin);
    }

    private function appliquerTri(Builder $query, Request $request): void
    {
        $tri = (string) $request->query('tri', '');

        if (isset(self::TRIS_METIER[$tri])) {
            [$sort, $order] = self::TRIS_METIER[$tri];
        } else {
            $sort  = (string) $request->query('sort', 'created_at');
            $order = strtoupper((string) $request->query('order', 'DESC'));
        }

        $colonne   = self::TRIS_AUTORISES[$sort] ?? 'produits.created_at';
        $direction = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $query->orderBy($colonne, $direction);

        // Depart departage stable : sans second critere, deux produits de
        // meme prix peuvent changer d'ordre entre deux pages et l'un
        // apparaitre deux fois, l'autre jamais.
        if ($colonne !== 'produits.id') {
            $query->orderBy('produits.id', 'DESC');
        }
    }

    /**
     * @return string[]
     */
    private function decouper(mixed $valeur): array
    {
        if (! is_string($valeur) || trim($valeur) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $valeur)),
            static fn (string $v) => $v !== ''
        )));
    }
}
