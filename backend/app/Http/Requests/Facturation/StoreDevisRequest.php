<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevisRequest extends FormRequest
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
            'entreprise_id' => ['required', 'exists:entreprises,id'],
            'prestation_id' => ['required', 'exists:prestations,id'],
            'exercice_id' => ['nullable', 'integer', Rule::exists('exercices', 'id')->where('statut', 'ouvert')],
            'date_devis' => ['required', 'date'],
            'date_validite' => ['required', 'date', 'after_or_equal:date_devis'],
            'type_tva' => ['nullable', Rule::in(['standard', 'exonere'])],
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
