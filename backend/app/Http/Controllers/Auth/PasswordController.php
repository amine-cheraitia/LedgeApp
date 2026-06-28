<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function __construct(private readonly InvitationService $invitationService) {}

    /**
     * Mot de passe oublie (libre-service). Reponse generique pour ne pas reveler
     * l'existence d'un compte (anti-enumeration d'emails).
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user) {
            $this->invitationService->envoyerReinitialisation($user);
        }

        return response()->json([
            'message' => 'Si un compte existe pour cette adresse, un email de reinitialisation vient d\'etre envoye.',
        ]);
    }

    /**
     * Definition / reinitialisation du mot de passe via un jeton a usage unique.
     * Sert aussi bien a l'invitation (premiere definition) qu'au mot de passe oublie.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'token' => ['Ce lien est invalide ou a expire. Demandez-en un nouveau.'],
            ]);
        }

        return response()->json([
            'message' => 'Votre mot de passe a ete defini. Vous pouvez maintenant vous connecter.',
        ]);
    }
}
