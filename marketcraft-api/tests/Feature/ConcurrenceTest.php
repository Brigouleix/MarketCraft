<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Boutique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreeDesDonnees;
use Tests\TestCase;

/**
 * Analyse concurrentielle du catalogue d'un vendeur.
 *
 * Ajout hors perimetre du cahier des charges, assume comme tel.
 *
 * Ce que ces tests garantissent avant tout : **les chiffres sont calcules,
 * jamais generes**. La suite tourne sans cle IA, donc sur le repli
 * statistique — et c'est precisement la partie qui doit rester exacte.
 */
class ConcurrenceTest extends TestCase
{
    use CreeDesDonnees;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake();
    }

    // ------------------------------------------------------------------
    // Acces
    // ------------------------------------------------------------------

    public function test_l_analyse_exige_une_authentification(): void
    {
        $this->getJson('/api/dashboard/concurrence')->assertStatus(401);
    }

    public function test_un_client_n_y_a_pas_acces(): void
    {
        $client = $this->creerUtilisateur('client');

        $this->getJson('/api/dashboard/concurrence', $this->entetes($client))
            ->assertStatus(403);
    }

    public function test_un_vendeur_sans_boutique_recoit_un_message_clair(): void
    {
        $vendeur = $this->creerUtilisateur('vendeur');

        $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur))
            ->assertStatus(404)
            ->assertJsonPath('error', "Vous n'avez pas encore de boutique.");
    }

    public function test_un_vendeur_ne_voit_que_sa_propre_boutique(): void
    {
        // Un vendeur qui passerait ?boutique_id=<autre> obtiendrait sinon le
        // positionnement tarifaire complet de son concurrent.
        [$vendeur, $boutique] = $this->boutiqueAvecCatalogue(200.00);
        $autre = $this->creerBoutique($this->creerUtilisateur('vendeur'), ['nom' => 'Rivale']);

        $reponse = $this->getJson(
            "/api/dashboard/concurrence?boutique_id={$autre->id}",
            $this->entetes($vendeur)
        );

        $reponse->assertStatus(200)->assertJsonPath('data.boutique.id', (int) $boutique->id);
    }

    public function test_un_administrateur_peut_inspecter_une_boutique_donnee(): void
    {
        [, $boutique] = $this->boutiqueAvecCatalogue(200.00);
        $admin = $this->creerUtilisateur('admin');

        $this->getJson("/api/dashboard/concurrence?boutique_id={$boutique->id}", $this->entetes($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.boutique.id', (int) $boutique->id);
    }

    // ------------------------------------------------------------------
    // Exactitude du calcul
    // ------------------------------------------------------------------

    public function test_la_mediane_et_l_ecart_sont_exacts(): void
    {
        // Concurrents a 100, 150, 250 et 300 : mediane = (150+250)/2 = 200.
        // Produit du vendeur a 200 -> ecart nul, donc aligne.
        [$vendeur] = $this->boutiqueAvecCatalogue(200.00);

        $ligne = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur))
            ->json('data.produits.0');

        $this->assertSame(4, $ligne['concurrents']);
        $this->assertSame('200.00', $ligne['marche']['mediane']);
        $this->assertSame('100.00', $ligne['marche']['min']);
        $this->assertSame('300.00', $ligne['marche']['max']);
        // JSON ne distingue pas 0 de 0.0 : on compare des valeurs, pas des types.
        $this->assertEqualsWithDelta(0.0, $ligne['ecart_median_pct'], 0.01);
        $this->assertSame('dans_la_moyenne', $ligne['positionnement']);
    }

    public function test_un_prix_tres_inferieur_est_signale_comme_tel(): void
    {
        // 50 face a une mediane de 200 : -75 %.
        [$vendeur] = $this->boutiqueAvecCatalogue(50.00);

        $ligne = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur))
            ->json('data.produits.0');

        $this->assertEqualsWithDelta(-75.0, $ligne['ecart_median_pct'], 0.01);
        $this->assertSame('moins_cher', $ligne['positionnement']);
    }

    public function test_un_prix_tres_superieur_est_signale_comme_tel(): void
    {
        // 400 face a une mediane de 200 : +100 %.
        [$vendeur] = $this->boutiqueAvecCatalogue(400.00);

        $ligne = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur))
            ->json('data.produits.0');

        $this->assertEqualsWithDelta(100.0, $ligne['ecart_median_pct'], 0.01);
        $this->assertSame('plus_cher', $ligne['positionnement']);
    }

    public function test_un_ecart_faible_reste_considere_comme_aligne(): void
    {
        // 220 face a 200 : +10 %, sous le seuil de 15 %.
        [$vendeur] = $this->boutiqueAvecCatalogue(220.00);

        $ligne = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur))
            ->json('data.produits.0');

        $this->assertEqualsWithDelta(10.0, $ligne['ecart_median_pct'], 0.01);
        $this->assertSame('dans_la_moyenne', $ligne['positionnement']);
    }

    public function test_un_produit_sans_equivalent_est_distingue_d_un_produit_bon_marche(): void
    {
        $vendeur  = $this->creerUtilisateur('vendeur');
        $boutique = $this->creerBoutique($vendeur);
        $inedit   = $this->creerCategorie('Vitrail', 'vitrail');

        $produit = $this->creerProduit($boutique, ['nom' => 'Vitrail sur mesure', 'prix' => 480.00]);
        $produit->categories()->sync([$inedit->id]);

        $reponse = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur));

        $ligne = $reponse->json('data.produits.0');

        // Sans comparable, aucun ecart n'a de sens : le champ reste null
        // plutot que de valoir zero, qui se lirait « aligne sur le marche ».
        $this->assertNull($ligne['marche']);
        $this->assertNull($ligne['ecart_median_pct']);
        $this->assertSame('sans_comparable', $ligne['positionnement']);
        $this->assertSame(1, $reponse->json('data.resume.sans_comparable'));
    }

    public function test_les_produits_de_la_meme_boutique_ne_sont_pas_des_concurrents(): void
    {
        [$vendeur, $boutique] = $this->boutiqueAvecCatalogue(200.00);

        $poterie = Categorie::query()->where('slug', 'poterie')->first();

        // Deux articles supplementaires chez le meme vendeur : ils ne
        // doivent pas gonfler le nombre de concurrents.
        foreach ([90.00, 110.00] as $prix) {
            $p = $this->creerProduit($boutique, ['prix' => $prix]);
            $p->categories()->sync([$poterie->id]);
        }

        $ligne = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur))
            ->json('data.produits');

        $this->assertSame(4, collect($ligne)->firstWhere('nom', 'Piece analysee')['concurrents']);
    }

    // ------------------------------------------------------------------
    // Repli
    // ------------------------------------------------------------------

    public function test_sans_cle_la_synthese_statistique_prend_le_relais(): void
    {
        [$vendeur] = $this->boutiqueAvecCatalogue(200.00);

        $reponse = $this->getJson('/api/dashboard/concurrence', $this->entetes($vendeur));

        $reponse->assertStatus(200)
            ->assertJsonPath('data.ia_active', false)
            ->assertJsonPath('data.source', 'statistiques');

        // La synthese de secours doit rester lisible : c'est elle que verra
        // le vendeur si la cle expire.
        $this->assertNotEmpty($reponse->json('data.synthese'));
        $this->assertStringContainsString('produit', $reponse->json('data.synthese'));

        // Aucun conseil n'est invente en l'absence de modele.
        $this->assertNull($reponse->json('data.produits.0.conseil'));
    }

    // ------------------------------------------------------------------
    // Fabrique
    // ------------------------------------------------------------------

    /**
     * Une boutique avec un produit au prix donne, et quatre concurrents
     * repartis a 100, 150, 250 et 300 dans la meme categorie.
     *
     * @return array{0: \App\Models\User, 1: Boutique}
     */
    private function boutiqueAvecCatalogue(float $prixDuVendeur): array
    {
        $poterie = $this->creerCategorie('Poterie', 'poterie');

        $vendeur  = $this->creerUtilisateur('vendeur');
        $boutique = $this->creerBoutique($vendeur, ['nom' => 'Boutique analysee']);

        $produit = $this->creerProduit($boutique, [
            'nom'  => 'Piece analysee',
            'prix' => $prixDuVendeur,
        ]);
        $produit->categories()->sync([$poterie->id]);

        foreach ([100.00, 150.00, 250.00, 300.00] as $i => $prix) {
            $rivale = $this->creerBoutique(
                $this->creerUtilisateur('vendeur'),
                ['nom' => "Rivale {$i}"]
            );

            $concurrent = $this->creerProduit($rivale, ['prix' => $prix]);
            $concurrent->categories()->sync([$poterie->id]);
        }

        return [$vendeur, $boutique];
    }
}
