<?php

use App\Jobs\EnvoyerRelancesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envoi quotidien des relances automatiques a 8h du matin
Schedule::job(new EnvoyerRelancesJob)->dailyAt('08:00');
