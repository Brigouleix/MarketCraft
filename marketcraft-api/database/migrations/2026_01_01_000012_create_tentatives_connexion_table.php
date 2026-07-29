<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support du verrouillage de compte : 5 echecs, 15 minutes.
        // Une table plutot qu'un cache — la trace survit a un redemarrage
        // et reste opposable.
        Schema::create('tentatives_connexion', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 191);
            $table->string('ip', 45)->nullable();
            $table->boolean('reussie')->default(0);
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            // Index compose : le decompte des echecs recents interroge
            // toujours ces trois colonnes ensemble.
            $table->index(['email', 'reussie', 'created_at'], 'idx_tentatives_email');
            $table->index(['ip', 'created_at'], 'idx_tentatives_ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentatives_connexion');
    }
};
