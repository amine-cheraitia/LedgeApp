<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvoirRequest extends FormRequest
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
            'montant_ht' => ['required', 'numeric', 'min:0.01'],
            'date_avoir' => ['required', 'date'],
            'motif' => ['required', 'string', 'max:500'],
        ];
    }
}
