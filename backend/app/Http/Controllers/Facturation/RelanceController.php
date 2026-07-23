<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StoreRelanceRequest;
use App\Http\Resources\Facturation\RelanceResource;
use App\Models\Facture;
use App\Models\Relance;
use App\Services\RelanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelanceController extends Controller
{
    public function __construct(private readonly RelanceService $relanceService) {}

    public function index(Facture $facture): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Relance::class);

        $relances = $facture->relances()
            ->with('sentBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return RelanceResource::collection($relances);
    }

    public function store(StoreRelanceRequest $request, Facture $facture): JsonResponse
    {
        $this->authorize('create', Relance::class);

        try {
            $relance = $this->relanceService->envoyerManuelle(
                $facture,
                $request->validated()['niveau'],
                $request->user()->id,
            );

            return (new RelanceResource($relance))->response()->setStatusCode(201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
