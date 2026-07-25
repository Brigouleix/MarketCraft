<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite exiges par le cahier des charges
 * (OWASP — Security Misconfiguration).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // L'API n'a aucune raison d'etre affichee dans une iframe.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Empeche le navigateur de deviner un type MIME : une reponse JSON
        // ne doit jamais etre interpretee comme du HTML ou du JavaScript.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Ne transmet l'URL complete qu'aux requetes de meme origine.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // L'API ne sert aucun document : tout contenu actif est interdit.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        // Aucune API navigateur n'est requise.
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS n'a de sens que derriere HTTPS : l'envoyer en clair sur
        // localhost verrouillerait le domaine pour rien.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
