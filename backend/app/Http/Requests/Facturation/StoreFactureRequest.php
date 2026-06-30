<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use App\Models\Exercice;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'mission_id' => ['required', 'exists:missions,id'],
            'exercice_id' => ['nullable', 'integer', Rule::exists('exercices', 'id')->where('statut', 'ouvert')],
            'date_facture' => ['required', 'date'],
            'type_tva' => ['nullable', Rule::in(['standard', 'exonere'])],
        ];
    }

    /**
     * Verifie que la date de facturation tombe dans l'exercice choisi
     * (entre date_ouverture et date_cloture).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['date_facture', 'exercice_id'])) {
                return; // ne pas empiler sur une date / un exercice deja invalide
            }

            $exercice = $this->filled('exercice_id')
                ? Exercice::find($this->input('exercice_id'))
                : Exercice::current();

            if (! $exercice) {
                return; // exercice non resolu : laisser les regles de base gerer
            }

            $date = Carbon::parse($this->input('date_facture'))->startOfDay();

            if ($date->lt($exercice->date_ouverture) || $date->gt($exercice->date_cloture)) {
                $validator->errors()->add(
                    'date_facture',
                    "La date de facturation doit être comprise dans l'exercice {$exercice->annee} "
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
            'mission_id.required' => 'La mission est obligatoire.',
            'mission_id.exists' => 'La mission sélectionnée est introuvable.',
            'date_facture.required' => 'La date de facturation est obligatoire.',
            'date_facture.date' => 'La date de facturation est invalide.',
            'type_tva.in' => 'La catégorie de TVA sélectionnée est invalide.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mission_id' => 'mission',
            'exercice_id' => 'exercice',
            'date_facture' => 'date de facturation',
            'type_tva' => 'catégorie de TVA',
        ];
    }
}
