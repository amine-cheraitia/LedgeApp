<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise a jour d'un utilisateur par l'admin.
 * Aucun mot de passe : le changement passe par l'invitation / le mot de passe oublie.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'entreprise_id' => ['nullable', 'exists:entreprises,id'],
        ];
    }
}
