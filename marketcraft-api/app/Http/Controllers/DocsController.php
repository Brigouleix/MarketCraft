<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Documentation interactive de l'API.
 *
 * `GET /api/docs`              — page Swagger UI (HTML)
 * `GET /api/docs/openapi.yaml` — la spécification OpenAPI brute
 *
 * La CSP globale (`default-src 'none'`) bloquerait Swagger UI : une exception
 * ciblée sur le chemin `api/docs*` est prévue dans le middleware
 * App\Http\Middleware\SecurityHeaders.
 */
class DocsController extends Controller
{
    public function page(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MarketCraft API — Documentation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
  <style>
    body { margin: 0; background: #fafafa; }
    .topbar { display: none; }
    #brand { background: #8B4513; color: #fff; padding: 12px 20px; font-family: Georgia, serif; font-size: 18px; font-weight: bold; }
  </style>
</head>
<body>
  <div id="brand">MarketCraft — Documentation de l'API</div>
  <div id="swagger-ui"></div>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = function () {
      window.ui = SwaggerUIBundle({
        url: '/api/docs/openapi.yaml',
        dom_id: '#swagger-ui',
        deepLinking: true,
        docExpansion: 'list',
        defaultModelsExpandDepth: 0,
      });
    };
  </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function spec(): Response
    {
        $chemin = base_path('openapi.yaml');

        if (! is_file($chemin)) {
            return response("openapi.yaml introuvable.", 404)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        return response((string) file_get_contents($chemin), 200)
            ->header('Content-Type', 'application/yaml; charset=utf-8');
    }
}
