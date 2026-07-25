<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            // 191 caracteres : longueur maximale indexable en utf8mb4
            // sous l'ancien format de ligne InnoDB.
            $table->string('email', 191)->unique('uq_utilisateurs_email');
            // Colonne volontairement nommee `password_hash` et non
            // `password` : le modele User surcharge getAuthPassword().
            $table->string('password_hash', 255);
            $table->enum('role', ['client', 'vendeur', 'admin'])->default('client');
            $table->string('avatar_url', 500)->nullable();
            $table->string('telephone', 20)->nullable();
            $table->boolean('est_actif')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('role', 'idx_utilisateurs_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
