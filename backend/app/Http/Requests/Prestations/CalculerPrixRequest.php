<?php

declare(strict_types=1);

namespace App\Http\Requests\Prestations;

use Illuminate\Foundation\Http\FormRequest;

class CalculerPrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'regime_fiscal' => ['required', 'string'],
            'categorie' => ['required', 'string'],
        ];
    }
}
