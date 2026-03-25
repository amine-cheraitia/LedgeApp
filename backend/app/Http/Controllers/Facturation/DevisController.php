<?php

declare(strict_types=1);

namespace App\Http\Controllers\Facturation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturation\StoreDevisRequest;
use App\Http\Resources\Facturation\DevisResource;
use App\Models\Devis;
use App\Services\FacturationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DevisController extends Controller
{
    public function __construct(private FacturationService $facturationService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $devis = Devis::with('entreprise', 'lignes')
            ->when($request->entreprise_id, fn ($q, $id) => $q->where('entreprise_id', $id))
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->search, fn ($q, $s) => $q->where('numero', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return DevisResource::collection($devis);
    }

    public function store(StoreDevisRequest $request): JsonResponse
    {
        $devis = $this->facturationService->creerDevis(
            $request->safe()->except('lignes'),
            $request->validated('lignes'),
            $request->user()->id,
        );

        return (new DevisResource($devis->load('lignes', 'entreprise')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Devis $devis): DevisResource
    {
        return new DevisResource($devis->load('lignes', 'entreprise'));
    }

    public function update(Request $request, Devis $devis): DevisResource|JsonResponse
    {
        if ($devis->statut !== 'brouillon') {
            return response()->json([
                'message' => 'Seuls les devis en brouillon peuvent etre modifies.',
            ], 409);
        }

        $validated = $request->validate([
            'statut' => ['sometimes', 'in:brouillon,envoye,accepte,refuse,expire'],
            'notes' => ['nullable', 'string'],
            'date_validite' => ['sometimes', 'date'],
        ]);

        $devis->update($validated);

        return new DevisResource($devis->load('lignes', 'entreprise'));
    }

    public function destroy(Devis $devis): JsonResponse
    {
        if ($devis->statut !== 'brouillon') {
            return response()->json([
                'message' => 'Seuls les devis en brouillon peuvent etre supprimes.',
            ], 409);
        }

        $devis->lignes()->delete();
        $devis->delete();

        return response()->json(['message' => 'Devis supprime.']);
    }
}
