<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Entreprises\EntrepriseController;
use App\Http\Controllers\Exercices\ExerciceController;
use App\Http\Controllers\Facturation\DevisController;
use App\Http\Controllers\Facturation\FactureController;
use App\Http\Controllers\Facturation\PaiementController;
use App\Http\Controllers\Planning\MissionController;
use App\Http\Controllers\Planning\TacheCommentaireController;
use App\Http\Controllers\Planning\TacheController;
use App\Http\Controllers\Prestations\PrestationController;
use App\Http\Controllers\Settings\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth publique
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Routes authentifiees
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

            // Planning — Missions
            Route::apiResource('missions', MissionController::class);
            Route::apiResource('missions.taches', TacheController::class)->except(['show'])->parameters(['taches' => 'tache']);
            Route::apiResource('taches.commentaires', TacheCommentaireController::class)->except(['show'])->parameters(['commentaires' => 'commentaire']);

            // Facturation — Devis
            Route::post('devis/{devi}/convertir-en-mission', [DevisController::class, 'convertirEnMission']);
            Route::apiResource('devis', DevisController::class);

            // Facturation — Factures
            Route::apiResource('factures', FactureController::class)->except(['update']);

            // Facturation — Paiements (nested sous factures)
            Route::get('factures/{facture}/paiements', [PaiementController::class, 'index']);
            Route::post('factures/{facture}/paiements', [PaiementController::class, 'store']);
            Route::delete('factures/{facture}/paiements/{paiement}', [PaiementController::class, 'destroy']);
        });

        // Portail client
        Route::middleware('portail')->prefix('portail')->group(function () {
            //
        });
    });
});
