<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Mission;
use App\Services\MissionService;
use App\Services\PdfService;
use App\Services\PortailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PortailDocumentController extends Controller
{
    public function __construct(
        private readonly PortailService $portailService,
        private readonly MissionService $missionService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = $this->portailService->listerDocuments($request->user()->entreprise_id);

        return MissionResource::collection($documents);
    }

    public function conventionPdf(Request $request, Mission $mission): Response
    {
        abort_if(
            $mission->entreprise_id !== $request->user()->entreprise_id || ! $mission->visible_portail,
            403,
            'Acces non autorise.'
        );

        abort_if(! $mission->convention_numero, 404, 'Convention non generee.');

        return $this->pdfService->genererConvention($mission)
            ->stream('convention-'.$mission->convention_numero.'.pdf');
    }

    public function mandatPdf(Request $request, Mission $mission): Response
    {
        abort_if(
            $mission->entreprise_id !== $request->user()->entreprise_id || ! $mission->visible_portail,
            403,
            'Acces non autorise.'
        );

        abort_if(! $mission->mandat_numero, 404, 'Mandat non genere.');

        return $this->pdfService->genererMandat($mission)
            ->stream('mandat-'.$mission->mandat_numero.'.pdf');
    }
}
