<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMissionRequest extends FormRequest
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
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['sometimes', 'date', 'after_or_equal:date_debut'],
            'statut' => ['sometimes', 'in:en_cours,terminee,suspendue,annulee'],
            'collaborateur_ids' => ['nullable', 'array'],
            'collaborateur_ids.*' => ['integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'visible_portail' => ['sometimes', 'boolean'],
        ];
    }
}
