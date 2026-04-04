<?php

declare(strict_types=1);

namespace App\Http\Controllers\Entreprises;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entreprises\StoreEntrepriseRequest;
use App\Http\Requests\Entreprises\UpdateEntrepriseRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Entreprises\EntrepriseResource;
use App\Models\Entreprise;
use App\Services\PortailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntrepriseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entreprises = Entreprise::query()
            ->when($request->search, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('raison_sociale', 'like', "%{$s}%")
                        ->orWhere('nif', 'like', "%{$s}%")
                        ->orWhere('nis', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('telephone', 'like', "%{$s}%")
                        ->orWhere('ville', 'like', "%{$s}%");
                });
            })
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->wilaya, fn ($q, $w) => $q->where('wilaya', $w))
            ->with('users')
            ->withCount('missions', 'factures')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return EntrepriseResource::collection($entreprises);
    }

    public function wilayas(): JsonResponse
    {
        $wilayas = Entreprise::query()
            ->whereNotNull('wilaya')
            ->distinct()
            ->orderBy('wilaya')
            ->pluck('wilaya');

        return response()->json(['data' => $wilayas]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Entreprise::query()
            ->when($request->search, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('raison_sociale', 'like', "%{$s}%")
                        ->orWhere('nif', 'like', "%{$s}%")
                        ->orWhere('nis', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('telephone', 'like', "%{$s}%")
                        ->orWhere('ville', 'like', "%{$s}%");
                });
            })
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->wilaya, fn ($q, $w) => $q->where('wilaya', $w))
            ->orderBy('raison_sociale');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="entreprises.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Raison sociale', 'NIF', 'NIS', 'Statut', 'Regime fiscal', 'Categorie', 'Wilaya', 'Ville', 'Email', 'Telephone'], ';');

            $query->chunk(500, function ($entreprises) use ($handle) {
                foreach ($entreprises as $e) {
                    fputcsv($handle, [
                        $e->raison_sociale,
                        $e->nif ?? '',
                        $e->nis ?? '',
                        $e->statut,
                        $e->regime_fiscal,
                        $e->categorie,
                        $e->wilaya ?? '',
                        $e->ville ?? '',
                        $e->email ?? '',
                        $e->telephone ?? '',
                    ], ';');
                }
            });

            fclose($handle);
        }, 'entreprises.csv', $headers);
    }

    public function store(StoreEntrepriseRequest $request): JsonResponse
    {
        $entreprise = Entreprise::create($request->validated());

        return (new EntrepriseResource($entreprise))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Entreprise $entreprise): EntrepriseResource
    {
        $entreprise->load('users', 'contacts');
        $entreprise->loadCount('missions', 'factures');

        return new EntrepriseResource($entreprise);
    }

    public function update(UpdateEntrepriseRequest $request, Entreprise $entreprise): EntrepriseResource
    {
        $entreprise->update($request->validated());

        return new EntrepriseResource($entreprise);
    }

    public function destroy(Entreprise $entreprise): JsonResponse
    {
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
            'message' => 'Acces portail active.',
            'user' => new UserResource($result['user']),
            'temporary_password' => $result['temporary_password'],
        ], 201);
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
