<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Amorce l'application pour la suite de tests.
     *
     * La visibilite doit rester `public` : la classe parente la declare
     * ainsi, et PHP refuse qu'une classe fille restreigne l'acces a une
     * methode heritee. Pas de type de retour declare non plus, pour rester
     * compatible avec la signature du framework quelle que soit sa version.
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
