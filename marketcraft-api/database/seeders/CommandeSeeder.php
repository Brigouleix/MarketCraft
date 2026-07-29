<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deux commandes de demonstration, a des stades differents du cycle de vie.
 *
 * La premiere est livree depuis vingt jours : elle depasse le delai de
 * quatorze jours et permet de montrer sp_liberer_paiements_echus() faire
 * passer son paiement au statut « libere ».
 *
 * Elle sert aussi de prerequis aux avis : seul un acheteur du produit peut
 * le noter. Sans cette commande, AvisSeeder ne pourrait rien inserer qui
 * soit coherent avec la regle appliquee par l'API.
 */
class CommandeSeeder extends Seeder
{
    public function run(): void
    {
        $this->creer(
            email: 'jules.lemoine@example.com',
            statut: 'livree',
            fraisLivraison: 5.90,
            joursDepuisLivraison: 20,
            lignes: [
                ['bol-noyer-cire', 2],
                ['cadre-photo-rustique', 1],
            ],
            methodePaiement: 'carte',
        );

        $this->creer(
            email: 'camille.petit@example.com',
            statut: 'en_preparation',
            fraisLivraison: 5.90,
            joursDepuisLivraison: null,
            lignes: [
                ['mug-gres-bleu-ocean', 1],
                ['vase-effile-terracotta', 1],
            ],
            methodePaiement: 'paypal',
        );
    }

    /**
     * @param array<int, array{0: string, 1: int}> $lignes
     */
    private function creer(
        string $email,
        string $statut,
        float $fraisLivraison,
        ?int $joursDepuisLivraison,
        array $lignes,
        string $methodePaiement,
    ): void {
        $client = User::query()->where('email', $email)->first();

        if ($client === null) {
            return;
        }

        // Le seeder doit pouvoir etre rejoue sans empiler les commandes.
        if (Commande::query()->where('utilisateur_id', $client->id)->exists()) {
            return;
        }

        $adresse = Adresse::query()->where('utilisateur_id', $client->id)->first();

        $produits = Produit::query()
            ->whereIn('slug', array_column($lignes, 0))
            ->get()
            ->keyBy('slug');

        $montant = 0.0;
        $preparees = [];

        foreach ($lignes as [$slug, $quantite]) {
            $produit = $produits[$slug] ?? null;

            if ($produit === null) {
                continue;
            }

            $montant += (float) $produit->prix * $quantite;

            $preparees[] = [
                'produit_id'    => (int) $produit->id,
                'quantite'      => $quantite,
                'prix_unitaire' => (float) $produit->prix,
                'nom_produit'   => $produit->nom,
            ];
        }

        if ($preparees === []) {
            return;
        }

        $commande = Commande::create([
            'utilisateur_id'       => (int) $client->id,
            'adresse_livraison_id' => $adresse?->id,
            'statut'               => $statut,
            'montant_total'        => round($montant + $fraisLivraison, 2),
            'frais_livraison'      => $fraisLivraison,
            'date_livraison'       => $joursDepuisLivraison !== null
                ? now()->subDays($joursDepuisLivraison)
                : null,
        ]);

        foreach ($preparees as $ligne) {
            LigneCommande::create($ligne + ['commande_id' => (int) $commande->id]);
        }

        Paiement::create([
            'commande_id'    => (int) $commande->id,
            'methode'        => $methodePaiement,
            'statut'         => 'valide',
            'montant'        => (float) $commande->montant_total,
            'transaction_id' => 'SIM-SEED-' . strtoupper(bin2hex(random_bytes(3))),
            'payload'        => ['simulation' => true, 'origine' => 'seeder'],
        ]);
    }
}
