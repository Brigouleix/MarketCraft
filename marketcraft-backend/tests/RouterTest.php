<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function test_route_get_enregistree_et_matchee(): void
    {
        $called = false;

        $this->router->get('/test', function (array $params) use (&$called) {
            $called = true;
        });

        // Capturer la sortie et le code HTTP
        ob_start();
        $this->router->dispatch('GET', '/test');
        ob_end_clean();

        $this->assertTrue($called);
    }

    public function test_route_avec_parametre_dynamique(): void
    {
        $capturedParams = [];

        $this->router->get('/produits/:id', function (array $params) use (&$capturedParams) {
            $capturedParams = $params;
        });

        ob_start();
        $this->router->dispatch('GET', '/produits/42');
        ob_end_clean();

        $this->assertSame('42', $capturedParams['id']);
    }

    public function test_route_inconnue_retourne_404(): void
    {
        ob_start();
        $this->router->dispatch('GET', '/route-inexistante');
        ob_end_clean();

        $this->assertSame(404, http_response_code());
    }

    public function test_methode_incorrecte_retourne_405(): void
    {
        $this->router->get('/only-get', function (array $p) {});

        ob_start();
        $this->router->dispatch('POST', '/only-get');
        ob_end_clean();

        $this->assertSame(405, http_response_code());
    }
}
