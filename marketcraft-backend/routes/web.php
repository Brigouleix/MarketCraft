<?php

declare(strict_types=1);

/**
 * Routes MarketCraft
 *
 * $router est une instance de App\Core\Router injectée depuis public/index.php.
 * Chaque route peut recevoir un tableau de middlewares en 3ème paramètre.
 * Middleware disponibles : 'auth' (vérifie le JWT Bearer token).
 */

use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\BoutiqueController;
use App\Controllers\OrderController;
use App\Controllers\AvisController;
use App\Controllers\DashboardController;

// =========================================================================
// AUTH
// =========================================================================

// POST /auth/register  – Inscription
$router->post('/api/auth/register', [AuthController::class, 'register']);

// POST /auth/login     – Connexion
$router->post('/api/auth/login', [AuthController::class, 'login']);

// GET  /auth/me        – Profil de l'utilisateur connecté
$router->get('/auth/me', [AuthController::class, 'me'], ['auth']);

// PUT  /auth/me        – Mise à jour du profil
$router->put('/auth/me', [AuthController::class, 'updateMe'], ['auth']);

// =========================================================================
// PRODUITS
// =========================================================================

// GET    /products         – Liste (pagination + filtres)
$router->get('/products', [ProductController::class, 'index']);

// GET    /products/:id     – Détail d'un produit
$router->get('/products/:id', [ProductController::class, 'show']);

// POST   /products         – Créer un produit (JWT + vendeur/admin)
$router->post('/products', [ProductController::class, 'store'], ['auth']);

// PUT    /products/:id     – Modifier un produit (JWT + owner)
$router->put('/products/:id', [ProductController::class, 'update'], ['auth']);

// DELETE /products/:id     – Supprimer un produit (JWT + owner)
$router->delete('/products/:id', [ProductController::class, 'destroy'], ['auth']);

// =========================================================================
// BOUTIQUES
// =========================================================================

// GET    /boutiques        – Liste des boutiques actives
$router->get('/boutiques', [BoutiqueController::class, 'index']);

// GET    /boutiques/:id    – Détail avec produits
$router->get('/boutiques/:id', [BoutiqueController::class, 'show']);

// POST   /boutiques        – Créer une boutique (JWT)
$router->post('/boutiques', [BoutiqueController::class, 'store'], ['auth']);

// PUT    /boutiques/:id    – Modifier (JWT + owner)
$router->put('/boutiques/:id', [BoutiqueController::class, 'update'], ['auth']);

// DELETE /boutiques/:id    – Supprimer (JWT + owner ou admin)
$router->delete('/boutiques/:id', [BoutiqueController::class, 'destroy'], ['auth']);

// =========================================================================
// COMMANDES
// =========================================================================

// GET    /orders           – Mes commandes (JWT)
$router->get('/orders', [OrderController::class, 'index'], ['auth']);

// GET    /orders/:id       – Détail d'une commande (JWT + owner)
$router->get('/orders/:id', [OrderController::class, 'show'], ['auth']);

// POST   /orders           – Passer une commande (JWT)
$router->post('/orders', [OrderController::class, 'store'], ['auth']);

// PUT/PATCH /orders/:id/status – Changer le statut (JWT + vendeur/admin)
$router->put('/orders/:id/status', [OrderController::class, 'updateStatus'], ['auth']);
$router->patch('/orders/:id/status', [OrderController::class, 'updateStatus'], ['auth']);

// DELETE /orders/:id       – Annuler une commande (JWT + owner)
$router->delete('/orders/:id', [OrderController::class, 'destroy'], ['auth']);

// =========================================================================
// AVIS
// =========================================================================

// GET    /products/:id/avis  – Avis d'un produit (public)
$router->get('/products/:id/avis', [AvisController::class, 'indexByProduct']);

// POST   /products/:id/avis  – Poster un avis (JWT)
$router->post('/products/:id/avis', [AvisController::class, 'store'], ['auth']);

// DELETE /avis/:id           – Supprimer un avis (JWT + owner/admin)
$router->delete('/avis/:id', [AvisController::class, 'destroy'], ['auth']);

// =========================================================================
// DASHBOARD VENDEUR
// =========================================================================

// GET /dashboard/stats   – KPIs du vendeur connecté (JWT + vendeur/admin)
$router->get('/dashboard/stats', [DashboardController::class, 'stats'], ['auth']);

// GET /vendor/orders     – Commandes liées aux boutiques du vendeur (JWT + vendeur/admin)
$router->get('/vendor/orders', [DashboardController::class, 'vendorOrders'], ['auth']);

// =========================================================================
// RECHERCHE
// =========================================================================

use App\Controllers\SearchController;

// POST /search/ai  – Recherche IA (public)
$router->post('/search/ai', [SearchController::class, 'aiSearch']);

// GET  /search     – Recherche simple (public)
$router->get('/search', [SearchController::class, 'search']);

// =========================================================================
// ROUTE DE SANTÉ
// =========================================================================

$router->get('/health', function (array $params) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'service' => 'MarketCraft API',
        'version' => '1.0.0',
        'time'    => date('c'),
    ], JSON_UNESCAPED_UNICODE);
});
