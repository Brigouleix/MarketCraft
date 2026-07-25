<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Interdit l'acces paresseux aux relations hors production : une
        // boucle qui declenche une requete par element se voit tout de
        // suite, au lieu de ne se manifester qu'en charge.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Signale une affectation en masse d'un champ non declare dans
        // $fillable, plutot que de l'ignorer en silence.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
