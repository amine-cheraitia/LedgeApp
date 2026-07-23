<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // TelescopeServiceProvider est enregistre conditionnellement (local uniquement)
    // dans AppServiceProvider::register() : evite l'enregistrement de donnees
    // sensibles en prod et le boot casse en --no-dev (classe parente absente).
];
