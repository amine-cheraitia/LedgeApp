<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDevisRequest extends FormRequest
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
            'entreprise_id' => ['sometimes', 'exists:entreprises,id'],
            'prestation_id' => ['sometimes', 'exists:prestations,id'],
            'date_devis' => ['sometimes', 'date'],
            'date_validite' => ['sometimes', 'date', 'after_or_equal:date_devis'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
