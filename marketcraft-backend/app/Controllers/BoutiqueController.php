<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Boutique;

class BoutiqueController extends Controller
{
    private Boutique $boutiqueModel;

    public function __construct()
    {
        $this->boutiqueModel = new Boutique();
    }

    // ------------------------------------------------------------------
    // GET /boutiques
    // ------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $page  = max(1, (int) $this->getParam('page', 1));
        $limit = min(50, max(1, (int) $this->getParam('limit', 20)));

        $items = $this->boutiqueModel->findAll($page, $limit);
        $total = $this->boutiqueModel->count();

        $this->paginated($items, $total, $page, $limit);
    }

    // ------------------------------------------------------------------
    // GET /boutiques/:id
    // ------------------------------------------------------------------

    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        $boutique = $this->boutiqueModel->getWithProduits($id);

        if ($boutique === null) {
            $this->error('Boutique not found.', 404);
            return;
        }

        $this->success($boutique);
    }

    // ------------------------------------------------------------------
    // POST /boutiques  (JWT required)
    // ------------------------------------------------------------------

    public function store(array $params = []): void
    {
        $auth = Auth::getCurrentUser();

        $body   = $this->getBody();
        $errors = $this->validate($body, [
            'nom' => 'required|min:3|max:150',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        // Mettre à jour le rôle de l'utilisateur en "vendeur" s'il est "client"
        // (logique métier : créer une boutique implique devenir vendeur)
        try {
            $boutique = $this->boutiqueModel->create([
                'vendeur_id'    => (int) $auth['sub'],
                'nom'           => $body['nom'],
                'description'   => $body['description']   ?? null,
                'logo_url'      => $body['logo_url']      ?? null,
                'banniere_url'  => $body['banniere_url']  ?? null,
            ]);

            $this->json(['success' => true, 'message' => 'Boutique created.', 'data' => $boutique], 201);
        } catch (\Throwable $e) {
            $this->error('Failed to create boutique: ' . $e->getMessage(), 500);
        }
    }

    // ------------------------------------------------------------------
    // PUT /boutiques/:id  (JWT + owner)
    // ------------------------------------------------------------------

    public function update(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $boutique = $this->boutiqueModel->findById($id);

        if ($boutique === null) {
            $this->error('Boutique not found.', 404);
            return;
        }

        if ($auth['role'] !== 'admin' && (int) $boutique['vendeur_id'] !== (int) $auth['sub']) {
            $this->error('Forbidden. You do not own this boutique.', 403);
            return;
        }

        $body   = $this->getBody();
        $errors = $this->validate($body, [
            'nom' => 'min:3|max:150',
        ]);

        if (!empty($errors)) {
            $this->error('Validation failed.', 422, $errors);
            return;
        }

        $updated = $this->boutiqueModel->update($id, $body);
        $this->success($updated, 'Boutique updated successfully.');
    }

    // ------------------------------------------------------------------
    // DELETE /boutiques/:id  (JWT + owner ou admin)
    // ------------------------------------------------------------------

    public function destroy(array $params = []): void
    {
        $auth = Auth::getCurrentUser();
        $id   = (int) ($params['id'] ?? 0);

        $boutique = $this->boutiqueModel->findById($id);

        if ($boutique === null) {
            $this->error('Boutique not found.', 404);
            return;
        }

        if ($auth['role'] !== 'admin' && (int) $boutique['vendeur_id'] !== (int) $auth['sub']) {
            $this->error('Forbidden. You do not own this boutique.', 403);
            return;
        }

        if ($this->boutiqueModel->delete($id)) {
            $this->success(null, 'Boutique deleted successfully.');
        } else {
            $this->error('Failed to delete boutique.', 500);
        }
    }
}
