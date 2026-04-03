<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Http\Resources\Facturation\FactureResource;
use App\Models\Facture;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

class PortailFactureController extends Controller
{
    public function __construct(private PdfService $pdfService) {}

    /**
     * Liste les factures de l'entreprise du client connecte.
     * Scope isolation : entreprise_id du user connecte uniquement.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $entrepriseId = $request->user()->entreprise_id;

        $factures = Facture::with('mission', 'lignes')
            ->where('entreprise_id', $entrepriseId)
            ->where('type', 'FF')
            ->when($request->exercice_id, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($request->statut_paiement, fn ($q, $v) => $q->where('statut_paiement', $v))
            ->latest('date_facture')
            ->paginate($request->per_page ?? 15);

        return FactureResource::collection($factures);
    }

    /**
     * Telecharge le PDF d'une facture appartenant a l'entreprise du client.
     */
    public function pdf(Request $request, Facture $facture): HttpResponse
    {
        abort_if(
            $facture->entreprise_id !== $request->user()->entreprise_id,
            403,
            'Acces non autorise.'
        );

        return $this->pdfService->genererFacture($facture)
            ->download('facture-'.$facture->numero.'.pdf');
    }
}
