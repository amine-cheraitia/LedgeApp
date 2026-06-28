<?php

use App\Http\Controllers\Audit\AuditController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
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
use App\Http\Controllers\Referentiel\ReferentielTvaController;
use App\Http\Controllers\Settings\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth publique
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Mot de passe — definition (invitation) / reinitialisation (libre-service), via jeton a usage unique
    Route::post('/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:6,1');

    // Routes authentifiees
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class, 'me']);

        // ─── Back-office ─────────────────────────────────────────────────────
        Route::middleware('backoffice')->group(function () {

            // ── Admin uniquement ─────────────────────────────────────────────
            Route::middleware('role:admin')->group(function () {
                // Gestion utilisateurs
                Route::post('users', [UserController::class, 'store']);
                Route::put('users/{user}', [UserController::class, 'update']);
                Route::delete('users/{user}', [UserController::class, 'destroy']);
                Route::post('users/{user}/renvoyer-invitation', [UserController::class, 'renvoyerInvitation'])->middleware('throttle:6,1');

                // Parametres cabinet (ecriture)
                Route::put('settings', [SettingController::class, 'update']);

                // Entreprises (ecriture + portail — admin uniquement)
                Route::delete('entreprises/{entreprise}', [EntrepriseController::class, 'destroy']);
                Route::post('entreprises/{entreprise}/activer-portail', [EntrepriseController::class, 'activerPortail']);
                Route::post('entreprises/{entreprise}/renvoyer-invitation', [EntrepriseController::class, 'renvoyerInvitation'])->middleware('throttle:6,1');
                Route::post('entreprises/{entreprise}/toggle-portail', [EntrepriseController::class, 'togglePortail']);

                // Exercices (ecriture + rapport cloture)
                Route::post('exercices', [ExerciceController::class, 'store']);
                Route::put('exercices/{exercice}', [ExerciceController::class, 'update']);
                Route::delete('exercices/{exercice}', [ExerciceController::class, 'destroy']);
                Route::get('exercices/{exercice}/rapport-cloture/pdf', [ExerciceController::class, 'rapportCloturePdf']);

                // Prestations (ecriture)
                Route::post('prestations', [PrestationController::class, 'store']);
                Route::put('prestations/{prestation}', [PrestationController::class, 'update']);
                Route::delete('prestations/{prestation}', [PrestationController::class, 'destroy']);

                // Referentiel — Taux de TVA (admin uniquement)
                Route::get('referentiels/tva-taux', [ReferentielTvaController::class, 'index']);
                Route::post('referentiels/tva-taux', [ReferentielTvaController::class, 'store']);
                Route::put('referentiels/tva-taux/{tvaTaux}', [ReferentielTvaController::class, 'update']);
                Route::delete('referentiels/tva-taux/{tvaTaux}', [ReferentielTvaController::class, 'destroy']);

                // KPI objectifs (ecriture)
                Route::post('/kpi/objectifs', [KpiController::class, 'upsert']);
                Route::delete('/kpi/objectifs/{objectif}', [KpiController::class, 'destroy']);

                // Dashboard stats financieres (admin uniquement)
                Route::get('/stats', [DashboardController::class, 'stats']);

                // Journal d'audit — piste d'audit des actions utilisateurs
                Route::get('/audit-logs', [AuditController::class, 'index']);

                // Prestations — calcul de prix (preview pour la creation de devis)
                Route::post('prestations/{prestation}/calculer-prix', [PrestationController::class, 'calculerPrix']);

                // Facturation — Devis (creation / cycle de vie / suppression — admin uniquement)
                Route::post('devis', [DevisController::class, 'store']);
                Route::put('devis/{devis}', [DevisController::class, 'update']);
                Route::delete('devis/{devis}', [DevisController::class, 'destroy']);
                Route::post('devis/{devis}/accepter', [DevisController::class, 'accepter']);
                Route::post('devis/{devis}/refuser', [DevisController::class, 'refuser']);
                Route::post('devis/{devis}/convertir-en-mission', [DevisController::class, 'convertirEnMission']);

                // Facturation — Factures (creation / suppression — admin uniquement)
                Route::post('factures', [FactureController::class, 'store']);
                Route::delete('factures/{facture}', [FactureController::class, 'destroy']);

                // Facturation — Avoirs (creation / suppression — admin uniquement)
                Route::post('factures/{facture}/avoirs', [AvoirController::class, 'store']);
                Route::delete('avoirs/{avoir}', [AvoirController::class, 'destroy']);
            });

            // ── Secretaire uniquement ────────────────────────────────────────
            Route::middleware('role:secretaire')->group(function () {
                Route::get('/stats/secretaire', [DashboardController::class, 'secretaireStats']);
            });

            // ── Admin + Secretaire ───────────────────────────────────────────
            Route::middleware('role:admin|secretaire')->group(function () {
                // KPI objectifs (lecture)
                Route::get('/kpi/objectifs', [KpiController::class, 'index']);

                // Entreprises (lecture + creation/modification — suppression reservee admin)
                Route::post('entreprises', [EntrepriseController::class, 'store']);
                Route::put('entreprises/{entreprise}', [EntrepriseController::class, 'update']);
                Route::get('entreprises/wilayas', [EntrepriseController::class, 'wilayas']);
                Route::get('entreprises/export-csv', [EntrepriseController::class, 'exportCsv']);
                Route::get('entreprises', [EntrepriseController::class, 'index']);
                Route::get('entreprises/{entreprise}', [EntrepriseController::class, 'show']);

                // Contacts entreprise (CRUD — rattache a la gestion des entreprises)
                Route::get('entreprises/{entreprise}/contacts', [ContactController::class, 'index']);
                Route::post('entreprises/{entreprise}/contacts', [ContactController::class, 'store']);
                Route::put('entreprises/{entreprise}/contacts/{contact}', [ContactController::class, 'update']);
                Route::delete('entreprises/{entreprise}/contacts/{contact}', [ContactController::class, 'destroy']);

                // Exercices (lecture)
                Route::get('exercices/current', [ExerciceController::class, 'current']);
                Route::get('exercices', [ExerciceController::class, 'index']);
                Route::get('exercices/{exercice}', [ExerciceController::class, 'show']);

                // Prestations (lecture)
                Route::get('prestations', [PrestationController::class, 'index']);
                Route::get('prestations/{prestation}', [PrestationController::class, 'show']);

                // Facturation — Devis (lecture + PDF + envoi au client)
                Route::get('devis', [DevisController::class, 'index']);
                Route::get('devis/{devis}', [DevisController::class, 'show']);
                Route::get('devis/{devis}/pdf', [DevisController::class, 'pdf'])->middleware('throttle:30,1');
                Route::post('devis/{devis}/envoyer', [DevisController::class, 'envoyer'])->middleware('throttle:6,1');

                // Facturation — Factures (lecture + PDF pour transmission au client)
                Route::get('factures', [FactureController::class, 'index']);
                Route::get('factures/{facture}', [FactureController::class, 'show']);
                Route::get('factures/{facture}/pdf', [FactureController::class, 'pdf'])->middleware('throttle:30,1');
                Route::post('factures/{facture}/transmettre', [FactureController::class, 'transmettre'])->middleware('throttle:6,1');

                // Recouvrement — Paiements (lecture + enregistrement + suppression propre saisie)
                Route::get('factures/{facture}/paiements', [PaiementController::class, 'index']);
                Route::post('factures/{facture}/paiements', [PaiementController::class, 'store']);
                Route::delete('factures/{facture}/paiements/{paiement}', [PaiementController::class, 'destroy']);

                // Recouvrement — Creances impayees
                Route::get('creances', [CreanceController::class, 'index']);

                // Recouvrement — Relances (lecture + envoi)
                Route::get('factures/{facture}/relances', [RelanceController::class, 'index']);
                Route::post('factures/{facture}/relances', [RelanceController::class, 'store'])->middleware('throttle:6,1');

                // Facturation — Avoirs (lecture + PDF)
                Route::get('avoirs', [AvoirController::class, 'indexAll']);
                Route::get('factures/{facture}/avoirs', [AvoirController::class, 'index']);
                Route::get('factures/{facture}/avoirs/{avoir}/pdf', [AvoirController::class, 'pdf'])->middleware('throttle:30,1');
            });

            // ── Tous roles backoffice : utilitaires partages (admin + secretaire + collaborateur) ────
            // Lecture utilisateurs (pour les selects d'assignation des taches)
            Route::get('users', [UserController::class, 'index']);
            Route::get('users/{user}', [UserController::class, 'show']);

            // Parametres cabinet (lecture)
            Route::get('settings', [SettingController::class, 'index']);

            // ── Admin + Collaborateur : missions, taches, planning (hors perimetre secretaire) ────
            Route::middleware('role:admin|collaborateur')->group(function () {
                // Dashboard collaborateur
                Route::get('collaborateur/stats', [DashboardController::class, 'collaborateurStats']);

                // Planning — Calendrier (filtre auto par user dans le service)
                Route::get('calendar', [CalendarController::class, 'index']);

                // Planning — Missions (filtre collaborateur dans MissionService + MissionPolicy)
                Route::get('missions/{mission}/rapport/pdf', [MissionController::class, 'rapportPdf']);
                Route::get('missions/{mission}/convention/pdf', [MissionController::class, 'conventionPdf']);
                Route::get('missions/{mission}/mandat/pdf', [MissionController::class, 'mandatPdf']);
                Route::apiResource('missions', MissionController::class);

                // Planning — Detection de conflit d'affectation (avant l'apiResource pour eviter toute collision)
                Route::get('taches/conflits', [TacheController::class, 'conflits']);

                // Planning — Taches (gate mission dans TacheController::index)
                Route::apiResource('missions.taches', TacheController::class)->parameters(['taches' => 'tache']);

                // Planning — Commentaires (gate mission dans TacheCommentaireController)
                Route::apiResource('taches.commentaires', TacheCommentaireController::class)->except(['show'])->parameters(['taches' => 'tache', 'commentaires' => 'commentaire']);
            });
        });

        // ─── Portail client ───────────────────────────────────────────────────
        Route::middleware('portail')->prefix('portail')->group(function () {
            Route::get('me', [PortailController::class, 'me']);

            // US-30 — Mes factures
            Route::get('factures', [PortailFactureController::class, 'index']);
            Route::get('factures/{facture}/pdf', [PortailFactureController::class, 'pdf'])->middleware('throttle:30,1');

            // US-31 — Mes missions
            Route::get('missions', [PortailMissionController::class, 'index']);
            Route::get('missions/{mission}', [PortailMissionController::class, 'show']);
            Route::get('missions/{mission}/rapport/pdf', [PortailMissionController::class, 'rapportPdf'])->middleware('throttle:30,1');

            // US-32 — Mes documents
            Route::get('documents', [PortailDocumentController::class, 'index']);
            Route::get('documents/{mission}/convention/pdf', [PortailDocumentController::class, 'conventionPdf']);
            Route::get('documents/{mission}/mandat/pdf', [PortailDocumentController::class, 'mandatPdf']);
        });
    });
});
