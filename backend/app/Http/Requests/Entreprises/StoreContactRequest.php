<?php

declare(strict_types=1);

namespace App\Http\Requests\Entreprises;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'poste' => ['nullable', 'string', 'max:255'],
            'est_principal' => ['boolean'],
        ];
    }
}
