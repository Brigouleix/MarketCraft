<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Liaison N-N. Aucune cle primaire technique ni horodatage :
        // la paire (produit, categorie) est sa propre identite.
        Schema::create('produit_categorie', function (Blueprint $table) {
            $table->unsignedInteger('produit_id');
            $table->unsignedInteger('categorie_id');

            $table->primary(['produit_id', 'categorie_id']);
            $table->index('categorie_id', 'idx_pc_categorie');

            $table->foreign('produit_id', 'fk_pc_produit')
                ->references('id')->on('produits')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('categorie_id', 'fk_pc_categorie')
                ->references('id')->on('categories')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produit_categorie');
    }
};
