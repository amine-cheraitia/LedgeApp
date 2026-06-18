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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'taux.numeric' => 'Le taux doit etre un nombre.',
            'taux.min' => 'Le taux ne peut pas etre inferieur a 0 %.',
            'taux.max' => 'Le taux ne peut pas depasser 100 %.',
            'type.in' => 'La categorie doit etre « standard » ou « exonere ».',
            'date_debut.required' => 'La date de debut est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin doit etre posterieure ou egale a la date de debut.',
            'designation.required' => 'La designation est obligatoire.',
        ];
    }
}
