<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Http\Resources\Auth\StaffSelectResource;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        // Seul l'admin obtient l'annuaire complet (donnees sensibles) ; les autres
        // roles ne voient que le personnel, en version minimale (selects d'assignation).
        $estAdmin = $request->user()->hasRole('admin');

        $users = $this->userService->listerAnnuaire(
            $estAdmin,
            $request->search,
            $request->role,
            (int) ($request->per_page ?? 15),
        );

        return $estAdmin
            ? UserResource::collection($users)
            : StaffSelectResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $result = $this->userService->creerStaff($request->validated());

        return (new UserResource($result['user']))
            ->additional(['invitation_url' => $result['invitation_url']])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load('roles', 'entreprise');

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        return new UserResource($this->userService->mettreAJour($user, $request->validated()));
    }

    /**
     * Renvoie une invitation (lien de definition de mot de passe) a un utilisateur.
     */
    public function renvoyerInvitation(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        return response()->json([
            'message' => 'Invitation renvoyee a '.$user->email.'.',
            'invitation_url' => $this->userService->renvoyerInvitation($user),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
