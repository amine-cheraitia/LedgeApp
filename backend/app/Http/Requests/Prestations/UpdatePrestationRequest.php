<?php

declare(strict_types=1);

namespace App\Http\Requests\Prestations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrestationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('prestations', 'code')->ignore($this->prestation)],
            'designation' => ['sometimes', 'string', 'max:255'],
            'tarif_initial' => ['sometimes', 'numeric', 'min:0'],
            'duree_mois' => ['sometimes', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ];
    }
}
