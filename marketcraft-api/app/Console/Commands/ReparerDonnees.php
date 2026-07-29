<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repare trois incoherences heritees de la base de production.
 *
 * Aucune n'est un defaut de conception : ce sont des degats causes par des
 * bugs de l'ancien back-end, restes en base apres coup. Les corriger dans
 * le code ne suffit donc pas — il faut reprendre les lignes existantes.
 *
 * 1. URL d'images pointant sur l'ancien hote
 *    Les images sont stockees en URL ABSOLUE, construite a l'ecriture
 *    depuis APP_URL. Celles ecrites par le back-end sur le port 8000
 *    continuent de le designer. Le jour ou l'ancien serveur s'arrete,
 *    toutes les images du catalogue disparaissent.
 *
 * 2. Slugs de categories mal translitteres
 *    L'ancien back-end generait ses slugs avec
 *    iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', ...), dont le resultat
 *    depend de la bibliotheque C du systeme. Sous Windows, « Céramique »
 *    devient « c-eramique » au lieu de « ceramique ». Le filtre
 *    ?categorie=ceramique ne remonte alors plus rien.
 *
 * 3. Liaisons produit_categorie manquantes
 *    `produits.categorie_id` porte la categorie principale, la table de
 *    liaison porte l'ensemble. Les filtres n'interrogent QUE la liaison :
 *    un produit absent de la table est invisible dans toute recherche par
 *    categorie, alors que sa fiche s'affiche normalement.
 *
 * Toujours lancer avec --dry-run d'abord.
 */
class ReparerDonnees extends Command
{
    protected $signature = 'db:reparer
                            {--dry-run : Affiche les corrections sans rien ecrire}
                            {--ancien-hote= : Hote a remplacer dans les URL d images, ex http://localhost:8000}';

    protected $description = 'Repare les incoherences de donnees heritees de l ancien back-end';

    private bool $simulation = false;

    public function handle(): int
    {
        $this->simulation = (bool) $this->option('dry-run');

        if ($this->simulation) {
            $this->comment('Mode simulation : aucune ecriture.');
        }

        $total = $this->reparerImages()
            + $this->reparerSlugs()
            + $this->reparerLiaisons();

        $this->line('');

        if ($total === 0) {
            $this->info('Rien a corriger.');
        } elseif ($this->simulation) {
            $this->comment("{$total} correction(s) a appliquer. Relancez sans --dry-run.");
        } else {
            $this->info("{$total} correction(s) appliquee(s).");
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // 1. URL d'images
    // ------------------------------------------------------------------

    private function reparerImages(): int
    {
        $nouvelHote = rtrim((string) config('app.url'), '/');
        $ancienHote = $this->option('ancien-hote');

        $this->line('');
        $this->info('— URL d images —');

        $produits = Produit::query()
            ->whereNotNull('images')
            ->get(['id', 'nom', 'images']);

        $corriges = 0;

        foreach ($produits as $produit) {
            $images = $produit->images;

            if (! is_array($images) || $images === []) {
                continue;
            }

            $nouvelles = array_map(
                fn (mixed $url) => $this->reecrireUrl((string) $url, $ancienHote, $nouvelHote),
                $images
            );

            if ($nouvelles === $images) {
                continue;
            }

            $corriges++;
            $this->line("  #{$produit->id} {$produit->nom}");

            foreach ($images as $i => $avant) {
                if (($nouvelles[$i] ?? null) !== $avant) {
                    $this->line("      {$avant}");
                    $this->line("   -> {$nouvelles[$i]}");
                }
            }

            if (! $this->simulation) {
                $produit->images = $nouvelles;
                $produit->saveQuietly();
            }
        }

        $this->line($corriges === 0 ? '  Aucune URL a reecrire.' : "  {$corriges} produit(s).");

        if ($corriges > 0) {
            $this->line('');
            $this->comment('  Rappel : reecrire les URL ne deplace pas les fichiers.');
            $this->comment('  Copiez le contenu de public/uploads de l ancien projet vers celui-ci.');
        }

        return $corriges;
    }

    /**
     * Ne touche qu'aux URL absolues portant un hote different du notre.
     * Les noms de fichiers nus du jeu de demonstration d'origine
     * (« bol-noyer-1.jpg ») sont laisses tels quels : le front les ecarte
     * deja et affiche son visuel de remplacement.
     */
    private function reecrireUrl(string $url, ?string $ancienHote, string $nouvelHote): string
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if ($ancienHote !== null && $ancienHote !== '') {
            return Str::startsWith($url, rtrim($ancienHote, '/'))
                ? $nouvelHote . Str::after($url, rtrim($ancienHote, '/'))
                : $url;
        }

        // Sans --ancien-hote, on reecrit toute URL qui contient /uploads/
        // et dont l'hote n'est pas deja le notre.
        if (! Str::contains($url, '/uploads/') || Str::startsWith($url, $nouvelHote)) {
            return $url;
        }

        return $nouvelHote . '/uploads/' . Str::afterLast($url, '/uploads/');
    }

    // ------------------------------------------------------------------
    // 2. Slugs de categories
    // ------------------------------------------------------------------

    private function reparerSlugs(): int
    {
        $this->line('');
        $this->info('— Slugs de categories —');

        $corriges = 0;

        foreach (Categorie::query()->orderBy('id')->get() as $categorie) {
            $attendu = Str::slug($categorie->nom);

            if ($attendu === '' || $attendu === $categorie->slug) {
                continue;
            }

            // Un autre enregistrement occupe deja le slug correct : on ne
            // touche a rien, le cas demande un arbitrage humain (doublon
            // de categorie, le plus souvent).
            $occupe = Categorie::query()
                ->where('slug', $attendu)
                ->where('id', '!=', $categorie->id)
                ->exists();

            if ($occupe) {
                $this->warn("  #{$categorie->id} {$categorie->nom} : « {$attendu} » est deja pris, ignore.");
                continue;
            }

            $corriges++;
            $this->line("  #{$categorie->id} {$categorie->nom} : {$categorie->slug} -> {$attendu}");

            if (! $this->simulation) {
                $categorie->slug = $attendu;
                $categorie->save();
            }
        }

        $this->line($corriges === 0 ? '  Aucun slug a corriger.' : "  {$corriges} categorie(s).");

        return $corriges;
    }

    // ------------------------------------------------------------------
    // 3. Liaisons produit_categorie
    // ------------------------------------------------------------------

    private function reparerLiaisons(): int
    {
        $this->line('');
        $this->info('— Liaisons produit_categorie —');

        // Produits ayant une categorie principale sans liaison correspondante.
        $manquantes = DB::table('produits as p')
            ->join('categories as c', 'c.id', '=', 'p.categorie_id')
            ->leftJoin('produit_categorie as pc', function ($j) {
                $j->on('pc.produit_id', '=', 'p.id')
                  ->on('pc.categorie_id', '=', 'p.categorie_id');
            })
            ->whereNotNull('p.categorie_id')
            ->whereNull('pc.produit_id')
            ->select('p.id', 'p.nom', 'p.categorie_id', 'c.nom as categorie_nom')
            ->get();

        foreach ($manquantes as $ligne) {
            $this->line("  #{$ligne->id} {$ligne->nom} -> {$ligne->categorie_nom}");
        }

        if (! $this->simulation && $manquantes->isNotEmpty()) {
            DB::table('produit_categorie')->insertOrIgnore(
                $manquantes->map(static fn ($l) => [
                    'produit_id'   => $l->id,
                    'categorie_id' => $l->categorie_id,
                ])->all()
            );
        }

        $nombre = $manquantes->count();
        $this->line($nombre === 0 ? '  Aucune liaison manquante.' : "  {$nombre} liaison(s).");

        return $nombre;
    }
}
