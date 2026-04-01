<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;

class ConvertirEnMissionRequest extends FormRequest
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
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'collaborateur_ids' => ['nullable', 'array'],
            'collaborateur_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
