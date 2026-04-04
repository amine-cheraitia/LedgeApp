<?php

declare(strict_types=1);

namespace App\Http\Requests\Prestations;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:prestations,code'],
            'designation' => ['required', 'string', 'max:255'],
            'tarif_initial' => ['required', 'numeric', 'min:0'],
            'duree_mois' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ];
    }
}
