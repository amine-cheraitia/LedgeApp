<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthCheckJsonResultsController::class);
Route::get('/health/dashboard', HealthCheckResultsController::class);
