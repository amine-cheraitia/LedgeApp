<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Http\Resources\Facturation\FactureResource;
use App\Models\Facture;
use App\Services\PdfService;
use App\Services\PortailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

class PortailFactureController extends Controller
{
    public function __construct(
        private readonly PortailService $portailService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $factures = $this->portailService->listerFactures(
            $request->user()->entreprise_id,
            $request->only(['exercice_id', 'statut_paiement', 'per_page'])
        );

        return FactureResource::collection($factures);
    }

    public function pdf(Request $request, Facture $facture): HttpResponse
    {
        abort_if(
            $facture->entreprise_id !== $request->user()->entreprise_id,
            403,
            'Acces non autorise.'
        );

        // L'endpoint ne sert que les factures (FF), pas les avoirs (FA) qui partagent la table :
        // aligne le PDF sur le filtre type='FF' de la liste du portail.
        abort_if($facture->type !== 'FF', 404, 'Facture introuvable.');

        return $this->pdfService->genererFacture($facture)
            ->download('facture-'.$facture->numero.'.pdf');
    }
}
