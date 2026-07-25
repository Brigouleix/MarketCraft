<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Sonde publique. Forme historique volontairement conservee : elle
 * s'ecarte des trois enveloppes du contrat, mais des outils externes
 * peuvent deja la consommer telle quelle.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $baseAccessible = true;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            // Le detail n'est pas expose : il revelerait l'hote et le nom
            // de la base a un appelant anonyme.
            $baseAccessible = false;
        }

        return ApiResponse::raw([
            'success'  => true,
            'service'  => 'MarketCraft API',
            'version'  => '2.0.0',
            'time'     => date('c'),
            'database' => $baseAccessible ? 'up' : 'down',
        ], $baseAccessible ? 200 : 503);
    }
}
