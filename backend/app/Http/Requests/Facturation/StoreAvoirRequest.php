<?php

declare(strict_types=1);

namespace App\Http\Requests\Facturation;

use App\Models\Exercice;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * La date de l'avoir doit tomber dans l'exercice courant (celui de rattachement
     * et de numerotation FA{annee}) : evite un numero/exercice incoherent avec la date.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('date_avoir')) {
                return;
            }

            $exercice = Exercice::current();

            if (! $exercice || ! $exercice->date_ouverture || ! $exercice->date_cloture) {
                return;
            }

            $date = Carbon::parse($this->input('date_avoir'))->startOfDay();

            if ($date->lt($exercice->date_ouverture) || $date->gt($exercice->date_cloture)) {
                $validator->errors()->add(
                    'date_avoir',
                    "La date de l'avoir doit être comprise dans l'exercice {$exercice->annee} "
                    ."(du {$exercice->date_ouverture->format('d/m/Y')} au {$exercice->date_cloture->format('d/m/Y')})."
                );
            }
        });
    }
}
