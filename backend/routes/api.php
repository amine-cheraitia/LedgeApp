<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\KpiController;
use App\Http\Controllers\Entreprises\ContactController;
use App\Http\Controllers\Entreprises\EntrepriseController;
use App\Http\Controllers\Exercices\ExerciceController;
use App\Http\Controllers\Facturation\AvoirController;
use App\Http\Controllers\Facturation\CreanceController;
use App\Http\Controllers\Facturation\DevisController;
use App\Http\Controllers\Facturation\FactureController;
use App\Http\Controllers\Facturation\PaiementController;
use App\Http\Controllers\Facturation\RelanceController;
use App\Http\Controllers\Planning\CalendarController;
use App\Http\Controllers\Planning\MissionController;
use App\Http\Controllers\Planning\TacheCommentaireController;
use App\Http\Controllers\Planning\TacheController;
use App\Http\Controllers\Portail\PortailController;
use App\Http\Controllers\Portail\PortailDocumentController;
use App\Http\Controllers\Portail\PortailFactureController;
use App\Http\Controllers\Portail\PortailMissionController;
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

            // Dashboard stats
            Route::get('/stats', [DashboardController::class, 'stats']);

            // KPI objectifs collaborateurs (US-34)
            Route::get('/kpi/objectifs', [KpiController::class, 'index']);
            Route::post('/kpi/objectifs', [KpiController::class, 'upsert']);
            Route::delete('/kpi/objectifs/{objectif}', [KpiController::class, 'destroy']);

            // Users
            Route::apiResource('users', UserController::class);

            // Entreprises
            Route::get('entreprises/wilayas', [EntrepriseController::class, 'wilayas']);
            Route::get('entreprises/export-csv', [EntrepriseController::class, 'exportCsv']);
            Route::apiResource('entreprises', EntrepriseController::class);
            Route::post('entreprises/{entreprise}/activer-portail', [EntrepriseController::class, 'activerPortail']);
            Route::post('entreprises/{entreprise}/toggle-portail', [EntrepriseController::class, 'togglePortail']);

            // Contacts entreprise
            Route::get('entreprises/{entreprise}/contacts', [ContactController::class, 'index']);
            Route::post('entreprises/{entreprise}/contacts', [ContactController::class, 'store']);
            Route::put('entreprises/{entreprise}/contacts/{contact}', [ContactController::class, 'update']);
            Route::delete('entreprises/{entreprise}/contacts/{contact}', [ContactController::class, 'destroy']);

            // Exercices
            Route::get('exercices/current', [ExerciceController::class, 'current']);
            Route::apiResource('exercices', ExerciceController::class);

            // Prestations
            Route::get('prestations', [PrestationController::class, 'index']);
            Route::post('prestations', [PrestationController::class, 'store']);
            Route::get('prestations/{prestation}', [PrestationController::class, 'show']);
            Route::put('prestations/{prestation}', [PrestationController::class, 'update']);
            Route::delete('prestations/{prestation}', [PrestationController::class, 'destroy']);
            Route::post('prestations/{prestation}/calculer-prix', [PrestationController::class, 'calculerPrix']);

            // Settings (admin uniquement)
            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);

            // Planning — Calendrier
            Route::get('calendar', [CalendarController::class, 'index']);

            // Planning — Missions
            Route::get('missions/{mission}/convention/pdf', [MissionController::class, 'conventionPdf']);
            Route::get('missions/{mission}/mandat/pdf', [MissionController::class, 'mandatPdf']);
            Route::apiResource('missions', MissionController::class);
            Route::apiResource('missions.taches', TacheController::class)->except(['show'])->parameters(['taches' => 'tache']);
            Route::apiResource('taches.commentaires', TacheCommentaireController::class)->except(['show'])->parameters(['commentaires' => 'commentaire']);

            // Facturation — Devis
            Route::get('devis/{devis}/pdf', [DevisController::class, 'pdf']);
            Route::post('devis/{devis}/envoyer', [DevisController::class, 'envoyer']);
            Route::post('devis/{devis}/accepter', [DevisController::class, 'accepter']);
            Route::post('devis/{devis}/refuser', [DevisController::class, 'refuser']);
            Route::post('devis/{devis}/convertir-en-mission', [DevisController::class, 'convertirEnMission']);
            Route::apiResource('devis', DevisController::class)->parameters(['devis' => 'devis']);

            // Facturation — Factures
            Route::get('factures/{facture}/pdf', [FactureController::class, 'pdf']);
            Route::apiResource('factures', FactureController::class)->except(['update']);

            // Facturation — Paiements (nested sous factures)
            Route::get('factures/{facture}/paiements', [PaiementController::class, 'index']);
            Route::post('factures/{facture}/paiements', [PaiementController::class, 'store']);
            Route::delete('factures/{facture}/paiements/{paiement}', [PaiementController::class, 'destroy']);

            // Facturation — Creances impayees
            Route::get('creances', [CreanceController::class, 'index']);

            // Facturation — Relances
            Route::get('factures/{facture}/relances', [RelanceController::class, 'index']);
            Route::post('factures/{facture}/relances', [RelanceController::class, 'store']);

            // Facturation — Avoirs
            Route::get('avoirs', [AvoirController::class, 'indexAll']);
            Route::delete('avoirs/{avoir}', [AvoirController::class, 'destroy']);
            Route::get('factures/{facture}/avoirs', [AvoirController::class, 'index']);
            Route::post('factures/{facture}/avoirs', [AvoirController::class, 'store']);
            Route::get('factures/{facture}/avoirs/{avoir}/pdf', [AvoirController::class, 'pdf']);
        });

        // Portail client
        Route::middleware('portail')->prefix('portail')->group(function () {
            Route::get('me', [PortailController::class, 'me']);

            // US-30 — Mes factures
            Route::get('factures', [PortailFactureController::class, 'index']);
            Route::get('factures/{facture}/pdf', [PortailFactureController::class, 'pdf']);

            // US-31 — Mes missions
            Route::get('missions', [PortailMissionController::class, 'index']);
            Route::get('missions/{mission}', [PortailMissionController::class, 'show']);

            // US-32 — Mes documents
            Route::get('documents', [PortailDocumentController::class, 'index']);
            Route::get('documents/{mission}/convention/pdf', [PortailDocumentController::class, 'conventionPdf']);
            Route::get('documents/{mission}/mandat/pdf', [PortailDocumentController::class, 'mandatPdf']);
        });
    });
});
