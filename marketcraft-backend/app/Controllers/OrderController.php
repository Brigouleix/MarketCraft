<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Models\Order;
use App\Models\Product;

class OrderController extends Controller
{
    private Order   $orderModel;
    private Product $productModel;

    public function __construct()
    {
        $this->orderModel   = new Order();
        $this->productModel = new Product();
    }

    // ------------------------------------------------------------------
    // GET /orders  (JWT – mes commandes)
    // ------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $auth  = Auth::getCurrentUser();
        $page  = max(1, (int) $this->getParam('page', 1));
        $limit = min(50, max(1, (int) $this->getParam('limit', 20)));

        $userId = (int) $auth['sub'];

        $items = $this->orderModel->findByUser($userId, $page, $limit);
        $total = $this->orderModel->countByUser($userId);

        $this->paginated($items, $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // GET /orders/:id  (JWT + owner)
    // ------------------------------------------------------------------

    public function show(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $order = $this->orderModel->findById($id);

        if ($order === null) {
            $this->error('Order not found.', 404);
            return;
        }

        // Seul le propriétaire ou un admin peut voir le détail
        if ($auth['role'] !== 'admin' && (int) $order['utilisateur_id'] !== (int) $auth['sub']) {
            $this->error('Forbidden.', 403);
            return;
        }

        $this->success($order);
    }

    // ------------------------------------------------------------------
    // POST /orders  (JWT)
    // ------------------------------------------------------------------

    public function store(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $body = $this->getBody();

        $errors = $this->validate($body, [
            'lignes' => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        if (!is_array($body['lignes']) || count($body['lignes']) === 0) {
            $this->error('Order must contain at least one item.', 422);
            return;
        }

        // Valider et enrichir chaque ligne avec les données produit en BDD
        $lignes = [];

        foreach ($body['lignes'] as $index => $ligne) {
            if (empty($ligne['produit_id']) || empty($ligne['quantite'])) {
                $this->error("Line #{$index}: produit_id and quantite are required.", 422);
                return;
            }

            $quantite = (int) $ligne['quantite'];
            if ($quantite < 1) {
                $this->error("Line #{$index}: quantite must be at least 1.", 422);
                return;
            }

            $product = $this->productModel->findById((int) $ligne['produit_id']);

            if ($product === null) {
                $this->error("Line #{$index}: Product #{$ligne['produit_id']} not found.", 404);
                return;
            }

            if ($product['stock'] < $quantite) {
                $this->error("Line #{$index}: Insufficient stock for \"{$product['nom']}\" (available: {$product['stock']}).", 422);
                return;
            }

            $lignes[] = [
                'produit_id'    => $product['id'],
                'quantite'      => $quantite,
                'prix_unitaire' => (float) $product['prix'],
                'nom_produit'   => $product['nom'],
            ];
        }

        try {
            // Décrémenter le stock de chaque produit
            foreach ($lignes as $ligne) {
                $this->productModel->decrementStock($ligne['produit_id'], $ligne['quantite']);
            }

            $order = $this->orderModel->create([
                'utilisateur_id'      => (int) $auth['sub'],
                'adresse_livraison_id' => isset($body['adresse_livraison_id'])
                    ? (int) $body['adresse_livraison_id']
                    : null,
                'frais_livraison'     => (float) ($body['frais_livraison'] ?? 5.90),
                'note'                => $body['note'] ?? null,
                'lignes'              => $lignes,
            ]);

            $total = array_sum(array_map(
                static fn($l) => $l['prix_unitaire'] * $l['quantite'],
                $lignes
            ));
            Logger::activity('order_create', "New order #{$order['id']} — {$total} €", [
                'order_id'   => $order['id'],
                'total'      => $total,
                'nb_articles' => count($lignes),
            ], (int) $auth['sub']);

            $this->json(['success' => true, 'message' => 'Order created.', 'data' => $order], 201);
        } catch (\Throwable $e) {
            Logger::error('Order create failed: ' . $e->getMessage());
            $this->error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    // ------------------------------------------------------------------
    // PUT /orders/:id/status  (JWT + vendeur ou admin)
    // ------------------------------------------------------------------

    public function updateStatus(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        if (!Auth::hasRole('vendeur', 'admin')) {
            $this->error('Forbidden. Only vendors and admins can update order status.', 403);
            return;
        }

        $order = $this->orderModel->findById($id);

        if ($order === null) {
            $this->error('Order not found.', 404);
            return;
        }

        $body = $this->getBody();

        $errors = $this->validate($body, [
            'statut' => 'required|in:en_attente,confirmee,en_preparation,expediee,livree,annulee',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        $previousStatut = $order['statut'];
        $updated = $this->orderModel->updateStatus(
            $id,
            $body['statut'],
            $body['numero_suivi'] ?? null
        );

        Logger::activity('order_status', "Order #{$id} status: {$previousStatut} → {$body['statut']}", [
            'order_id'    => $id,
            'from_statut' => $previousStatut,
            'to_statut'   => $body['statut'],
        ], (int) $auth['sub']);

        $this->success($updated, 'Order status updated.');
    }

    // ------------------------------------------------------------------
    // DELETE /orders/:id  (JWT + owner – annulation)
    // ------------------------------------------------------------------

    public function destroy(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $order = $this->orderModel->findById($id);

        if ($order === null) {
            $this->error('Order not found.', 404);
            return;
        }

        if ($auth['role'] !== 'admin' && (int) $order['utilisateur_id'] !== (int) $auth['sub']) {
            $this->error('Forbidden.', 403);
            return;
        }

        if ($this->orderModel->cancel($id)) {
            $this->success(null, 'Order cancelled successfully.');
        } else {
            $this->error('Cannot cancel this order (already shipped or delivered).', 409);
        }
    }
}
