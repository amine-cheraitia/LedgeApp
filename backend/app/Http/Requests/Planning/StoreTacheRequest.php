<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class StoreTacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'statut' => ['sometimes', 'in:a_faire,en_cours,terminee,bloquee'],
            'date_echeance' => ['nullable', 'date'],
            'priorite' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ];
    }
}
