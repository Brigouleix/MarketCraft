<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Avis;
use App\Models\Product;

class AvisController extends Controller
{
    private Avis    $avisModel;
    private Product $productModel;

    public function __construct()
    {
        $this->avisModel    = new Avis();
        $this->productModel = new Product();
    }

    // ------------------------------------------------------------------
    // GET /products/:id/avis
    // ------------------------------------------------------------------

    public function indexByProduct(array $params = []): void
    {
        $produitId = (int) ($params['id'] ?? 0);

        $product = $this->productModel->findById($produitId);

        if ($product === null) {
            $this->error('Product not found.', 404);
            return;
        }

        $page  = max(1, (int) $this->getParam('page', 1));
        $limit = min(50, max(1, (int) $this->getParam('limit', 20)));

        $items    = $this->avisModel->findByProduct($produitId, $page, $limit);
        $total    = $this->avisModel->countByProduct($produitId);
        $avgNote  = $this->avisModel->getAvgNote($produitId);

        $this->json([
            'success'    => true,
            'data'       => $items,
            'stats'      => $avgNote,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int) ceil($total / max(1, $limit)),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /products/:id/avis  (JWT)
    // ------------------------------------------------------------------

    public function store(array $params = []): void
    {
        $auth      = Auth::getCurrentUser();
        $produitId = (int) ($params['id'] ?? 0);

        $product = $this->productModel->findById($produitId);

        if ($product === null) {
            $this->error('Product not found.', 404);
            return;
        }

        $userId = (int) $auth['sub'];

        // Vérifier si l'utilisateur a déjà posté un avis sur ce produit
        if ($this->avisModel->userAlreadyReviewed($produitId, $userId)) {
            $this->error('You have already reviewed this product.', 409);
            return;
        }

        $body   = $this->getBody();
        $errors = $this->validate($body, [
            'note' => 'required|integer|in:1,2,3,4,5',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        $note = (int) $body['note'];
        if ($note < 1 || $note > 5) {
            $this->error('Note must be between 1 and 5.', 422);
            return;
        }

        try {
            $avis = $this->avisModel->create([
                'produit_id'     => $produitId,
                'utilisateur_id' => $userId,
                'note'           => $note,
                'titre'          => isset($body['titre'])       ? strip_tags(trim($body['titre']))       : null,
                'commentaire'    => isset($body['commentaire']) ? strip_tags(trim($body['commentaire'])) : null,
            ]);

            $this->json(['success' => true, 'message' => 'Review posted.', 'data' => $avis], 201);
        } catch (\Throwable $e) {
            // Gérer la violation de contrainte UNIQUE
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'uq_avis_produit_user')) {
                $this->error('You have already reviewed this product.', 409);
            } else {
                $this->error('Failed to post review: ' . $e->getMessage(), 500);
            }
        }
    }

    // ------------------------------------------------------------------
    // DELETE /avis/:id  (JWT + owner ou admin)
    // ------------------------------------------------------------------

    public function destroy(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $avis = $this->avisModel->findById($id);

        if ($avis === null) {
            $this->error('Review not found.', 404);
            return;
        }

        if ($auth['role'] !== 'admin' && (int) $avis['utilisateur_id'] !== (int) $auth['sub']) {
            $this->error('Forbidden. You cannot delete this review.', 403);
            return;
        }

        if ($this->avisModel->delete($id)) {
            $this->success(null, 'Review deleted successfully.');
        } else {
            $this->error('Failed to delete review.', 500);
        }
    }
}
