<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force la negociation de contenu en JSON.
 *
 * Sans cela, un client qui n'envoie pas d'en-tete Accept recoit la page
 * d'erreur HTML de Laravel au lieu de l'enveloppe d'erreur du contrat.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (! $response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        }

        return $response;
    }
}
