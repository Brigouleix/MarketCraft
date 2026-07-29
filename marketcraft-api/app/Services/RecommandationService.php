<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LigneCommande;
use App\Models\Produit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Module IA — recommandation personnalisee (option C du cahier des charges).
 *
 * « Sur la base de la fiche produit et du contenu du panier, l'IA suggere
 * des articles complementaires ou similaires pertinents. »
 *
 * ---------------------------------------------------------------------
 * Algorithme de selection — a documenter en soutenance
 * ---------------------------------------------------------------------
 *
 * 1. PRESELECTION (toujours, sans IA)
 *    On ne soumet jamais le catalogue entier au modele : c'est couteux,
 *    lent, et la fenetre de contexte le tronquerait. Un score local
 *    classe d'abord les candidats :
 *
 *      +40  categorie principale identique
 *      +25  par categorie secondaire partagee (liaison N-N)
 *      +15  meme boutique          (complementarite d'un meme artisan)
 *      +20  ecart de prix < 30 %   (meme gamme, donc meme intention d'achat)
 *      +10  ecart de prix < 60 %
 *      + 5  note moyenne >= 4      (departage a pertinence egale)
 *      -50  rupture de stock       (recommander l'indisponible degrade
 *                                   l'experience sans rien rapporter)
 *
 *    Les 15 meilleurs partent au modele. Les produits deja au panier et
 *    le produit consulte sont exclus.
 *
 * 2. CLASSEMENT PAR L'IA
 *    Le modele recoit ces 15 fiches reduites (id, nom, categorie, prix,
 *    description tronquee) et rend un tableau ordonne d'identifiants,
 *    avec un motif par produit. Il ne cree rien : il ne fait que trier
 *    et justifier. Une reponse contenant un id absent de la preselection
 *    est ecartee — le modele ne peut donc pas inventer de produit.
 *
 * 3. REPLI
 *    Si l'IA echoue — cle absente, quota, reseau, JSON invalide, id
 *    inconnu — on renvoie les N premiers de la preselection. La
 *    fonctionnalite se degrade, elle ne casse pas. `ia_active` passe a
 *    false pour que le front distingue les deux cas, et le motif de
 *    l'echec part dans storage/logs/ia.log. Jamais de retour muet.
 */
class RecommandationService
{
    private const CANDIDATS_MAX = 15;
    private const DESCRIPTION_MAX = 180;

    /**
     * Recommandations a partir d'un produit consulte.
     *
     * @return array{produits: array<int, Produit>, ia_active: bool, motifs: array<int, string>, source: string}
     */
    public function pourProduit(Produit $produit, int $limite = 4): array
    {
        $candidats = $this->preselectionner(
            reference: $produit,
            exclusions: [(int) $produit->id],
        );

        return $this->classer(
            $candidats,
            $limite,
            $this->contexteProduit($produit)
        );
    }

    /**
     * Recommandations a partir du contenu du panier.
     *
     * @param  int[] $produitIds
     * @return array{produits: array<int, Produit>, ia_active: bool, motifs: array<int, string>, source: string}
     */
    public function pourPanier(array $produitIds, int $limite = 4): array
    {
        $panier = Produit::query()
            ->actif()
            ->whereIn('id', $produitIds)
            ->with(['categories:id,nom', 'categorie:id,nom', 'boutique:id,nom,vendeur_id'])
            ->get();

        if ($panier->isEmpty()) {
            return ['produits' => [], 'ia_active' => false, 'motifs' => [], 'source' => 'panier_vide'];
        }

        // Le produit le plus cher sert de reference de gamme : c'est lui
        // qui porte l'intention d'achat dominante.
        $reference = $panier->sortByDesc(static fn (Produit $p) => (float) $p->prix)->first();

        $candidats = $this->preselectionner(
            reference: $reference,
            exclusions: $panier->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            panier: $panier,
        );

        return $this->classer($candidats, $limite, $this->contextePanier($panier));
    }

    /**
     * Recommandations a partir de l'historique d'achat.
     *
     * Prolongement direct de l'option C : plutot que le seul panier en
     * cours, on prend les articles reellement commandes par le client.
     * Les commandes annulees sont ecartees — elles ne disent rien d'un
     * gout, seulement d'un renoncement.
     *
     * Les produits deja achetes sont exclus des suggestions : proposer a
     * quelqu'un ce qu'il possede deja est le defaut le plus visible d'un
     * moteur de recommandation.
     *
     * @return array{produits: array<int, Produit>, ia_active: bool, motifs: array<int, string>, source: string}
     */
    public function pourHistorique(int $utilisateurId, int $limite = 4): array
    {
        $achetes = LigneCommande::query()
            ->select('lignes_commande.produit_id')
            ->join('commandes', 'commandes.id', '=', 'lignes_commande.commande_id')
            ->where('commandes.utilisateur_id', $utilisateurId)
            ->where('commandes.statut', '!=', 'annulee')
            // Les achats recents pesent plus lourd que ceux d'il y a un an.
            ->orderByDesc('commandes.created_at')
            ->limit(20)
            ->pluck('lignes_commande.produit_id')
            ->unique()
            ->values();

        if ($achetes->isEmpty()) {
            return [
                'produits'  => [],
                'ia_active' => false,
                'motifs'    => [],
                'source'    => 'historique_vide',
            ];
        }

        $historique = Produit::query()
            ->whereIn('id', $achetes)
            ->with(['categories:id,nom', 'categorie:id,nom', 'boutique:id,nom,vendeur_id'])
            ->get();

        if ($historique->isEmpty()) {
            return [
                'produits'  => [],
                'ia_active' => false,
                'motifs'    => [],
                'source'    => 'historique_vide',
            ];
        }

        // Reference de gamme : le panier moyen du client, pas son achat le
        // plus cher — un cadeau exceptionnel ne doit pas fausser toutes ses
        // recommandations ulterieures.
        $reference = $historique
            ->sortBy(fn (Produit $p) => abs((float) $p->prix - $historique->avg(fn (Produit $q) => (float) $q->prix)))
            ->first();

        $candidats = $this->preselectionner(
            reference: $reference,
            exclusions: $achetes->map(static fn ($id) => (int) $id)->all(),
            panier: $historique,
        );

        return $this->classer($candidats, $limite, $this->contexteHistorique($historique));
    }

    // ------------------------------------------------------------------
    // 1. Preselection locale
    // ------------------------------------------------------------------

    /**
     * @param  int[] $exclusions
     * @return Collection<int, Produit>
     */
    private function preselectionner(
        Produit $reference,
        array $exclusions,
        ?Collection $panier = null
    ): Collection {
        $reference->loadMissing(['categories:id,nom', 'categorie:id,nom']);

        $categoriesRef = $panier !== null
            ? $panier->flatMap(static fn (Produit $p) => $p->categories->pluck('id'))->unique()->all()
            : $reference->categories->pluck('id')->all();

        $prixRef = (float) $reference->prix;

        // On ne charge pas tout le catalogue : seuls les produits qui
        // partagent au moins une categorie, ou la meme boutique, sont
        // plausibles. Au-dela, le score n'aurait plus rien a departager.
        $candidats = Produit::query()
            ->actif()
            ->whereNotIn('id', $exclusions)
            ->with(['boutique:id,nom,vendeur_id', 'categorie:id,nom', 'categories:id,nom'])
            ->where(function ($q) use ($categoriesRef, $reference) {
                $q->where('boutique_id', $reference->boutique_id);

                if ($categoriesRef !== []) {
                    $q->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoriesRef));
                }
            })
            ->limit(120)
            ->get();

        return $candidats
            ->map(function (Produit $p) use ($reference, $categoriesRef, $prixRef) {
                $p->score_reco = $this->score($p, $reference, $categoriesRef, $prixRef);

                return $p;
            })
            ->sortByDesc('score_reco')
            ->take(self::CANDIDATS_MAX)
            ->values();
    }

    /**
     * @param  int[] $categoriesRef
     */
    private function score(Produit $candidat, Produit $reference, array $categoriesRef, float $prixRef): int
    {
        $score = 0;

        if ($candidat->categorie_id !== null && $candidat->categorie_id === $reference->categorie_id) {
            $score += 40;
        }

        $partagees = $candidat->categories->pluck('id')->intersect($categoriesRef)->count();
        $score += 25 * $partagees;

        if ((int) $candidat->boutique_id === (int) $reference->boutique_id) {
            $score += 15;
        }

        if ($prixRef > 0) {
            $ecart = abs((float) $candidat->prix - $prixRef) / $prixRef;

            if ($ecart < 0.30) {
                $score += 20;
            } elseif ($ecart < 0.60) {
                $score += 10;
            }
        }

        if ((float) $candidat->note_moyenne >= 4.0) {
            $score += 5;
        }

        // Une suggestion en rupture n'apporte rien : on la conserve en
        // dernier recours plutot que de renvoyer une liste vide.
        if ((int) $candidat->stock <= 0) {
            $score -= 50;
        }

        return $score;
    }

    // ------------------------------------------------------------------
    // 2. Classement par l'IA, avec repli
    // ------------------------------------------------------------------

    /**
     * @param  Collection<int, Produit> $candidats
     * @return array{produits: array<int, Produit>, ia_active: bool, motifs: array<int, string>, source: string}
     */
    private function classer(Collection $candidats, int $limite, string $contexte): array
    {
        if ($candidats->isEmpty()) {
            return ['produits' => [], 'ia_active' => false, 'motifs' => [], 'source' => 'aucun_candidat'];
        }

        $repli = [
            'produits'  => $candidats->take($limite)->values()->all(),
            'ia_active' => false,
            'motifs'    => [],
            'source'    => 'similarite',
        ];

        $cle = (string) config('marketcraft.ai.key');

        if ($cle === '') {
            $this->tracerEchec('cle_absente', 'AI_API_KEY non renseignee : repli par similarite.');

            return $repli;
        }

        try {
            $reponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $cle,
                    'Content-Type'  => 'application/json',
                    // Sans User-Agent, Cloudflare renvoie 403 avant meme
                    // de verifier la cle. Ne pas retirer cet en-tete.
                    'User-Agent'    => (string) config('marketcraft.ai.user_agent'),
                ])
                ->timeout((int) config('marketcraft.ai.timeout', 12))
                ->post((string) config('marketcraft.ai.url'), [
                    'model'    => (string) config('marketcraft.ai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->consigneSysteme()],
                        ['role' => 'user',   'content' => $contexte . "\n\n" . $this->catalogue($candidats)],
                    ],
                    // Sortie JSON stricte : sans cela le modele encadre sa
                    // reponse de texte libre et le decodage echoue.
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.3,
                ]);

            if (! $reponse->successful()) {
                $this->tracerEchec('http_' . $reponse->status(), mb_substr($reponse->body(), 0, 300));

                return $repli;
            }

            $contenu = $reponse->json('choices.0.message.content');

            if (! is_string($contenu)) {
                $this->tracerEchec('reponse_vide', 'Le champ content est absent de la reponse.');

                return $repli;
            }

            $decode = json_decode($contenu, true);

            if (! is_array($decode) || ! isset($decode['recommandations']) || ! is_array($decode['recommandations'])) {
                $this->tracerEchec('json_invalide', mb_substr($contenu, 0, 300));

                return $repli;
            }

            $parId   = $candidats->keyBy(static fn (Produit $p) => (int) $p->id);
            $retenus = [];
            $motifs  = [];

            foreach ($decode['recommandations'] as $item) {
                $id = (int) ($item['id'] ?? 0);

                // Un identifiant hors preselection est ignore : le modele
                // ne doit pas pouvoir inventer de produit ni en exhumer un
                // que le score avait ecarte.
                if (! $parId->has($id)) {
                    continue;
                }

                $retenus[]    = $parId->get($id);
                $motifs[$id]  = mb_substr((string) ($item['motif'] ?? ''), 0, 200);

                if (count($retenus) >= $limite) {
                    break;
                }
            }

            if ($retenus === []) {
                $this->tracerEchec('aucun_id_valide', 'Le modele n a renvoye aucun identifiant de la preselection.');

                return $repli;
            }

            return [
                'produits'  => $retenus,
                'ia_active' => true,
                'motifs'    => $motifs,
                'source'    => 'ia',
            ];
        } catch (\Throwable $e) {
            // Reseau coupe, DNS, timeout : la page produit doit s'afficher
            // quand meme.
            $this->tracerEchec('exception', $e->getMessage());

            return $repli;
        }
    }

    // ------------------------------------------------------------------
    // Construction des messages
    // ------------------------------------------------------------------

    private function consigneSysteme(): string
    {
        return implode(' ', [
            "Tu es assistant d'une marketplace d'artisanat francaise.",
            'On te donne un contexte d achat et une liste de produits candidats.',
            'Choisis les produits les plus pertinents a suggerer, du plus au moins pertinent.',
            'Privilegie la complementarite (usage commun, meme piece de la maison, meme matiere)',
            'plutot que la simple ressemblance.',
            'Reponds UNIQUEMENT en JSON, sans texte autour, selon ce schema :',
            '{"recommandations":[{"id":<entier>,"motif":"<une phrase courte en francais>"}]}.',
            'N utilise que des id presents dans la liste fournie.',
        ]);
    }

    private function contexteProduit(Produit $produit): string
    {
        $categorie = $produit->categorie?->nom ?? 'non precisee';

        return "Le client consulte la fiche du produit suivant :\n"
            . "- nom : {$produit->nom}\n"
            . "- categorie : {$categorie}\n"
            . '- prix : ' . $produit->prix . " EUR\n"
            . '- description : ' . $this->tronquer((string) $produit->description);
    }

    /**
     * @param  Collection<int, Produit> $panier
     */
    private function contextePanier(Collection $panier): string
    {
        $lignes = $panier->map(function (Produit $p) {
            $categorie = $p->categorie?->nom ?? 'non precisee';

            return "- {$p->nom} ({$categorie}, {$p->prix} EUR)";
        })->implode("\n");

        return "Le panier du client contient :\n{$lignes}";
    }

    /**
     * @param  Collection<int, Produit> $historique
     */
    private function contexteHistorique(Collection $historique): string
    {
        $lignes = $historique->map(function (Produit $p) {
            $categorie = $p->categorie?->nom ?? 'non precisee';

            return "- {$p->nom} ({$categorie}, {$p->prix} EUR)";
        })->implode("\n");

        $moyen = round((float) $historique->avg(fn (Produit $p) => (float) $p->prix), 2);

        return "Le client a deja achete les articles suivants :\n{$lignes}\n"
            . "Panier moyen constate : {$moyen} EUR.\n"
            . 'Suggere des articles complementaires, en evitant de proposer '
            . 'un equivalent de ce qu il possede deja.';
    }

    /**
     * @param  Collection<int, Produit> $candidats
     */
    private function catalogue(Collection $candidats): string
    {
        $lignes = $candidats->map(function (Produit $p) {
            $categorie = $p->categorie?->nom ?? 'non precisee';
            $boutique  = $p->boutique?->nom ?? 'inconnue';

            return "id={$p->id} | {$p->nom} | categorie: {$categorie} | boutique: {$boutique} | "
                . "prix: {$p->prix} EUR | " . $this->tronquer((string) $p->description);
        })->implode("\n");

        return "Produits candidats :\n{$lignes}";
    }

    private function tronquer(string $texte): string
    {
        $texte = trim(preg_replace('/\s+/', ' ', $texte) ?? '');

        return mb_strlen($texte) > self::DESCRIPTION_MAX
            ? mb_substr($texte, 0, self::DESCRIPTION_MAX) . '...'
            : $texte;
    }

    /**
     * Chaque echec est trace avec son motif. Un `return null` muet rendrait
     * impossible de distinguer « l IA n a rien trouve » de « la cle a expire ».
     */
    private function tracerEchec(string $motif, string $detail): void
    {
        Log::channel('ia')->warning('Repli sur la selection par similarite', [
            'motif'  => $motif,
            'detail' => $detail,
            'modele' => config('marketcraft.ai.model'),
        ]);
    }
}
