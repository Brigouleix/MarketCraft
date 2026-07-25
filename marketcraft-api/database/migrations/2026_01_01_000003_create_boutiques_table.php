<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boutiques', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('vendeur_id');
            $table->string('nom', 150);
            $table->string('slug', 160)->unique('uq_boutiques_slug');
            $table->text('description')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('banniere_url', 500)->nullable();
            $table->boolean('est_active')->default(1);
            $table->decimal('note_moyenne', 3, 2)->default(0.00)
                ->comment('Recalculee automatiquement par trigger sur produits');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Regle de gestion : au plus une boutique par vendeur.
            // La contrainte vit en base, pas seulement dans le controleur :
            // deux requetes concurrentes passeraient sinon toutes les deux
            // le test applicatif.
            $table->unique('vendeur_id', 'uq_boutiques_vendeur');

            $table->foreign('vendeur_id', 'fk_boutiques_vendeur')
                ->references('id')->on('utilisateurs')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boutiques');
    }
};
