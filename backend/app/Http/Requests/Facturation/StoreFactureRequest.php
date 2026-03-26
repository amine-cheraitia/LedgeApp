<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;

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
            'entreprise_id' => ['required', 'exists:entreprises,id'],
            'mission_id' => ['nullable', 'exists:missions,id'],
            'devis_id' => ['nullable', 'exists:devis,id'],
            'type' => ['required', 'in:FF,FA'],
            'facture_origine_id' => ['nullable', 'exists:factures,id'],
            'date_facture' => ['required', 'date'],
            'date_echeance' => ['required', 'date', 'after_or_equal:date_facture'],
            'notes' => ['nullable', 'string'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.prestation_id' => ['nullable', 'exists:prestations,id'],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire_ht' => ['required', 'numeric', 'min:0'],
        ];
    }
}
