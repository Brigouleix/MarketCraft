<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CategorieResource;
use App\Models\Categorie;
use Illuminate\Http\JsonResponse;

class CategorieController extends Controller
{
    /**
     * GET /categories — public.
     *
     * Renvoie l'arbre a plat, `parent_id` compris : le front reconstruit
     * lui-meme ses deux onglets « Objet » et « Materiau ». Le tri par
     * `ordre` puis `nom` reproduit l'ordre d'affichage attendu.
     */
    public function index(): JsonResponse
    {
        $categories = Categorie::query()
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get();

        return $this->ok(CategorieResource::collection($categories));
    }
}
