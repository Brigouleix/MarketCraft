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
        Schema::create('produits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('boutique_id');
            // Categorie principale : la premiere selectionnee. L'ensemble
            // des categories vit dans la table de liaison produit_categorie.
            $table->unsignedInteger('categorie_id')->nullable();
            $table->string('nom', 200);
            $table->string('slug', 220)->unique('uq_produits_slug');
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2);
            $table->integer('stock')->default(0);
            $table->decimal('note_moyenne', 3, 2)->default(0.00)
                ->comment('Recalculee automatiquement par trigger sur avis');
            $table->unsignedInteger('nombre_avis')->default(0)
                ->comment('Recalcule automatiquement par trigger sur avis');
            // Tableau d'URL absolues, construites depuis APP_URL.
            $table->json('images')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('est_actif')->default(1);
            $table->boolean('est_fait_main')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('boutique_id', 'idx_produits_boutique');
            $table->index('categorie_id', 'idx_produits_categorie');
            $table->index('prix', 'idx_produits_prix');
            $table->index('est_actif', 'idx_produits_actif');

            $table->foreign('boutique_id', 'fk_produits_boutique')
                ->references('id')->on('boutiques')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('categorie_id', 'fk_produits_categorie')
                ->references('id')->on('categories')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // Index FULLTEXT : specifique a MySQL/MariaDB, ignore sous SQLite
        // ou tourne la suite de tests.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `produits` ADD FULLTEXT `ft_produits_nom_desc` (`nom`, `description`)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
