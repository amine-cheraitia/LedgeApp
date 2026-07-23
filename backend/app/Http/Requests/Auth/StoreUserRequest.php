<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation d'un utilisateur staff par l'admin.
 * Aucun mot de passe : l'utilisateur le definira lui-meme via le lien d'invitation.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            // Roles staff uniquement : la creation d'un client passe exclusivement
            // par l'activation du portail (PortailService::activerPortail).
            'role' => ['required', 'string', Rule::in(['admin', 'collaborateur', 'secretaire'])],
            'entreprise_id' => ['nullable', 'exists:entreprises,id'],
        ];
    }
}
