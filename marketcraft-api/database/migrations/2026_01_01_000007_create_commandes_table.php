<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('utilisateur_id');
            $table->unsignedInteger('adresse_livraison_id')->nullable();
            $table->enum('statut', [
                'en_attente', 'confirmee', 'en_preparation',
                'expediee', 'livree', 'annulee',
            ])->default('en_attente');
            $table->decimal('montant_total', 10, 2)->default(0.00);
            $table->decimal('frais_livraison', 10, 2)->default(0.00);
            $table->text('note')->nullable();
            $table->string('numero_suivi', 100)->nullable();
            $table->dateTime('date_livraison')->nullable()
                ->comment('Date de livraison effective, base du calcul J+14');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('utilisateur_id', 'idx_commandes_utilisateur');
            $table->index('statut', 'idx_commandes_statut');

            // RESTRICT et non CASCADE : supprimer un compte ne doit pas
            // effacer l'historique comptable des commandes.
            $table->foreign('utilisateur_id', 'fk_commandes_utilisateur')
                ->references('id')->on('utilisateurs')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('adresse_livraison_id', 'fk_commandes_adresse')
                ->references('id')->on('adresses_livraison')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
