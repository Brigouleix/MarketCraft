<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            // Auto-reference : deux racines (« Objet », « Materiau »)
            // regroupent leurs sous-categories. ON DELETE SET NULL plutot
            // que CASCADE : supprimer une racine ne doit pas emporter tout
            // son sous-arbre.
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('nom', 100);
            $table->string('slug', 110)->unique('uq_categories_slug');
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->smallInteger('ordre')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->index('parent_id', 'idx_categories_parent');
            $table->foreign('parent_id', 'fk_categories_parent')
                ->references('id')->on('categories')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
