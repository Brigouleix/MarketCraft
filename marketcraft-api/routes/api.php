<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyseConcurrentielleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvisController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\RecommandationController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de l'API MarketCraft
|--------------------------------------------------------------------------
|
| Le prefixe /api est applique par bootstrap/app.php (apiPrefix: 'api').
| Les chemins ci-dessous s'ecrivent donc sans lui.
|
| Middlewares :
|   jwt                    jeton Bearer valide et compte actif
|   role:vendeur,admin     controle de role, a placer apres jwt
|
| Le controle de role vit ici plutot que dans les controleurs : une route
| ajoutee sans middleware saute aux yeux a la relecture de ce fichier,
| alors qu'un controle oublie au fond d'une methode passe inapercu.
|
*/

// =========================================================================
// SANTE
// =========================================================================

Route::get('/health', HealthController::class);

// =========================================================================
// DOCUMENTATION (Swagger UI + spécification OpenAPI)
// =========================================================================

Route::get('/docs', [DocsController::class, 'page']);
Route::get('/docs/openapi.yaml', [DocsController::class, 'spec']);

// =========================================================================
// AUTHENTIFICATION
// =========================================================================

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    // Defi captcha, exige a partir du 3e echec de connexion.
    Route::get('/captcha', [AuthController::class, 'captcha']);

    Route::middleware('jwt')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateMe']);
        Route::delete('/me', [AuthController::class, 'deleteMe']);
    });
});

// =========================================================================
// PRODUITS
// =========================================================================

Route::get('/products', [ProduitController::class, 'index']);

// Les routes litterales precedent /products/{id} : sans cela « similar »
// serait capture comme identifiant.
Route::get('/products/{id}/avis', [AvisController::class, 'indexByProduct'])->whereNumber('id');
Route::get('/products/{id}/similar', [RecommandationController::class, 'pourProduit'])->whereNumber('id');
Route::get('/products/{id}/recommendations', [RecommandationController::class, 'pourProduit'])->whereNumber('id');
Route::get('/products/{id}', [ProduitController::class, 'show'])->whereNumber('id');

Route::middleware('jwt')->group(function () {
    Route::post('/products', [ProduitController::class, 'store'])->middleware('role:vendeur,admin');
    Route::put('/products/{id}', [ProduitController::class, 'update'])->whereNumber('id');
    Route::delete('/products/{id}', [ProduitController::class, 'destroy'])->whereNumber('id');

    Route::post('/products/{id}/avis', [AvisController::class, 'store'])->whereNumber('id');

    // Consultee par la fiche produit avant d'afficher le formulaire d'avis.
    Route::get('/products/{id}/avis/eligibilite', [AvisController::class, 'eligibilite'])
        ->whereNumber('id');
});

// =========================================================================
// RECOMMANDATION IA (option C du cahier des charges)
// =========================================================================

Route::post('/cart/recommendations', [RecommandationController::class, 'pourPanier']);

// Suggestions fondees sur l'historique d'achat du client connecte.
Route::get('/me/recommendations', [RecommandationController::class, 'pourHistorique'])
    ->middleware('jwt');

// =========================================================================
// TABLEAU DE BORD VENDEUR
// =========================================================================

// Analyse concurrentielle — ajout hors perimetre du cahier des charges,
// documente comme tel dans docs/ECARTS-CONTRAT.md.
Route::get('/dashboard/concurrence', AnalyseConcurrentielleController::class)
    ->middleware(['jwt', 'role:vendeur,admin']);

// Indicateurs du vendeur (sa boutique) et statistiques d'achat du client.
Route::get('/dashboard/stats', [DashboardController::class, 'vendeur'])
    ->middleware(['jwt', 'role:vendeur,admin']);
Route::get('/dashboard/acheteur', [DashboardController::class, 'acheteur'])
    ->middleware('jwt');

// =========================================================================
// BOUTIQUES
// =========================================================================

Route::get('/boutiques', [BoutiqueController::class, 'index']);

// Doit rester avant /boutiques/{id}, sinon « me » est lu comme un id.
Route::get('/boutiques/me', [BoutiqueController::class, 'me'])->middleware('jwt');

Route::get('/boutiques/{id}', [BoutiqueController::class, 'show'])->whereNumber('id');

Route::middleware('jwt')->group(function () {
    Route::post('/boutiques', [BoutiqueController::class, 'store']);
    Route::put('/boutiques/{id}', [BoutiqueController::class, 'update'])->whereNumber('id');
    Route::delete('/boutiques/{id}', [BoutiqueController::class, 'destroy'])->whereNumber('id');
});

// =========================================================================
// COMMANDES
// =========================================================================

Route::middleware('jwt')->group(function () {
    Route::get('/orders', [CommandeController::class, 'index']);
    Route::get('/orders/{id}', [CommandeController::class, 'show'])->whereNumber('id');
    Route::post('/orders', [CommandeController::class, 'store']);
    Route::put('/orders/{id}/status', [CommandeController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('role:vendeur,admin');
    Route::delete('/orders/{id}', [CommandeController::class, 'destroy'])->whereNumber('id');
});

// =========================================================================
// AVIS
// =========================================================================

Route::delete('/avis/{id}', [AvisController::class, 'destroy'])
    ->whereNumber('id')
    ->middleware('jwt');

// =========================================================================
// DEPOT D'IMAGES
// =========================================================================

Route::middleware('jwt')->group(function () {
    Route::post('/upload/image', [UploadController::class, 'image']);
    Route::post('/upload/images', [UploadController::class, 'images']);
});

// =========================================================================
// CATEGORIES
// =========================================================================

Route::get('/categories', [CategorieController::class, 'index']);

// =========================================================================
// ADMINISTRATION  (back-office React AdminPage — role:admin)
// =========================================================================

Route::prefix('admin')->middleware(['jwt', 'role:admin'])->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);

    Route::get('/users', [AdminController::class, 'users']);
    Route::put('/users/{id}/toggle', [AdminController::class, 'toggleUser'])->whereNumber('id');

    Route::get('/boutiques', [AdminController::class, 'boutiques']);
    Route::put('/boutiques/{id}/toggle', [AdminController::class, 'toggleBoutique'])->whereNumber('id');

    Route::get('/avis', [AdminController::class, 'avis']);
    Route::delete('/avis/{id}', [AdminController::class, 'deleteAvis'])->whereNumber('id');

    Route::get('/categories', [AdminController::class, 'categories']);
    Route::post('/categories', [AdminController::class, 'createCategorie']);
    Route::put('/categories/{id}', [AdminController::class, 'updateCategorie'])->whereNumber('id');
    Route::delete('/categories/{id}', [AdminController::class, 'deleteCategorie'])->whereNumber('id');
});
