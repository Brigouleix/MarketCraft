<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification par jeton JWT (Authorization: Bearer <token>).
 *
 * En cas de succes, l'utilisateur est resolu depuis la base et attache a
 * la requete : `$request->user()` fonctionne alors comme dans n'importe
 * quelle application Laravel, et le payload brut reste accessible via
 * `$request->attributes->get('jwt')`.
 */
class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = Jwt::fromHeader($request->header('Authorization'));

        if ($token === null) {
            return ApiResponse::error('Unauthorized. A valid Bearer token is required.', 401);
        }

        $payload = Jwt::decode($token);

        if ($payload === null) {
            return ApiResponse::error('Unauthorized. A valid Bearer token is required.', 401);
        }

        // Un jeton de rafraichissement ne donne pas acces aux ressources :
        // il ne sert qu'a obtenir un nouveau jeton d'acces.
        if (($payload['type'] ?? null) === 'refresh') {
            return ApiResponse::error('Refresh tokens cannot be used to access resources.', 401);
        }

        $user = User::query()->find((int) ($payload['sub'] ?? 0));

        // Un compte desactive depuis l'emission du jeton doit perdre l'acces
        // immediatement : le JWT seul ne suffit pas comme source de verite.
        if ($user === null || (int) $user->est_actif !== 1) {
            return ApiResponse::error('Unauthorized. Account is inactive or no longer exists.', 401);
        }

        $request->attributes->set('jwt', $payload);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
