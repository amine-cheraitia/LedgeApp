<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/', function () {
    return view('welcome');
});

// Diagnostics detailles (etat BDD/cache/disque/queue) : reserves a l'admin.
// Le monitoring externe (UptimeRobot) utilise l'endpoint public simple `/up`
// (configure dans bootstrap/app.php), qui ne divulgue aucun detail.
Route::middleware('role:admin')->group(function () {
    Route::get('/health', HealthCheckJsonResultsController::class);
    Route::get('/health/dashboard', HealthCheckResultsController::class);
});
