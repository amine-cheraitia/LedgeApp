<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactureRequest extends FormRequest
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
            'mission_id'  => ['required', 'exists:missions,id'],
            'exercice_id' => ['nullable', 'integer', Rule::exists('exercices', 'id')->where('statut', 'ouvert')],
            'date_facture' => ['required', 'date'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exercice_id.exists' => "L'exercice sélectionné n'existe pas ou est clôturé.",
        ];
    }
}
