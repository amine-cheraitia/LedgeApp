<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use App\Http\Requests\Planning\Concerns\ValidatesTacheDates;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTacheRequest extends FormRequest
{
    use ValidatesTacheDates;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'titre' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id', $this->assignableRule()],
            'statut' => ['sometimes', 'in:a_faire,en_cours,terminee,bloquee'],
            'priorite' => ['sometimes', 'integer', 'min:1', 'max:4'],
        ], $this->tacheDateRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tacheDateMessages();
    }
}
