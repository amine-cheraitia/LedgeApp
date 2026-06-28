<?php

declare(strict_types=1);

namespace App\Http\Controllers\Entreprises;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entreprises\StoreEntrepriseRequest;
use App\Http\Requests\Entreprises\UpdateEntrepriseRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Entreprises\EntrepriseResource;
use App\Models\Entreprise;
use App\Services\EntrepriseService;
use App\Services\PortailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntrepriseController extends Controller
{
    public function __construct(private readonly EntrepriseService $entrepriseService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Entreprise::class);

        $entreprises = $this->entrepriseService->lister($request->only([
            'search', 'statut', 'wilaya', 'per_page',
        ]));

        return EntrepriseResource::collection($entreprises);
    }

    public function wilayas(): JsonResponse
    {
        $this->authorize('viewAny', Entreprise::class);

        return response()->json(['data' => $this->entrepriseService->wilayas()]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Entreprise::class);

        return $this->entrepriseService->exportCsv($request->only([
            'search', 'statut', 'wilaya',
        ]));
    }

    public function store(StoreEntrepriseRequest $request): JsonResponse
    {
        $this->authorize('create', Entreprise::class);

        $entreprise = $this->entrepriseService->creer($request->validated());

        return (new EntrepriseResource($entreprise))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Entreprise $entreprise): EntrepriseResource
    {
        $this->authorize('view', $entreprise);

        $entreprise->load('users', 'contacts');
        $entreprise->loadCount('missions', 'factures');

        return new EntrepriseResource($entreprise);
    }

    public function update(UpdateEntrepriseRequest $request, Entreprise $entreprise): EntrepriseResource
    {
        $this->authorize('update', $entreprise);

        $entreprise->update($request->validated());

        return new EntrepriseResource($entreprise);
    }

    public function destroy(Entreprise $entreprise): JsonResponse
    {
        $this->authorize('delete', $entreprise);
        if ($entreprise->missions()->exists() || $entreprise->devis()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette entreprise : des missions ou devis y sont associes.',
            ], 409);
        }

        $entreprise->delete();

        return response()->json(['message' => 'Entreprise supprimee.']);
    }

    public function activerPortail(Request $request, Entreprise $entreprise, PortailService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        $result = $service->activerPortail($entreprise, $validated['name'], $validated['email']);

        return response()->json([
            'message' => 'Acces portail active. Une invitation a ete envoyee a '.$validated['email'].'.',
            'user' => new UserResource($result['user']),
            'invitation_url' => $result['invitation_url'],
        ], 201);
    }

    public function renvoyerInvitation(Entreprise $entreprise, PortailService $service): JsonResponse
    {
        $result = $service->renvoyerInvitation($entreprise);

        return response()->json([
            'message' => 'Invitation renvoyee a '.$result['user']->email.'.',
            'invitation_url' => $result['invitation_url'],
        ]);
    }

    public function togglePortail(Entreprise $entreprise, PortailService $service): JsonResponse
    {
        $user = $service->togglePortail($entreprise);

        return response()->json([
            'message' => $user->portail_actif ? 'Acces portail reactive.' : 'Acces portail desactive.',
            'portail_actif' => $user->portail_actif,
        ]);
    }
}
