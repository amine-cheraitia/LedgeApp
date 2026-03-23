<?php

namespace App\Http\Requests\Exercices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee' => ['sometimes', 'integer', 'min:2020', 'max:2100', 'unique:exercices,annee,'.$this->route('exercice')->id],
            'date_ouverture' => ['sometimes', 'date'],
            'date_cloture' => ['sometimes', 'date', 'after:date_ouverture'],
            'statut' => ['sometimes', 'in:ouvert,cloture'],
        ];
    }
}
