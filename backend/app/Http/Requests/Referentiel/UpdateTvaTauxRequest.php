<?php

declare(strict_types=1);

namespace App\Http\Requests\Referentiel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTvaTauxRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'taux' => ['required', 'numeric', 'min:0', 'max:100'],
            'designation' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['standard', 'exonere'])],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'actif' => ['boolean'],
        ];
    }
}
