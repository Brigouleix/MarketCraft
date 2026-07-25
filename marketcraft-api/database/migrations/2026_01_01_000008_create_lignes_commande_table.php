<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_commande', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('commande_id');
            $table->unsignedInteger('produit_id');
            $table->smallInteger('quantite')->default(1);
            // Instantane du prix et du nom au moment de l'achat : une
            // evolution du catalogue ne doit pas reecrire l'historique.
            $table->decimal('prix_unitaire', 10, 2);
            $table->string('nom_produit', 200)
                ->comment('Snapshot au moment de la commande');

            $table->index('commande_id', 'idx_lignes_commande');
            $table->index('produit_id', 'idx_lignes_produit');

            $table->foreign('commande_id', 'fk_lignes_commande')
                ->references('id')->on('commandes')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('produit_id', 'fk_lignes_produit')
                ->references('id')->on('produits')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_commande');
    }
};
