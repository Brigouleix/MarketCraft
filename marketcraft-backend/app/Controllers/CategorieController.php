<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Categorie;

class CategorieController extends Controller
{
    private Categorie $categorieModel;

    public function __construct()
    {
        $this->categorieModel = new Categorie();
    }

    // ------------------------------------------------------------------
    // GET /categories
    // ------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $this->success($this->categorieModel->findAll());
    }
}
