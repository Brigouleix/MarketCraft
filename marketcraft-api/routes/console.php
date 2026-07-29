<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Fait main, fait bien.');
})->purpose('Affiche une citation');
