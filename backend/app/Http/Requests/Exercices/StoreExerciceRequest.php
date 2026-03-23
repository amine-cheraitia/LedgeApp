<?php

namespace App\Http\Requests\Exercices;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee' => ['required', 'integer', 'min:2020', 'max:2100', 'unique:exercices,annee'],
            'date_ouverture' => ['required', 'date'],
            'date_cloture' => ['required', 'date', 'after:date_ouverture'],
            'statut' => ['required', 'in:ouvert,cloture'],
        ];
    }
}
