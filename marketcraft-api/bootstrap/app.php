<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleCors;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withCommands([__DIR__ . '/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware) {
        // Aucune session, aucun cookie, aucun CSRF : l'API est sans etat.
        //
        // Pile GLOBALE et non groupe « api » : le pre-vol OPTIONS envoye par
        // le navigateur ne correspond a aucune route declaree. Attache au
        // groupe, le middleware CORS ne serait jamais atteint et le pre-vol
        // repondrait 405 — le navigateur annulerait alors la vraie requete,
        // sans erreur exploitable cote client. La pile globale s'execute
        // avant le routage.
        $middleware->prepend([
            HandleCors::class,
            ForceJsonResponse::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'jwt'  => JwtAuthenticate::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Toute exception qui remonte jusqu'ici doit sortir dans l'enveloppe
        // d'erreur du contrat. Une reponse au format Laravel par defaut
        // ({"message": "..."}) laisserait le front sans champ `error`.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error('Validation failed.', 422, $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthorized. A valid Bearer token is required.', 401);
            }

            if ($e instanceof ModelNotFoundException) {
                return ApiResponse::error('Resource not found.', 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return ApiResponse::error(
                    "Method {$request->method()} not allowed for this endpoint.",
                    405
                );
            }

            if ($e instanceof NotFoundHttpException) {
                $uri = '/' . ltrim(preg_replace('#^/?api#', '', $request->path()), '/');

                return ApiResponse::error("Route not found: {$request->method()} {$uri}", 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status  = $e->getStatusCode();
                $message = $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.';

                return ApiResponse::error($message, $status);
            }

            // Le detail d'une erreur interne n'est expose qu'en developpement :
            // en production il revelerait des chemins de fichiers et du SQL.
            report($e);

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Internal server error.';

            return ApiResponse::error($message, 500);
        });
    })
    ->create();
