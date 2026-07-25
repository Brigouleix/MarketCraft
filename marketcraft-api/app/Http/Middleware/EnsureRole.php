<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controle de role (OWASP — Broken Access Control).
 *
 * S'emploie apres `jwt` :  ->middleware(['jwt', 'role:vendeur,admin'])
 *
 * Le controle vit dans le middleware plutot que dans chaque controleur :
 * une route ajoutee sans role explicite est ainsi visible en relecture du
 * fichier de routes, au lieu d'etre silencieusement ouverte a tous.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthorized. A valid Bearer token is required.', 401);
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return ApiResponse::error('Forbidden. Insufficient role.', 403);
        }

        return $next($request);
    }
}
