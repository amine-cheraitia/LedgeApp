<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
            'role' => ['required', 'string', 'exists:roles,name'],
            'entreprise_id' => ['nullable', 'exists:entreprises,id'],
        ];
    }
}
