<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Audit\ActivityResource;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        // Le journal d'audit (donnees sensibles) reste reserve a l'admin, meme si
        // la route est deplacee ou refactorisee (defense en profondeur).
        abort_unless($request->user()->hasRole('admin'), 403);

        $activites = $this->auditService->lister($request->only([
            'event', 'causer_id', 'subject_type', 'date_debut', 'date_fin', 'per_page',
        ]));

        return ActivityResource::collection($activites);
    }
}
