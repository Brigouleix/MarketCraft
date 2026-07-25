<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adresses_livraison', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('utilisateur_id');
            $table->string('nom_complet', 200);
            $table->string('ligne1', 255);
            $table->string('ligne2', 255)->nullable();
            $table->string('ville', 100);
            $table->string('code_postal', 20);
            $table->string('pays', 100)->default('France');
            $table->boolean('est_principale')->default(0);
            $table->dateTime('created_at')->useCurrent();

            $table->index('utilisateur_id', 'idx_adresses_utilisateur');
            $table->foreign('utilisateur_id', 'fk_adresses_utilisateur')
                ->references('id')->on('utilisateurs')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adresses_livraison');
    }
};
