<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckTacheConflitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation métier (admin) est vérifiée dans le contrôleur.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collaborateur_id' => ['required', 'integer', 'exists:users,id'],
            'date_debut' => ['nullable', 'date'],
            'date_echeance' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'exclude_tache_id' => ['nullable', 'integer', 'exists:taches,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (blank($this->input('date_debut')) && blank($this->input('date_echeance'))) {
                $validator->errors()->add('date_debut', 'Au moins une date (début ou échéance) est requise.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_echeance.after_or_equal' => "L'échéance doit être postérieure ou égale à la date de début.",
        ];
    }
}
