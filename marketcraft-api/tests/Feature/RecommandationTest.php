<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\LigneCommande;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreeDesDonnees;
use Tests\TestCase;

/**
 * Module IA — recommandation personnalisee (option C du cahier des charges).
 *
 * La suite tourne avec AI_API_KEY vide (voir phpunit.xml) : le repli par
 * similarite est donc le chemin nominal ici, et c'est voulu. C'est lui qui
 * doit fonctionner le jour ou la cle expire ou le reseau tombe — autrement
 * dit, le jour de la soutenance.
 *
 * Aucun appel reseau reel n'est emis : Http::fake() intercepte tout.
 */
class RecommandationTest extends TestCase
{
    use CreeDesDonnees;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Filet de securite : si une configuration laissait passer une cle,
        // le test ne doit pas appeler un fournisseur payant pour autant.
        Http::preventStrayRequests();
        Http::fake();
    }

    // ------------------------------------------------------------------
    // Recommandations depuis une fiche produit
    // ------------------------------------------------------------------

    public function test_les_deux_routes_de_recommandation_repondent_pareil(): void
    {
        [$produit] = $this->catalogue();

        $similar = $this->getJson("/api/products/{$produit->id}/similar");
        $recos   = $this->getJson("/api/products/{$produit->id}/recommendations");

        $similar->assertStatus(200);
        $recos->assertStatus(200);

        // Le front appelle /similar, le brief documente /recommendations :
        // les deux doivent servir la meme chose.
        $this->assertSame($similar->json('data.products'), $recos->json('data.products'));
    }

    public function test_sans_cle_le_repli_est_signale_et_non_masque(): void
    {
        [$produit] = $this->catalogue();

        $reponse = $this->getJson("/api/products/{$produit->id}/similar");

        // `ia_active` a false permet au front d'afficher « sélection par
        // similarité » plutot que de faire passer un repli pour une vraie
        // recommandation.
        $reponse->assertStatus(200)
            ->assertJsonPath('data.ia_active', false)
            ->assertJsonPath('data.source', 'similarite');

        $this->assertNotEmpty($reponse->json('data.products'));
    }

    public function test_le_produit_consulte_ne_se_recommande_pas_lui_meme(): void
    {
        [$produit] = $this->catalogue();

        $ids = array_column($this->getJson("/api/products/{$produit->id}/similar")->json('data.products'), 'id');

        $this->assertNotContains((int) $produit->id, $ids);
    }

    public function test_les_deux_cles_products_et_produits_sont_exposees(): void
    {
        [$produit] = $this->catalogue();

        $reponse = $this->getJson("/api/products/{$produit->id}/similar");

        // Doublon assume : ProductDetailPage lit `products`, d'autres vues
        // lisent `produits`.
        $this->assertSame($reponse->json('data.products'), $reponse->json('data.produits'));
    }

    public function test_un_produit_inexistant_renvoie_404(): void
    {
        $this->getJson('/api/products/9999/similar')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Product not found.');
    }

    // ------------------------------------------------------------------
    // Recommandations depuis le panier
    // ------------------------------------------------------------------

    public function test_le_panier_alimente_les_recommandations(): void
    {
        [$produit, $autres] = $this->catalogue();

        $reponse = $this->postJson('/api/cart/recommendations', [
            'produit_ids' => [$produit->id],
        ]);

        $reponse->assertStatus(200)->assertJsonPath('data.ia_active', false);

        $ids = array_column($reponse->json('data.products'), 'id');

        // Ce qui est deja au panier n'a pas a etre suggere.
        $this->assertNotContains((int) $produit->id, $ids);
    }

    public function test_un_panier_vide_est_refuse(): void
    {
        $this->postJson('/api/cart/recommendations', ['produit_ids' => []])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Recommandations depuis l'historique d'achat
    // ------------------------------------------------------------------

    public function test_l_historique_exige_une_authentification(): void
    {
        $this->getJson('/api/me/recommendations')->assertStatus(401);
    }

    public function test_un_client_sans_commande_recoit_une_liste_vide(): void
    {
        $client = $this->creerUtilisateur('client');

        // Liste vide et non erreur : le composant front ne rend alors rien,
        // plutot qu'un encart « Suggestions » desesperement creux.
        $this->getJson('/api/me/recommendations', $this->entetes($client))
            ->assertStatus(200)
            ->assertJsonPath('data.source', 'historique_vide')
            ->assertJsonPath('data.products', []);
    }

    public function test_les_suggestions_excluent_ce_qui_a_deja_ete_achete(): void
    {
        [$achete] = $this->catalogue();
        $client = $this->creerUtilisateur('client');

        $this->passerCommande($client->id, (int) $achete->id, 'livree');

        $reponse = $this->getJson('/api/me/recommendations', $this->entetes($client));

        $reponse->assertStatus(200);

        $ids = array_column($reponse->json('data.products'), 'id');

        $this->assertNotEmpty($ids, 'Le catalogue doit fournir des candidats.');
        $this->assertNotContains((int) $achete->id, $ids);
    }

    public function test_une_commande_annulee_ne_compte_pas_comme_un_achat(): void
    {
        [$achete] = $this->catalogue();
        $client = $this->creerUtilisateur('client');

        $this->passerCommande($client->id, (int) $achete->id, 'annulee');

        // Une commande annulee ne dit rien d'un gout, seulement d'un
        // renoncement : elle ne doit pas nourrir les recommandations.
        $this->getJson('/api/me/recommendations', $this->entetes($client))
            ->assertStatus(200)
            ->assertJsonPath('data.source', 'historique_vide');
    }

    // ------------------------------------------------------------------
    // Fabriques
    // ------------------------------------------------------------------

    /**
     * Un petit catalogue coherent : plusieurs produits partageant une
     * categorie, dans une gamme de prix voisine.
     *
     * @return array{0: \App\Models\Produit, 1: array<int, \App\Models\Produit>}
     */
    private function catalogue(): array
    {
        $boutique  = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $poterie   = $this->creerCategorie('Poterie', 'poterie');
        $ceramique = $this->creerCategorie('Céramique', 'ceramique');

        $principal = $this->creerProduit($boutique, ['nom' => 'Vase tourné', 'prix' => 55.00]);
        $principal->categories()->sync([$poterie->id, $ceramique->id]);
        $principal->setRelation('categorie', $poterie);

        $autres = [];

        foreach ([['Bol émaillé', 48.00], ['Pichet vernissé', 62.00], ['Coupelle', 39.00]] as [$nom, $prix]) {
            $p = $this->creerProduit($boutique, ['nom' => $nom, 'prix' => $prix]);
            $p->categories()->sync([$poterie->id, $ceramique->id]);
            $autres[] = $p;
        }

        return [$principal->fresh(), $autres];
    }

    private function passerCommande(int $clientId, int $produitId, string $statut): void
    {
        $commande = Commande::create([
            'utilisateur_id' => $clientId,
            'statut'         => $statut,
            'montant_total'  => 55.00,
            'frais_livraison'=> 0,
        ]);

        LigneCommande::create([
            'commande_id'   => $commande->id,
            'produit_id'    => $produitId,
            'quantite'      => 1,
            'prix_unitaire' => 55.00,
            'nom_produit'   => 'Vase tourné',
        ]);
    }
}
