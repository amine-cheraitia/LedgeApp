<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $exerciceId = $request->integer('exercice_id') ?: null;

        // Scope factures par exercice si fourni
        $factureQuery = fn () => Facture::where('type', 'FF')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId));

        $caTtc = (float) $factureQuery()->sum('montant_ttc');
        $totalPaye = (float) $factureQuery()->sum('montant_paye');

        $tvaCollectee = (float) $factureQuery()->sum('montant_tva');

        $tauxRecouvrement = $caTtc > 0
            ? round(($totalPaye / $caTtc) * 100, 1)
            : 0.0;

        // CA du mois courant (dans l'exercice si filtré)
        $caMois = (float) $factureQuery()
            ->whereYear('date_facture', $now->year)
            ->whereMonth('date_facture', $now->month)
            ->sum('montant_ttc');

        $enRetard = $factureQuery()
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->where('date_echeance', '<', $now)
            ->count();

        $totalImpaye = (float) $factureQuery()
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->selectRaw('COALESCE(SUM(montant_ttc - montant_paye), 0) as total')
            ->value('total');

        // Missions scoped par exercice si fourni
        $missionQuery = fn () => Mission::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId));

        // Seuil d'alerte recouvrement (configurable via settings, défaut 70%)
        $seuilRecouvrement = (float) (Setting::where('key', 'seuil_alerte_recouvrement')->value('value') ?? 70);

        // Alertes dynamiques
        $alertes = [];
        if ($enRetard > 0) {
            $alertes[] = [
                'type' => 'danger',
                'message' => "{$enRetard} facture(s) en retard de paiement.",
            ];
        }
        if ($caTtc > 0 && $tauxRecouvrement < $seuilRecouvrement) {
            $alertes[] = [
                'type' => 'warn',
                'message' => "Taux de recouvrement faible : {$tauxRecouvrement}% (seuil : {$seuilRecouvrement}%).",
            ];
        }

        // Exercices disponibles pour le filtre
        $exercices = Exercice::orderByDesc('annee')->get(['id', 'annee', 'statut']);

        return response()->json([
            'data' => [
                'exercices' => $exercices,
                'exercice_courant' => $exerciceId,
                'kpi' => [
                    'ca_mois' => $caMois,
                    'tva_collectee' => $tvaCollectee,
                    'taux_recouvrement' => $tauxRecouvrement,
                    'seuil_recouvrement' => $seuilRecouvrement,
                ],
                'alertes' => $alertes,
                'entreprises' => [
                    'total' => Entreprise::count(),
                    'clients' => Entreprise::where('statut', 'client')->count(),
                    'prospects' => Entreprise::where('statut', 'prospect')->count(),
                ],
                'missions' => [
                    'total' => $missionQuery()->count(),
                    'en_cours' => $missionQuery()->where('statut', 'en_cours')->count(),
                    'terminees' => $missionQuery()->where('statut', 'terminee')->count(),
                    'ca_ht' => (float) $missionQuery()->sum('prix_ht'),
                ],
                'factures' => [
                    'total' => $factureQuery()->count(),
                    'en_attente' => $factureQuery()->where('statut_paiement', 'en_attente')->count(),
                    'partielles' => $factureQuery()->where('statut_paiement', 'partiel')->count(),
                    'soldees' => $factureQuery()->where('statut_paiement', 'solde')->count(),
                    'ca_ttc' => $caTtc,
                    'total_paye' => $totalPaye,
                    'total_impaye' => $totalImpaye,
                    'en_retard' => $enRetard,
                ],
                'devis' => [
                    'total' => Devis::count(),
                    'en_attente' => Devis::whereIn('statut', ['brouillon', 'envoye'])->count(),
                    'acceptes' => Devis::where('statut', 'accepte')->count(),
                    'ca_potentiel' => (float) Devis::whereIn('statut', ['brouillon', 'envoye'])->sum('montant_ttc'),
                ],
                'recentes' => [
                    'factures' => Facture::with('entreprise')
                        ->where('type', 'FF')
                        ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                        ->latest('date_facture')
                        ->take(5)
                        ->get(['id', 'entreprise_id', 'numero', 'montant_ttc', 'montant_paye', 'statut_paiement', 'date_facture', 'date_echeance']),
                    'missions' => Mission::with('entreprise', 'prestation')
                        ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                        ->latest('created_at')
                        ->take(5)
                        ->get(['id', 'entreprise_id', 'prestation_id', 'reference', 'prix_ht', 'statut', 'date_debut']),
                ],
            ],
        ]);
    }
}
