<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreeDesDonnees;
use Tests\TestCase;

class ProduitTest extends TestCase
{
    use CreeDesDonnees;
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Lecture et serialisation
    // ------------------------------------------------------------------

    public function test_la_liste_respecte_l_enveloppe_paginee(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $this->creerProduit($boutique);

        $this->getJson('/api/products?limit=12')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'nom', 'prix', 'images', 'boutique' => ['id', 'nom'], 'categories']],
                'pagination' => ['total', 'page', 'limit', 'total_pages'],
            ]);
    }

    public function test_le_prix_sort_en_chaine_et_les_booleens_en_0_1(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $produit  = $this->creerProduit($boutique, ['prix' => 225.00]);

        $charge = $this->getJson("/api/products/{$produit->id}")->json('data');

        // Le front formate `prix` tel quel : un nombre casserait l affichage.
        $this->assertIsString($charge['prix']);
        $this->assertSame('225.00', $charge['prix']);
        $this->assertSame(1, $charge['est_actif']);
        $this->assertSame(1, $charge['est_fait_main']);
        $this->assertIsInt($charge['stock']);
    }

    public function test_les_images_sortent_en_tableau_decode(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $produit  = $this->creerProduit($boutique, [
            'images' => ['http://localhost:8001/uploads/a.jpg', 'http://localhost:8001/uploads/b.jpg'],
        ]);

        $charge = $this->getJson("/api/products/{$produit->id}")->json('data');

        $this->assertIsArray($charge['images']);
        $this->assertCount(2, $charge['images']);
    }

    public function test_les_dates_sont_au_format_attendu(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $produit  = $this->creerProduit($boutique);

        $charge = $this->getJson("/api/products/{$produit->id}")->json('data');

        // Y-m-d H:i:s, et non l ISO 8601 par defaut d Eloquent.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $charge['created_at']);
    }

    public function test_produit_inexistant_renvoie_404_dans_l_enveloppe(): void
    {
        $this->getJson('/api/products/9999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Product not found.');
    }

    public function test_un_produit_desactive_n_apparait_pas(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $produit  = $this->creerProduit($boutique, ['est_actif' => 0]);

        $this->getJson("/api/products/{$produit->id}")->assertStatus(404);
        $this->assertCount(0, $this->getJson('/api/products')->json('data'));
    }

    // ------------------------------------------------------------------
    // Filtres
    // ------------------------------------------------------------------

    public function test_categorie_et_materiau_se_croisent_en_et(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));

        $ceramique = $this->creerCategorie('Céramique', 'ceramique');
        $argile    = $this->creerCategorie('Argile', 'argile');
        $bois      = $this->creerCategorie('Bois', 'bois');

        $ceramiqueArgile = $this->creerProduit($boutique, ['nom' => 'Vase argile']);
        $ceramiqueArgile->categories()->sync([$ceramique->id, $argile->id]);

        $ceramiqueBois = $this->creerProduit($boutique, ['nom' => 'Bol bois']);
        $ceramiqueBois->categories()->sync([$ceramique->id, $bois->id]);

        // « Ceramique » seul : les deux produits.
        $this->assertCount(2, $this->getJson('/api/products?categorie=ceramique')->json('data'));

        // « Ceramique » + « Argile » : l intersection, pas l union.
        $croise = $this->getJson('/api/products?categorie=ceramique&materiau=argile')->json('data');

        $this->assertCount(1, $croise);
        $this->assertSame('Vase argile', $croise[0]['nom']);
    }

    public function test_plusieurs_valeurs_d_un_meme_filtre_se_combinent_en_ou(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));

        $bijoux  = $this->creerCategorie('Bijoux', 'bijoux');
        $textile = $this->creerCategorie('Textile', 'textile');

        $this->creerProduit($boutique)->categories()->sync([$bijoux->id]);
        $this->creerProduit($boutique)->categories()->sync([$textile->id]);

        $this->assertCount(2, $this->getJson('/api/products?categorie=bijoux,textile')->json('data'));
    }

    public function test_filtre_par_prix_et_tri(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));

        $this->creerProduit($boutique, ['prix' => 10.00]);
        $this->creerProduit($boutique, ['prix' => 50.00]);
        $this->creerProduit($boutique, ['prix' => 90.00]);

        $this->assertCount(2, $this->getJson('/api/products?prix_min=40')->json('data'));
        $this->assertCount(1, $this->getJson('/api/products?prix_min=40&prix_max=60')->json('data'));

        $croissant = $this->getJson('/api/products?tri=prix_asc')->json('data');
        $this->assertSame('10.00', $croissant[0]['prix']);

        $decroissant = $this->getJson('/api/products?tri=prix_desc')->json('data');
        $this->assertSame('90.00', $decroissant[0]['prix']);
    }

    public function test_une_colonne_de_tri_inconnue_retombe_sur_le_defaut(): void
    {
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));
        $this->creerProduit($boutique);

        // `sort` vient de l URL : il ne doit jamais atteindre le SQL brut.
        $this->getJson('/api/products?sort=' . urlencode('prix; DROP TABLE produits'))
            ->assertStatus(200);

        $this->assertDatabaseCount('produits', 1);
    }

    // ------------------------------------------------------------------
    // Ecriture et controle d acces
    // ------------------------------------------------------------------

    public function test_un_client_ne_peut_pas_creer_de_produit(): void
    {
        $client   = $this->creerUtilisateur('client');
        $boutique = $this->creerBoutique($this->creerUtilisateur('vendeur'));

        $this->postJson('/api/products', [
            'nom' => 'Tentative', 'prix' => 10, 'boutique_id' => $boutique->id,
        ], $this->entetes($client))->assertStatus(403);
    }

    public function test_un_vendeur_ne_peut_pas_deposer_chez_un_autre(): void
    {
        $intrus         = $this->creerUtilisateur('vendeur');
        $this->creerBoutique($intrus);
        $boutiqueAutrui = $this->creerBoutique($this->creerUtilisateur('vendeur'));

        $this->postJson('/api/products', [
            'nom' => 'Intrusion', 'prix' => 10, 'boutique_id' => $boutiqueAutrui->id,
        ], $this->entetes($intrus))
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden. This boutique does not belong to you.');
    }

    public function test_le_vendeur_cree_un_produit_dans_sa_boutique(): void
    {
        $vendeur  = $this->creerUtilisateur('vendeur');
        $boutique = $this->creerBoutique($vendeur);

        $this->postJson('/api/products', [
            'nom'   => 'Bol en noyer ciré',
            'prix'  => 45,
            'stock' => 12,
            'boutique_id' => $boutique->id,
        ], $this->entetes($vendeur))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Product created.')
            ->assertJsonPath('data.nom', 'Bol en noyer ciré')
            // Le slug est translittere, sans accent.
            ->assertJsonPath('data.slug', 'bol-en-noyer-cire');
    }

    public function test_un_vendeur_ne_modifie_pas_le_produit_d_un_autre(): void
    {
        $proprietaire = $this->creerUtilisateur('vendeur');
        $produit      = $this->creerProduit($this->creerBoutique($proprietaire));
        $intrus       = $this->creerUtilisateur('vendeur');
        $this->creerBoutique($intrus);

        $this->putJson("/api/products/{$produit->id}", ['nom' => 'Detourne'], $this->entetes($intrus))
            ->assertStatus(403);

        $this->putJson("/api/products/{$produit->id}", ['nom' => 'Renomme'], $this->entetes($proprietaire))
            ->assertStatus(200)
            ->assertJsonPath('data.nom', 'Renomme');
    }

    public function test_l_administrateur_passe_outre_la_propriete(): void
    {
        $produit = $this->creerProduit($this->creerBoutique($this->creerUtilisateur('vendeur')));
        $admin   = $this->creerUtilisateur('admin');

        $this->deleteJson("/api/products/{$produit->id}", [], $this->entetes($admin))
            ->assertStatus(200);

        // Desactivation et non suppression : l historique des commandes
        // reste referencable.
        $this->assertDatabaseHas('produits', ['id' => $produit->id, 'est_actif' => 0]);
    }

    public function test_une_route_inconnue_renvoie_l_enveloppe_d_erreur(): void
    {
        $this->getJson('/api/nexiste-pas')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'error']);
    }
}
