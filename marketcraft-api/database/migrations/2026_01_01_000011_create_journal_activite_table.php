<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal d'activite (OWASP — Security Logging Failures).
        // Table append-only, consultable via GET /admin/logs.
        Schema::create('journal_activite', function (Blueprint $table) {
            $table->increments('id');
            // Nullable : une tentative de connexion refusee n'a pas
            // d'utilisateur identifie.
            $table->unsignedInteger('utilisateur_id')->nullable();
            $table->string('action', 100)
                ->comment('connexion_reussie, connexion_refusee, admin_suppression_avis...');
            $table->enum('niveau', ['info', 'avertissement', 'erreur', 'critique'])->default('info');
            $table->text('message')->nullable();
            // 45 caracteres : longueur d'une adresse IPv6 en notation longue.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('contexte')->nullable()
                ->comment('Donnees structurees. Jamais de mot de passe ni de jeton.');
            $table->dateTime('created_at')->useCurrent();

            $table->index('action', 'idx_journal_action');
            $table->index('niveau', 'idx_journal_niveau');
            $table->index('created_at', 'idx_journal_date');
            $table->index('utilisateur_id', 'idx_journal_utilisateur');

            // SET NULL : la suppression d'un compte ne doit pas effacer la
            // trace de ses actions passees.
            $table->foreign('utilisateur_id', 'fk_journal_utilisateur')
                ->references('id')->on('utilisateurs')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activite');
    }
};
