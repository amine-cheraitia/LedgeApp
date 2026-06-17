<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Http\Resources\Planning\MissionResource;
use App\Models\Mission;
use App\Services\PdfService;
use App\Services\PortailService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PortailMissionController extends Controller
{
    public function __construct(
        private readonly PortailService $portailService,
        private readonly PdfService $pdfService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $missions = $this->portailService->listerMissions(
            $request->user()->entreprise_id,
            $request->only(['statut', 'per_page'])
        );

        return MissionResource::collection($missions);
    }

    public function show(Request $request, Mission $mission): MissionResource
    {
        abort_if(
            $mission->entreprise_id !== $request->user()->entreprise_id,
            403,
            'Acces non autorise.'
        );

        return new MissionResource(
            $mission->load('prestation', 'exercice', 'taches.assignee')
        );
    }

    public function rapportPdf(Request $request, Mission $mission): Response
    {
        abort_if(
            $mission->entreprise_id !== $request->user()->entreprise_id,
            403,
            'Acces non autorise.'
        );

        return $this->pdfService
            ->genererRapportMission($mission, true)
            ->stream('rapport-mission-'.$mission->reference.'.pdf');
    }
}
