<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(private readonly InvitationService $invitationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->role, fn ($q, $r) => $q->role($r))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // Placeholder inutilisable : l'utilisateur definira lui-meme son mot de
            // passe via le lien d'invitation. L'admin ne manipule aucun mot de passe.
            'password' => Hash::make(Str::random(40)),
            'entreprise_id' => $validated['entreprise_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        $invitationUrl = $this->invitationService->inviter($user);

        $user->load('roles');

        return (new UserResource($user))
            ->additional(['invitation_url' => $invitationUrl])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $user->load('roles', 'entreprise');

        return new UserResource($user);
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'entreprise_id' => ['nullable', 'exists:entreprises,id'],
        ]);

        $user->update(collect($validated)->except('role')->toArray());

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Renvoie une invitation (lien de definition de mot de passe) a un utilisateur.
     */
    public function renvoyerInvitation(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $invitationUrl = $this->invitationService->inviter($user);

        return response()->json([
            'message' => 'Invitation renvoyee a '.$user->email.'.',
            'invitation_url' => $invitationUrl,
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
