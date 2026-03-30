<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $now = Carbon::now();
        $debutMois = $now->copy()->startOfMonth();
        $debutAnnee = $now->copy()->startOfYear();

        return response()->json([
            'data' => [
                'entreprises' => [
                    'total' => Entreprise::count(),
                    'clients' => Entreprise::where('statut', 'client')->count(),
                    'prospects' => Entreprise::where('statut', 'prospect')->count(),
                ],
                'missions' => [
                    'total' => Mission::count(),
                    'en_cours' => Mission::where('statut', 'en_cours')->count(),
                    'terminees' => Mission::where('statut', 'terminee')->count(),
                    'ca_ht' => (float) Mission::sum('prix_ht'),
                ],
                'factures' => [
                    'total' => Facture::where('type', 'FF')->count(),
                    'en_attente' => Facture::where('type', 'FF')->where('statut_paiement', 'en_attente')->count(),
                    'partielles' => Facture::where('type', 'FF')->where('statut_paiement', 'partiel')->count(),
                    'soldees' => Facture::where('type', 'FF')->where('statut_paiement', 'solde')->count(),
                    'ca_ttc' => (float) Facture::where('type', 'FF')->sum('montant_ttc'),
                    'total_paye' => (float) Facture::where('type', 'FF')->sum('montant_paye'),
                    'total_impaye' => (float) Facture::where('type', 'FF')
                        ->whereIn('statut_paiement', ['en_attente', 'partiel'])
                        ->selectRaw('COALESCE(SUM(montant_ttc - montant_paye), 0) as total')
                        ->value('total'),
                    'en_retard' => Facture::where('type', 'FF')
                        ->whereIn('statut_paiement', ['en_attente', 'partiel'])
                        ->where('date_echeance', '<', $now)
                        ->count(),
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
                        ->latest('date_facture')
                        ->take(5)
                        ->get(['id', 'entreprise_id', 'numero', 'montant_ttc', 'montant_paye', 'statut_paiement', 'date_facture', 'date_echeance']),
                    'missions' => Mission::with('entreprise', 'prestation')
                        ->latest('created_at')
                        ->take(5)
                        ->get(['id', 'entreprise_id', 'prestation_id', 'reference', 'prix_ht', 'statut', 'date_debut']),
                ],
            ],
        ]);
    }
}
