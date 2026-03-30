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
            'prestation_id' => ['required', 'exists:prestations,id'],
            'date_devis' => ['required', 'date'],
            'date_validite' => ['required', 'date', 'after_or_equal:date_devis'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
