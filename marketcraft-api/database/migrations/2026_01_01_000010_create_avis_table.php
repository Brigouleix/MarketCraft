<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avis', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('produit_id');
            $table->unsignedInteger('utilisateur_id');
            $table->tinyInteger('note')->comment('Note de 1 a 5');
            $table->string('titre', 150)->nullable();
            $table->text('commentaire')->nullable();
            $table->boolean('est_verifie')->default(0);
            $table->dateTime('created_at')->useCurrent();

            // Un seul avis par couple (produit, utilisateur). La contrainte
            // est en base : le controle applicatif seul laisserait passer
            // deux envois simultanes.
            $table->unique(['produit_id', 'utilisateur_id'], 'uq_avis_produit_user');
            $table->index('produit_id', 'idx_avis_produit');
            $table->index('utilisateur_id', 'idx_avis_utilisateur');

            $table->foreign('produit_id', 'fk_avis_produit')
                ->references('id')->on('produits')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('utilisateur_id', 'fk_avis_utilisateur')
                ->references('id')->on('utilisateurs')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `avis` ADD CONSTRAINT `chk_avis_note` CHECK (`note` BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
