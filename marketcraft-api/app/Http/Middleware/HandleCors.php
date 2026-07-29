<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS. Les origines autorisees viennent de ALLOWED_ORIGINS, jamais du code.
 *
 * « * » est tolere en developpement ; en production la liste doit etre
 * explicite, sinon n'importe quel site peut appeler l'API depuis le
 * navigateur d'un utilisateur connecte.
 */
class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        // Le pre-vol ne doit pas traverser la pile applicative : il repond
        // immediatement, sinon un OPTIONS sur une route protegee renvoie 401
        // et le navigateur annule la requete reelle.
        $response = $request->getMethod() === 'OPTIONS'
            ? response('', 204)
            : $next($request);

        $allowed = config('marketcraft.allowed_origins', []);
        $origin  = (string) $request->headers->get('Origin', '');

        if (in_array('*', $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            // Sans Vary, un cache partage servirait la reponse d'une origine
            // a une autre.
            $response->headers->set('Vary', 'Origin');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '86400');

        // Les identifiants ne sont pas compatibles avec une origine « * » :
        // le navigateur rejetterait la reponse.
        if (! in_array('*', $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
