<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CommandeResource;
use App\Models\Adresse;
use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\Paiement;
use App\Models\Produit;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommandeController extends Controller
{
    /** Frais de port par defaut, en euros. */
    private const FRAIS_LIVRAISON_DEFAUT = 5.90;

    public function __construct(private readonly ActivityLogger $journal)
    {
    }

    // ------------------------------------------------------------------
    // GET /orders
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $page  = max(1, (int) $request->query('page', 1));
        $limit = max(1, min(50, (int) $request->query('limit', 20)));

        // Un administrateur voit tout ; les autres ne voient que leurs
        // propres commandes. Sans ce filtre, l'endpoint exposerait
        // l'historique d'achat de la plateforme entiere.
        $base = Commande::query()
            ->when(! $user->estAdmin(), fn ($q) => $q->where('utilisateur_id', $user->id));

        $total = (clone $base)->count();

        $commandes = $base
            ->withCount(['lignes as nb_articles'])
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get();

        return $this->pagine(CommandeResource::collection($commandes), $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // GET /orders/:id
    // ------------------------------------------------------------------

    public function show(Request $request, int $id): JsonResponse
    {
        $commande = $this->chargerDetail($id);

        if ($commande === null) {
            return $this->nonTrouve('Order not found.');
        }

        $user = $request->user();

        if (! $user->estAdmin() && (int) $commande->utilisateur_id !== (int) $user->id) {
            return $this->interdit();
        }

        return $this->ok(CommandeResource::make($commande));
    }

    // ------------------------------------------------------------------
    // POST /orders
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'lignes'                => ['required', 'array', 'min:1'],
            'lignes.*.produit_id'   => ['required', 'integer', 'min:1'],
            'lignes.*.quantite'     => ['required', 'integer', 'min:1'],
            'frais_livraison'       => ['sometimes', 'numeric', 'min:0'],
            'note'                  => ['sometimes', 'nullable', 'string'],
            'adresse_livraison_id'  => ['sometimes', 'nullable', 'integer'],
            'adresse_livraison'     => ['sometimes', 'array'],
            'paiement.methode'      => ['sometimes', 'string'],
        ]);

        $user = $request->user();

        // Les ValidationException levees dans la transaction remontent au
        // gestionnaire global, qui les traduit en 422 avec le detail par
        // champ — et la transaction est annulee au passage.
        $commande = DB::transaction(function () use ($request, $user) {
            $lignes = $this->preparerLignes($request->input('lignes', []));

            $adresseId = $this->resoudreAdresse($request, (int) $user->id);

            $montantLignes = array_reduce(
                $lignes,
                static fn (float $total, array $l) => $total + ($l['prix_unitaire'] * $l['quantite']),
                0.0
            );

            $frais = (float) $request->input('frais_livraison', self::FRAIS_LIVRAISON_DEFAUT);

            $commande = Commande::create([
                'utilisateur_id'       => (int) $user->id,
                'adresse_livraison_id' => $adresseId,
                'statut'               => 'en_attente',
                'montant_total'        => round($montantLignes + $frais, 2),
                'frais_livraison'      => $frais,
                'note'                 => $request->input('note'),
            ]);

            foreach ($lignes as $ligne) {
                LigneCommande::create([
                    'commande_id'   => (int) $commande->id,
                    'produit_id'    => $ligne['produit_id'],
                    'quantite'      => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'nom_produit'   => $ligne['nom_produit'],
                ]);

                // Le decrement porte une condition sur le stock : si
                // deux commandes se croisent, la seconde met a jour
                // zero ligne et l'on refuse plutot que de vendre a
                // decouvert.
                $affectees = Produit::query()
                    ->where('id', $ligne['produit_id'])
                    ->where('stock', '>=', $ligne['quantite'])
                    ->decrement('stock', $ligne['quantite']);

                if ($affectees === 0) {
                    throw ValidationException::withMessages([
                        'lignes' => ["Insufficient stock for \"{$ligne['nom_produit']}\"."],
                    ]);
                }
            }

            return $commande;
        });

        // Paiement simule : la plateforme n'est reliee a aucun prestataire
        // reel. La transaction est tracee avec un identifiant prefixe SIM-,
        // pour qu'aucune ligne ne puisse etre prise pour un vrai encaissement.
        $paiement = Paiement::create([
            'commande_id'    => (int) $commande->id,
            'methode'        => $this->methodePaiement($request->input('paiement.methode')),
            'statut'         => 'valide',
            'montant'        => (float) $commande->montant_total,
            'transaction_id' => 'SIM-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'payload'        => [
                'simulation' => true,
                'detail'     => $request->input('paiement.detail'),
            ],
        ]);

        $this->journal->info(
            'commande_creee',
            "Commande #{$commande->id} — {$commande->montant_total} EUR",
            ['commande_id' => (int) $commande->id],
            (int) $user->id
        );

        $donnees = CommandeResource::make($this->chargerDetail((int) $commande->id));
        $donnees['paiement'] = $paiement->toArray();

        return $this->cree($donnees, 'Order created.');
    }

    // ------------------------------------------------------------------
    // PUT /orders/:id/status   (vendeur ou admin)
    // ------------------------------------------------------------------

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $commande = Commande::query()->find($id);

        if ($commande === null) {
            return $this->nonTrouve('Order not found.');
        }

        $donnees = $request->validate([
            'statut'       => ['required', 'string', 'in:' . implode(',', Commande::STATUTS)],
            'numero_suivi' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $ancien = $commande->statut;

        $commande->statut = $donnees['statut'];

        if (array_key_exists('numero_suivi', $donnees)) {
            $commande->numero_suivi = $donnees['numero_suivi'];
        }

        // Le passage a « livree » horodate la livraison : c'est cette date
        // qui declenche la liberation du paiement au vendeur, a J+14.
        if ($donnees['statut'] === 'livree' && $commande->date_livraison === null) {
            $commande->date_livraison = now();
        }

        $commande->save();

        $this->journal->info(
            'commande_statut',
            "Commande #{$commande->id} : {$ancien} -> {$commande->statut}",
            ['commande_id' => (int) $commande->id, 'ancien' => $ancien, 'nouveau' => $commande->statut],
            (int) $request->user()->id
        );

        return $this->ok(
            CommandeResource::make($this->chargerDetail((int) $commande->id)),
            'Order status updated.'
        );
    }

    // ------------------------------------------------------------------
    // DELETE /orders/:id   (annulation)
    // ------------------------------------------------------------------

    public function destroy(Request $request, int $id): JsonResponse
    {
        $commande = Commande::query()->with('lignes')->find($id);

        if ($commande === null) {
            return $this->nonTrouve('Order not found.');
        }

        $user = $request->user();

        if (! $user->estAdmin() && (int) $commande->utilisateur_id !== (int) $user->id) {
            return $this->interdit();
        }

        if (! $commande->estAnnulable()) {
            return $this->echec('Cannot cancel this order (already shipped or delivered).', 409);
        }

        DB::transaction(function () use ($commande) {
            $commande->statut = 'annulee';
            $commande->save();

            // Le stock repart au catalogue : sans cette remise, chaque
            // annulation retire definitivement les articles de la vente.
            foreach ($commande->lignes as $ligne) {
                Produit::query()
                    ->where('id', $ligne->produit_id)
                    ->increment('stock', (int) $ligne->quantite);
            }
        });

        $this->journal->info(
            'commande_annulee',
            "Commande #{$commande->id} annulee",
            ['commande_id' => (int) $commande->id],
            (int) $user->id
        );

        return $this->ok(null, 'Order cancelled successfully.');
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    private function chargerDetail(int $id): ?Commande
    {
        return Commande::query()
            ->with([
                'utilisateur:id,nom,prenom,email',
                'adresse',
                'lignes.produit:id,slug,images',
            ])
            ->find($id);
    }

    /**
     * Verifie chaque ligne et fige le prix et le nom du produit.
     *
     * Le prix vient de la base, jamais du client : accepter un
     * `prix_unitaire` envoye par le navigateur laisserait acheter a
     * n'importe quel tarif.
     *
     * @param  array<int, mixed> $brutes
     * @return array<int, array{produit_id: int, quantite: int, prix_unitaire: float, nom_produit: string}>
     */
    private function preparerLignes(array $brutes): array
    {
        $lignes = [];

        foreach ($brutes as $index => $ligne) {
            $produitId = (int) ($ligne['produit_id'] ?? 0);
            $quantite  = (int) ($ligne['quantite'] ?? 0);

            // Verrou de ligne : deux commandes simultanees sur le meme
            // article sont serialisees jusqu'a la fin de la transaction.
            $produit = Produit::query()
                ->where('id', $produitId)
                ->where('est_actif', 1)
                ->lockForUpdate()
                ->first();

            if ($produit === null) {
                throw ValidationException::withMessages([
                    "lignes.{$index}.produit_id" => ["Line #{$index}: Product #{$produitId} not found."],
                ]);
            }

            if ((int) $produit->stock < $quantite) {
                throw ValidationException::withMessages([
                    "lignes.{$index}.quantite" => [
                        "Line #{$index}: Insufficient stock for \"{$produit->nom}\" (available: {$produit->stock}).",
                    ],
                ]);
            }

            $lignes[] = [
                'produit_id'    => (int) $produit->id,
                'quantite'      => $quantite,
                'prix_unitaire' => (float) $produit->prix,
                'nom_produit'   => $produit->nom,
            ];
        }

        return $lignes;
    }

    /**
     * Identifiant d'adresse existant, ou creation a la volee depuis l'objet
     * `adresse_livraison` du corps de la requete.
     */
    private function resoudreAdresse(Request $request, int $utilisateurId): ?int
    {
        $id = $request->input('adresse_livraison_id');

        if ($id !== null && $id !== '') {
            // L'adresse doit appartenir a l'acheteur : sans ce controle,
            // il suffirait d'incrementer un identifiant pour lire les
            // adresses des autres via le detail de la commande.
            $existe = Adresse::query()
                ->where('id', (int) $id)
                ->where('utilisateur_id', $utilisateurId)
                ->exists();

            if (! $existe) {
                throw ValidationException::withMessages([
                    'adresse_livraison_id' => ['Adresse de livraison introuvable.'],
                ]);
            }

            return (int) $id;
        }

        $adresse = $request->input('adresse_livraison');

        if (! is_array($adresse) || $adresse === []) {
            return null;
        }

        $valide = validator($adresse, [
            'nom_complet' => ['required', 'string', 'min:2', 'max:200'],
            'ligne1'      => ['required', 'string', 'min:3', 'max:255'],
            'ligne2'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'ville'       => ['required', 'string', 'max:100'],
            'code_postal' => ['required', 'string', 'min:4', 'max:20'],
            'pays'        => ['sometimes', 'nullable', 'string', 'max:100'],
        ])->validate();

        return (int) Adresse::create([
            'utilisateur_id' => $utilisateurId,
            'nom_complet'    => trim($valide['nom_complet']),
            'ligne1'         => trim($valide['ligne1']),
            'ligne2'         => $valide['ligne2'] ?? null,
            'ville'          => trim($valide['ville']),
            'code_postal'    => trim($valide['code_postal']),
            'pays'           => $valide['pays'] ?? 'France',
        ])->id;
    }

    private function methodePaiement(mixed $methode): string
    {
        return in_array($methode, Paiement::METHODES, true) ? $methode : 'carte';
    }
}
