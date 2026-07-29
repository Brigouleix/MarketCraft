<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('commande_id');
            $table->enum('methode', ['carte', 'virement', 'paypal', 'cheque'])->default('carte');
            $table->enum('statut', ['en_attente', 'valide', 'refuse', 'rembourse', 'libere'])
                ->default('en_attente');
            $table->decimal('montant', 10, 2);
            $table->string('transaction_id', 255)->nullable();
            $table->dateTime('date_liberation')->nullable()
                ->comment('Renseignee par sp_liberer_paiements_echus() a J+14');
            $table->json('payload')->nullable()
                ->comment('Reponse brute du prestataire de paiement');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('commande_id', 'idx_paiements_commande');
            $table->index('transaction_id', 'idx_paiements_transaction');

            $table->foreign('commande_id', 'fk_paiements_commande')
                ->references('id')->on('commandes')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
