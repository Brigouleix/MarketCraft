<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// 1. Chargement de l'environnement (.env manuel, sans dépendance)
// ---------------------------------------------------------------------------
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Autoloader Composer
// ---------------------------------------------------------------------------
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Dependencies not installed. Run composer install.']);
    exit;
}
require_once $autoloader;

// ---------------------------------------------------------------------------
// 3. En-têtes CORS
// ---------------------------------------------------------------------------
$allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?: '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Répondre immédiatement aux pré-vols OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// 4. Récupération de la méthode et de l'URI
// ---------------------------------------------------------------------------
$method = strtoupper($_SERVER['REQUEST_METHOD']);

// Support du "method override" via le header X-HTTP-Method-Override ou _method POST
if ($method === 'POST') {
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
    if (empty($override)) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $override = $body['_method'] ?? '';
    }
    if (in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
        $method = strtoupper($override);
    }
}

// Nettoyer l'URI (supprimer le query string et le préfixe /api)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$requestUri = '/' . ltrim(substr($requestUri, strlen($scriptDir)), '/');

// Supprimer le préfixe /api s'il est présent (le frontend appelle http://host/api/*)
if (str_starts_with($requestUri, '/api/') || $requestUri === '/api') {
    $requestUri = substr($requestUri, 4) ?: '/';
}

// ---------------------------------------------------------------------------
// 5. Dispatch vers le Router
// ---------------------------------------------------------------------------
use App\Core\Router;
use App\Core\Logger;

$router = new Router();

require_once dirname(__DIR__) . '/routes/web.php';

$_requestStart = microtime(true);

$router->dispatch($method, $requestUri);

// Log every request after dispatch (status code is already sent)
$statusCode  = http_response_code();
$durationMs  = (microtime(true) - $_requestStart) * 1000;
Logger::request($method, $requestUri, is_int($statusCode) ? $statusCode : 200, $durationMs);
