<?php

declare(strict_types=1);

namespace App\Http\Controllers\Entreprises;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entreprises\StoreContactRequest;
use App\Http\Requests\Entreprises\UpdateContactRequest;
use App\Http\Resources\Entreprises\ContactResource;
use App\Models\Contact;
use App\Models\Entreprise;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    public function index(Entreprise $entreprise): AnonymousResourceCollection
    {
        $contacts = $entreprise->contacts()->orderByDesc('est_principal')->orderBy('nom')->get();

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request, Entreprise $entreprise): JsonResponse
    {
        $contact = $this->contactService->creer($entreprise, $request->validated());

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateContactRequest $request, Entreprise $entreprise, Contact $contact): ContactResource
    {
        $contact = $this->contactService->mettreAJour($contact, $request->validated());

        return new ContactResource($contact);
    }

    public function destroy(Entreprise $entreprise, Contact $contact): JsonResponse
    {
        $this->contactService->supprimer($contact);

        return response()->json(['message' => 'Contact supprime.']);
    }
}
