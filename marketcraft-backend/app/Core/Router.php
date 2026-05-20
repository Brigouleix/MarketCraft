<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<string, array<array{pattern: string, handler: callable, middleware: string[]}>> */
    private array $routes = [];

    private array $supportedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    // ------------------------------------------------------------------
    // Enregistrement des routes
    // ------------------------------------------------------------------

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $pattern = $this->pathToPattern($path);
        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    // ------------------------------------------------------------------
    // Conversion chemin → regex
    // ------------------------------------------------------------------

    private function pathToPattern(string $path): string
    {
        // :id, :slug, etc. → capture nommée
        $pattern = preg_replace('/\/:([a-zA-Z_][a-zA-Z0-9_]*)/', '/(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#u';
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);

        if (!in_array($method, $this->supportedMethods, true)) {
            $this->sendError('Method not allowed.', 405);
            return;
        }

        // Supprimer le slash final (sauf racine)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extraire les paramètres nommés
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Appliquer les middlewares
                foreach ($route['middleware'] as $mw) {
                    if (!$this->runMiddleware($mw)) {
                        return; // Le middleware a déjà envoyé une réponse
                    }
                }

                // Appeler le handler
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // Vérifier si l'URI existe pour d'autres méthodes
        foreach ($this->routes as $routeMethod => $routeList) {
            if ($routeMethod === $method) {
                continue;
            }
            foreach ($routeList as $route) {
                if (preg_match($route['pattern'], $uri)) {
                    $this->sendError("Method {$method} not allowed for this endpoint.", 405);
                    return;
                }
            }
        }

        $this->sendError("Route not found: {$method} {$uri}", 404);
    }

    // ------------------------------------------------------------------
    // Middleware
    // ------------------------------------------------------------------

    private function runMiddleware(string $name): bool
    {
        return match ($name) {
            'auth'  => Auth::authenticate(),
            default => true,
        };
    }

    // ------------------------------------------------------------------
    // Appel du handler
    // ------------------------------------------------------------------

    private function callHandler(callable|array $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (is_string($class)) {
                $instance = new $class();
                $instance->$method($params);
                return;
            }
        }

        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }

        $this->sendError('Invalid route handler configuration.', 500);
    }

    // ------------------------------------------------------------------
    // Réponse d'erreur
    // ------------------------------------------------------------------

    private function sendError(string $message, int $code): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error'   => $message,
        ], JSON_UNESCAPED_UNICODE);
    }
}
