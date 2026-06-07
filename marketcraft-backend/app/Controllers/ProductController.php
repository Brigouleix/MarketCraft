<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Models\Product;
use App\Models\Boutique;

class ProductController extends Controller
{
    private Product  $productModel;
    private Boutique $boutiqueModel;

    public function __construct()
    {
        $this->productModel  = new Product();
        $this->boutiqueModel = new Boutique();
    }

    // ------------------------------------------------------------------
    // GET /products
    // ------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $page      = max(1, (int) ($this->getParam('page', 1)));
        $limit     = min(100, max(1, (int) ($this->getParam('limit', 20))));
        $search    = $this->getParam('search')    ?: null;
        $categorie = $this->getParam('categorie') ? (int) $this->getParam('categorie') : null;
        $boutique  = $this->getParam('boutique')  ? (int) $this->getParam('boutique')  : null;
        $prixMin   = $this->getParam('prix_min')  ? (float) $this->getParam('prix_min') : null;
        $prixMax   = $this->getParam('prix_max')  ? (float) $this->getParam('prix_max') : null;
        $sort      = $this->getParam('sort', 'created_at');
        $order     = strtoupper($this->getParam('order', 'DESC'));

        // ?my=true → produits du vendeur connecté uniquement
        $myOnly = in_array($this->getParam('my'), ['1', 'true'], true);

        if ($myOnly && Auth::hasRole('vendeur', 'admin')) {
            $auth      = Auth::getCurrentUser();
            $vendeurId = (int) $auth['sub'];
            $items     = $this->productModel->findByVendeur($vendeurId, $page, $limit);
            $total     = $this->productModel->countByVendeur($vendeurId);
        } else {
            $items = $this->productModel->findAll(
                $page, $limit, $search, $categorie, $boutique, $prixMin, $prixMax, $sort, $order
            );
            $total = $this->productModel->countAll($search, $categorie, $boutique, $prixMin, $prixMax);
        }

        $this->paginated($items, $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // GET /products/:id
    // ------------------------------------------------------------------

    public function show(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $product = $this->productModel->findById($id);

        if ($product === null) {
            $this->error('Product not found.', 404);
            return;
        }

        $this->success($product);
    }

    // ------------------------------------------------------------------
    // POST /products  (JWT + vendeur ou admin)
    // ------------------------------------------------------------------

    public function store(array $params = []): void
    {
        $auth = Auth::getCurrentUser();

        if (!Auth::hasRole('vendeur', 'admin')) {
            $this->error('Forbidden. Only vendors can create products.', 403);
            return;
        }

        $body   = $this->getBody();
        $errors = $this->validate($body, [
            'nom'         => 'required|min:3|max:200',
            'prix'        => 'required|numeric',
            'boutique_id' => 'required|integer',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        // Vérifier que la boutique appartient au vendeur courant (sauf admin)
        $boutiqueId = (int) $body['boutique_id'];
        $boutique   = $this->boutiqueModel->findById($boutiqueId);

        if ($boutique === null) {
            $this->error('Boutique not found.', 404);
            return;
        }

        if ($auth['role'] !== 'admin' && (int) $boutique['vendeur_id'] !== (int) $auth['sub']) {
            $this->error('Forbidden. This boutique does not belong to you.', 403);
            return;
        }

        if ((float) $body['prix'] < 0) {
            $this->error('Price must be a positive number.', 422);
            return;
        }

        try {
            $product = $this->productModel->create([
                'boutique_id'   => $boutiqueId,
                'categorie_id'  => isset($body['categorie_id']) ? (int) $body['categorie_id'] : null,
                'nom'           => $body['nom'],
                'description'   => $body['description'] ?? null,
                'prix'          => (float) $body['prix'],
                'stock'         => (int) ($body['stock'] ?? 0),
                'images'        => $body['images']        ?? null,
                'tags'          => $body['tags']          ?? null,
                'est_fait_main' => (int) ($body['est_fait_main'] ?? 1),
            ]);

            Logger::activity('product_create', "Product created: \"{$product['nom']}\"", [
                'product_id'  => $product['id'],
                'boutique_id' => $boutiqueId,
                'prix'        => $product['prix'],
            ], (int) $auth['sub']);

            $this->json(['success' => true, 'message' => 'Product created.', 'data' => $product], 201);
        } catch (\Throwable $e) {
            Logger::error('Product create failed: ' . $e->getMessage());
            $this->error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    // ------------------------------------------------------------------
    // PUT /products/:id  (JWT + owner)
    // ------------------------------------------------------------------

    public function update(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $product = $this->productModel->findById($id);

        if ($product === null) {
            $this->error('Product not found.', 404);
            return;
        }

        // Récupérer le vendeur_id via la boutique du produit
        if ($auth['role'] !== 'admin' && (int) ($product['vendeur_id'] ?? 0) !== (int) $auth['sub']) {
            $this->error('Forbidden. You do not own this product.', 403);
            return;
        }

        $body   = $this->getBody();
        $errors = $this->validate($body, [
            'nom'  => 'min:3|max:200',
            'prix' => 'numeric',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        if (isset($body['prix']) && (float) $body['prix'] < 0) {
            $this->error('Price must be a positive number.', 422);
            return;
        }

        $updated = $this->productModel->update($id, $body);

        Logger::activity('product_update', "Product updated: \"{$updated['nom']}\"", [
            'product_id' => $id,
        ], (int) $auth['sub']);

        $this->success($updated, 'Product updated successfully.');
    }

    // ------------------------------------------------------------------
    // DELETE /products/:id  (JWT + owner)
    // ------------------------------------------------------------------

    public function destroy(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $product = $this->productModel->findById($id);

        if ($product === null) {
            $this->error('Product not found.', 404);
            return;
        }

        if ($auth['role'] !== 'admin' && (int) ($product['vendeur_id'] ?? 0) !== (int) $auth['sub']) {
            $this->error('Forbidden. You do not own this product.', 403);
            return;
        }

        if ($this->productModel->delete($id)) {
            Logger::activity('product_delete', "Product deleted: \"{$product['nom']}\"", [
                'product_id' => $id,
            ], (int) $auth['sub']);
            $this->success(null, 'Product deleted successfully.');
        } else {
            $this->error('Failed to delete product.', 500);
        }
    }
}
