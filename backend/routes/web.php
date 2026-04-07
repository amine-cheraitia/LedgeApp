<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthCheckJsonResultsController::class);
