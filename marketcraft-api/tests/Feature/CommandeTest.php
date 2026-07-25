<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreeDesDonnees;
use Tests\TestCase;

class CommandeTest extends TestCase
{
    use CreeDesDonnees;
    use RefreshDatabase;

    public function test_le_parcours_de_commande_complet(): void
    {
        $client   = $this->creerUtilisateur('client');
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $produit  = $this->creerProduit($boutique, ['prix' => 45.00, 'stock' => 10]);

        $reponse = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 2]],
            'adresse_livraison' => [
                'nom_complet' => 'Jules Lemoine',
                'ligne1'      => '12 rue des Fleurs',
                'ville'       => 'Lyon',
                'code_postal' => '69001',
            ],
        ], $this->entetes($client));

        $reponse->assertStatus(201)
            ->assertJsonPath('message', 'Order created.')
            ->assertJsonPath('data.statut', 'en_attente')
            // 2 x 45,00 + 5,90 de frais de port.
            ->assertJsonPath('data.montant_total', '95.90')
            ->assertJsonPath('data.paiement.statut', 'valide');

        // Le prix vient de la base, jamais du client.
        $this->assertSame('45.00', $reponse->json('data.lignes.0.prix_unitaire'));

        $this->assertSame(8, Produit::query()->find($produit->id)->stock);
    }

    public function test_le_prix_envoye_par_le_client_est_ignore(): void
    {
        $client  = $this->creerUtilisateur('client');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')), ['prix' => 45.00]);

        $reponse = $this->postJson('/api/orders', [
            'lignes' => [[
                'produit_id'    => $produit->id,
                'quantite'      => 1,
                'prix_unitaire' => 0.01,
            ]],
        ], $this->entetes($client));

        $this->assertSame('45.00', $reponse->json('data.lignes.0.prix_unitaire'));
    }

    public function test_stock_insuffisant_refuse_la_commande(): void
    {
        $client  = $this->creerUtilisateur('client');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')), ['stock' => 1]);

        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 5]],
        ], $this->entetes($client))->assertStatus(422);

        // La transaction est annulee : ni commande, ni stock entame.
        $this->assertDatabaseCount('commandes', 0);
        $this->assertSame(1, Produit::query()->find($produit->id)->stock);
    }

    public function test_on_ne_voit_pas_la_commande_d_un_autre(): void
    {
        $acheteur = $this->creerUtilisateur('client');
        $curieux  = $this->creerUtilisateur('client');
        $produit  = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($acheteur))->json('data.id');

        $this->getJson("/api/orders/{$id}", $this->entetes($curieux))->assertStatus(403);
        $this->getJson("/api/orders/{$id}", $this->entetes($acheteur))->assertStatus(200);
        $this->assertCount(0, $this->getJson('/api/orders', $this->entetes($curieux))->json('data'));
    }

    public function test_seul_un_vendeur_ou_admin_change_le_statut(): void
    {
        $client  = $this->creerUtilisateur('client');
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($vendeur));

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client))->json('data.id');

        $this->putJson("/api/orders/{$id}/status", ['statut' => 'expediee'], $this->entetes($client))
            ->assertStatus(403);

        $this->putJson("/api/orders/{$id}/status", ['statut' => 'livree'], $this->entetes($vendeur))
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'livree');

        // Le passage a « livree » horodate la livraison, base du J+14.
        //
        // Relu avec le compte de l'acheteur : un vendeur peut changer le
        // statut d'une commande mais ne peut pas en consulter le detail,
        // reserve au proprietaire et a l'administrateur. Ce cloisonnement
        // est herite de l'ancien back-end — voir la note dans
        // docs/ECARTS-CONTRAT.md.
        $this->assertNotNull(
            $this->getJson("/api/orders/{$id}", $this->entetes($client))->json('data.date_livraison')
        );

        // Et le vendeur se voit bien refuser la lecture.
        $this->getJson("/api/orders/{$id}", $this->entetes($vendeur))->assertStatus(403);
    }

    public function test_une_commande_expediee_n_est_plus_annulable(): void
    {
        $client  = $this->creerUtilisateur('client');
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($vendeur), ['stock' => 10]);

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 2]],
        ], $this->entetes($client))->json('data.id');

        $this->putJson("/api/orders/{$id}/status", ['statut' => 'expediee'], $this->entetes($vendeur));

        $this->deleteJson("/api/orders/{$id}", [], $this->entetes($client))->assertStatus(409);
    }

    public function test_l_annulation_remet_le_stock(): void
    {
        $client  = $this->creerUtilisateur('client');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')), ['stock' => 10]);

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 3]],
        ], $this->entetes($client))->json('data.id');

        $this->assertSame(7, Produit::query()->find($produit->id)->stock);

        $this->deleteJson("/api/orders/{$id}", [], $this->entetes($client))->assertStatus(200);

        $this->assertSame(10, Produit::query()->find($produit->id)->stock);
    }

    public function test_seul_un_acheteur_peut_deposer_un_avis(): void
    {
        $client  = $this->creerUtilisateur('client');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));

        $this->postJson("/api/products/{$produit->id}/avis", [
            'note' => 5, 'commentaire' => 'Sans avoir achete',
        ], $this->entetes($client))->assertStatus(403);

        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client));

        $this->postJson("/api/products/{$produit->id}/avis", [
            'note' => 5, 'titre' => 'Superbe', 'commentaire' => 'Tres bien fini.',
        ], $this->entetes($client))->assertStatus(201)->assertJsonPath('data.est_verifie', 1);

        // Un seul avis par couple (produit, utilisateur).
        $this->postJson("/api/products/{$produit->id}/avis", [
            'note' => 4,
        ], $this->entetes($client))->assertStatus(409);
    }

    public function test_la_liste_des_avis_porte_la_cle_stats(): void
    {
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));

        $this->getJson("/api/products/{$produit->id}/avis")
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'stats' => ['moyenne', 'total', 'repartition'],
                'pagination' => ['total', 'page', 'limit', 'total_pages'],
            ]);
    }
}
