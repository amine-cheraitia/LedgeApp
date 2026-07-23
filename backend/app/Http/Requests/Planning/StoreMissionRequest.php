<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use App\Http\Requests\Planning\Concerns\ValidatesCollaborateursStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMissionRequest extends FormRequest
{
    use ValidatesCollaborateursStaff;

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
            'entreprise_id' => ['required', 'integer', 'exists:entreprises,id'],
            'prestation_id' => ['required', 'integer', 'exists:prestations,id'],
            'exercice_id' => ['nullable', 'integer', Rule::exists('exercices', 'id')->where('statut', 'ouvert')],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'collaborateur_ids' => ['nullable', 'array'],
            'collaborateur_ids.*' => ['integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
