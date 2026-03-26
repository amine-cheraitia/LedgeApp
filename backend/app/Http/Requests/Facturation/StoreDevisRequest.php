<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;

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
            'date_devis' => ['required', 'date'],
            'date_validite' => ['required', 'date', 'after_or_equal:date_devis'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.prestation_id' => ['nullable', 'exists:prestations,id'],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire_ht' => ['required', 'numeric', 'min:0'],
        ];
    }
}
