<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Facture;
use App\Models\Setting;
use App\Services\RelanceService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnvoyerRelancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RelanceService $relanceService): void
    {
        $delais = [
            1 => (int) Setting::get('relance_niveau1_jours', '15'),
            2 => (int) Setting::get('relance_niveau2_jours', '30'),
            3 => (int) Setting::get('relance_niveau3_jours', '45'),
        ];

        $factures = Facture::with(['entreprise', 'relances'])
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->whereNotNull('date_echeance')
            ->get();

        foreach ($factures as $facture) {
            $joursDepuisEcheance = Carbon::parse($facture->date_echeance)->diffInDays(now(), false);

            if ($joursDepuisEcheance <= 0) {
                continue;
            }

            // Determine le niveau attendu selon les jours ecoules
            $niveauAttendu = null;
            foreach (array_reverse($delais, true) as $niveau => $delai) {
                if ($joursDepuisEcheance >= $delai) {
                    $niveauAttendu = $niveau;
                    break;
                }
            }

            if ($niveauAttendu === null) {
                continue;
            }

            try {
                $relanceEnvoyee = $relanceService->envoyerAutomatique($facture, $niveauAttendu);

                if ($relanceEnvoyee) {
                    Log::info('Relance automatique envoyee', [
                        'facture_id' => $facture->id,
                        'numero' => $facture->numero,
                        'niveau' => $niveauAttendu,
                        'email' => $relanceEnvoyee->email_destinataire,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Echec envoi relance automatique', [
                    'facture_id' => $facture->id,
                    'niveau' => $niveauAttendu,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
