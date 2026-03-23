<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->role, fn ($q, $r) => $q->role($r))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
            'entreprise_id' => ['nullable', 'exists:entreprises,id'],
            'portail_actif' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'entreprise_id' => $validated['entreprise_id'] ?? null,
            'portail_actif' => $validated['portail_actif'] ?? false,
        ]);

        $user->assignRole($validated['role']);
        $user->load('roles');

        return (new UserResource($user))
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
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', Password::defaults()],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'entreprise_id' => ['nullable', 'exists:entreprises,id'],
            'portail_actif' => ['boolean'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update(collect($validated)->except('role')->toArray());

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $user->load('roles');

        return new UserResource($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
