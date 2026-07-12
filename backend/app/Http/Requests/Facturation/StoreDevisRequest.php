<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use App\Models\Exercice;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
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
     * La date du devis doit tomber dans l'exercice de rattachement : garantit la
     * coherence entre le numero (DV{annee}), l'exercice_id et la date reelle.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['date_devis', 'exercice_id'])) {
                return;
            }

            $exercice = $this->filled('exercice_id')
                ? Exercice::find($this->input('exercice_id'))
                : Exercice::current();

            if (! $exercice || ! $exercice->date_ouverture || ! $exercice->date_cloture) {
                return;
            }

            $date = Carbon::parse($this->input('date_devis'))->startOfDay();

            if ($date->lt($exercice->date_ouverture) || $date->gt($exercice->date_cloture)) {
                $validator->errors()->add(
                    'date_devis',
                    "La date du devis doit être comprise dans l'exercice {$exercice->annee} "
                    ."(du {$exercice->date_ouverture->format('d/m/Y')} au {$exercice->date_cloture->format('d/m/Y')})."
                );
            }
        });
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
