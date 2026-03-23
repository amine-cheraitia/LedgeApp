<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Entreprises\EntrepriseController;
use App\Http\Controllers\Exercices\ExerciceController;
use App\Http\Controllers\Prestations\PrestationController;
use App\Http\Controllers\Settings\SettingController;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth publique
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Routes authentifiées
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class, 'me']);

        // Back-office
        Route::middleware('backoffice')->group(function () {

            // Users
            Route::apiResource('users', UserController::class);

            // Entreprises
            Route::apiResource('entreprises', EntrepriseController::class);

            // Exercices
            Route::get('exercices/current', [ExerciceController::class, 'current']);
            Route::apiResource('exercices', ExerciceController::class);

            // Prestations (lecture + calcul prix)
            Route::get('prestations', [PrestationController::class, 'index']);
            Route::get('prestations/{prestation}', [PrestationController::class, 'show']);
            Route::post('prestations/{prestation}/calculer-prix', [PrestationController::class, 'calculerPrix']);

            // Settings (admin uniquement)
            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);
        });

        // Portail client
        Route::middleware('portail')->prefix('portail')->group(function () {
            //
        });
    });
});
