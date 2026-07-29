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
        // Relu avec le compte de l'acheteur.
        $this->assertNotNull(
            $this->getJson("/api/orders/{$id}", $this->entetes($client))->json('data.date_livraison')
        );

        // Le vendeur concerne peut desormais lire la commande : il doit
        // savoir quoi preparer.
        $this->getJson("/api/orders/{$id}", $this->entetes($vendeur))->assertStatus(200);
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

    // ------------------------------------------------------------------
    // Separation des roles
    // ------------------------------------------------------------------

    public function test_un_vendeur_ne_peut_pas_passer_commande(): void
    {
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));

        // Le refus qui fait foi est ici : masquer le panier cote interface
        // ne protege rien, l'API restant appelable directement.
        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($vendeur))->assertStatus(403);

        $this->assertDatabaseCount('commandes', 0);
    }

    public function test_un_acheteur_ne_peut_pas_ouvrir_de_boutique(): void
    {
        $client = $this->creerUtilisateur('client');

        $this->postJson('/api/boutiques', ['nom' => 'Ma tentative'], $this->entetes($client))
            ->assertStatus(403)
            ->assertJsonPath('error', 'Seul un compte vendeur peut ouvrir une boutique.');

        // Et le role n'a pas ete promu au passage : il se choisit a
        // l'inscription et n'evolue plus.
        $this->assertSame('client', $client->fresh()->role);
        $this->assertDatabaseCount('boutiques', 0);
    }

    public function test_le_resume_de_l_acheteur_porte_ses_lignes(): void
    {
        $client  = $this->creerUtilisateur('client');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));

        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 2]],
        ], $this->entetes($client));

        // Sans ces lignes, l'historique ne permet pas de remonter a la
        // fiche produit — donc de deposer un avis apres livraison.
        $reponse = $this->getJson('/api/orders', $this->entetes($client));

        $reponse->assertStatus(200)
            ->assertJsonPath('data.0.lignes.0.produit_id', (int) $produit->id)
            ->assertJsonPath('data.0.lignes.0.quantite', 2);

        $this->assertNotNull($reponse->json('data.0.lignes.0.produit_slug'));
    }

    // ------------------------------------------------------------------
    // Vue « ventes » du vendeur
    // ------------------------------------------------------------------

    public function test_le_vendeur_voit_les_commandes_contenant_ses_produits(): void
    {
        $client  = $this->creerUtilisateur('client');
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($vendeur));

        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client))->assertStatus(201);

        // Sans scope : ses propres achats, donc rien.
        $this->assertCount(0, $this->getJson('/api/orders', $this->entetes($vendeur))->json('data'));

        // Avec scope=ventes : la commande de son client.
        $ventes = $this->getJson('/api/orders?scope=ventes', $this->entetes($vendeur));

        $ventes->assertStatus(200);
        $this->assertCount(1, $ventes->json('data'));

        // Le nom du client accompagne la vente : le vendeur doit savoir a
        // qui expedier.
        $this->assertSame($client->nom, $ventes->json('data.0.client_nom'));
    }

    public function test_un_vendeur_ne_voit_pas_les_ventes_d_un_autre(): void
    {
        $client   = $this->creerUtilisateur('client');
        $vendeurA = $this->creerUtilisateur('vendeur');
        $vendeurB = $this->creerUtilisateur('vendeur');
        $this->creerBoutique($vendeurB);

        $produit = $this->creerProduit($this->creerBoutique($vendeurA));

        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client));

        $this->assertCount(
            0,
            $this->getJson('/api/orders?scope=ventes', $this->entetes($vendeurB))->json('data')
        );
    }

    public function test_un_client_ne_peut_pas_demander_la_vue_ventes(): void
    {
        $client = $this->creerUtilisateur('client');

        $this->getJson('/api/orders?scope=ventes', $this->entetes($client))
            ->assertStatus(403);
    }

    public function test_la_liste_de_l_acheteur_n_expose_pas_le_nom_du_client(): void
    {
        $client  = $this->creerUtilisateur('client');
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));

        $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client));

        // Inutile sur sa propre liste : la donnee ne sort que si elle sert.
        $this->getJson('/api/orders', $this->entetes($client))
            ->assertStatus(200)
            ->assertJsonMissingPath('data.0.client_nom');
    }

    public function test_un_vendeur_ne_change_pas_le_statut_d_une_commande_qui_ne_le_concerne_pas(): void
    {
        $client   = $this->creerUtilisateur('client');
        $vendeurA = $this->creerUtilisateur('vendeur');
        $intrus   = $this->creerUtilisateur('vendeur');
        $this->creerBoutique($intrus);

        $produit = $this->creerProduit($this->creerBoutique($vendeurA));

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client))->json('data.id');

        // Le middleware ne verifie que le role : sans controle de propriete,
        // n'importe quel vendeur marquerait « livree » la commande d'autrui.
        $this->putJson("/api/orders/{$id}/status", ['statut' => 'livree'], $this->entetes($intrus))
            ->assertStatus(403);

        $this->putJson("/api/orders/{$id}/status", ['statut' => 'livree'], $this->entetes($vendeurA))
            ->assertStatus(200);
    }

    public function test_l_avis_exige_un_achat_puis_une_livraison(): void
    {
        $client  = $this->creerUtilisateur('client');
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($vendeur));

        $avis = ['note' => 5, 'titre' => 'Superbe', 'commentaire' => 'Tres bien fini.'];

        // 1. Jamais commande.
        $this->postJson("/api/products/{$produit->id}/avis", $avis, $this->entetes($client))
            ->assertStatus(403);

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client))->json('data.id');

        // 2. Commande passee mais pas encore livree : on ne note pas un
        // produit qu'on n'a pas recu.
        $this->postJson("/api/products/{$produit->id}/avis", $avis, $this->entetes($client))
            ->assertStatus(403);

        $this->putJson("/api/orders/{$id}/status", ['statut' => 'livree'], $this->entetes($vendeur))
            ->assertStatus(200);

        // 3. Livree : l'avis passe.
        $this->postJson("/api/products/{$produit->id}/avis", $avis, $this->entetes($client))
            ->assertStatus(201)
            ->assertJsonPath('data.est_verifie', 1);

        // Un seul avis par couple (produit, utilisateur).
        $this->postJson("/api/products/{$produit->id}/avis", ['note' => 4], $this->entetes($client))
            ->assertStatus(409);
    }

    public function test_l_eligibilite_annonce_le_motif_avant_toute_saisie(): void
    {
        $client  = $this->creerUtilisateur('client');
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($vendeur));

        $url = "/api/products/{$produit->id}/avis/eligibilite";

        // Jamais commande.
        $this->getJson($url, $this->entetes($client))
            ->assertStatus(200)
            ->assertJsonPath('data.peut_deposer', false)
            ->assertJsonPath('data.motif', 'non_commande');

        $id = $this->postJson('/api/orders', [
            'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
        ], $this->entetes($client))->json('data.id');

        // Commande, pas livree.
        $this->getJson($url, $this->entetes($client))
            ->assertJsonPath('data.peut_deposer', false)
            ->assertJsonPath('data.motif', 'non_livre');

        $this->putJson("/api/orders/{$id}/status", ['statut' => 'livree'], $this->entetes($vendeur));

        // Livree.
        $this->getJson($url, $this->entetes($client))
            ->assertJsonPath('data.peut_deposer', true)
            ->assertJsonPath('data.motif', 'ok');

        $this->postJson("/api/products/{$produit->id}/avis", [
            'note' => 5, 'commentaire' => 'Parfait.',
        ], $this->entetes($client))->assertStatus(201);

        // Deja depose.
        $this->getJson($url, $this->entetes($client))
            ->assertJsonPath('data.peut_deposer', false)
            ->assertJsonPath('data.motif', 'deja_depose');
    }

    public function test_un_vendeur_ne_depose_pas_d_avis(): void
    {
        $vendeur = $this->creerUtilisateur('vendeur');
        $produit = $this->creerProduit($this->creerBoutique($vendeur));

        $this->getJson("/api/products/{$produit->id}/avis/eligibilite", $this->entetes($vendeur))
            ->assertJsonPath('data.peut_deposer', false)
            ->assertJsonPath('data.motif', 'vendeur');
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
