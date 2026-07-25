<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApiResponse;
use Tests\TestCase;

/**
 * Les trois enveloppes du contrat. Le front deballe `data` sans verifier :
 * une reponse hors enveloppe vide l'interface sans erreur en console, ce
 * qui en fait la regression la plus couteuse a diagnostiquer du projet.
 */
class ApiResponseTest extends TestCase
{
    public function test_le_succes_porte_toujours_un_message(): void
    {
        $charge = json_decode(ApiResponse::success(['id' => 1])->getContent(), true);

        $this->assertSame(true, $charge['success']);
        $this->assertSame('OK', $charge['message']);
        $this->assertSame(['id' => 1], $charge['data']);
    }

    public function test_data_est_omis_quand_il_n_y_a_rien_a_renvoyer(): void
    {
        $charge = json_decode(ApiResponse::success(null, 'Supprime.')->getContent(), true);

        $this->assertArrayNotHasKey('data', $charge);
        $this->assertSame('Supprime.', $charge['message']);
    }

    public function test_l_erreur_omet_details_quand_il_est_vide(): void
    {
        $sansDetail = json_decode(ApiResponse::error('Refus.', 403)->getContent(), true);
        $avecDetail = json_decode(ApiResponse::error('Refus.', 422, ['nom' => ['obligatoire']])->getContent(), true);

        $this->assertFalse($sansDetail['success']);
        $this->assertArrayNotHasKey('details', $sansDetail);
        $this->assertSame(['nom' => ['obligatoire']], $avecDetail['details']);
    }

    public function test_la_pagination_calcule_le_nombre_de_pages(): void
    {
        $charge = json_decode(ApiResponse::paginated([], 42, 1, 12)->getContent(), true);

        $this->assertSame(
            ['total' => 42, 'page' => 1, 'limit' => 12, 'total_pages' => 4],
            $charge['pagination']
        );
        // Forme paginee : pas de cle `message`, contrairement au succes simple.
        $this->assertArrayNotHasKey('message', $charge);
    }

    public function test_les_accents_et_les_slashs_ne_sont_pas_echappes(): void
    {
        $brut = ApiResponse::success(['nom' => 'Céramique', 'url' => 'http://a.fr/b'])->getContent();

        $this->assertStringContainsString('Céramique', $brut);
        $this->assertStringContainsString('http://a.fr/b', $brut);
        $this->assertStringNotContainsString('\\u00e9', $brut);
        $this->assertStringNotContainsString('\\/', $brut);
    }
}
