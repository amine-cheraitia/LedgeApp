<?php

namespace App\Http\Requests\Entreprises;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntrepriseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raison_sociale' => ['sometimes', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:50', Rule::unique('entreprises', 'nif')->ignore($this->route('entreprise'))->whereNull('deleted_at')],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('entreprises', 'nis')->ignore($this->route('entreprise'))->whereNull('deleted_at')],
            'num_rc' => ['nullable', 'string', 'max:50'],
            'article_imposition' => ['nullable', 'string', 'max:50'],
            'regime_fiscal' => ['sometimes', 'string', 'max:50'],
            'categorie' => ['sometimes', 'string', 'max:50'],
            'secteur_activite' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'ville' => ['nullable', 'string', 'max:100'],
            'wilaya' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_principal' => ['nullable', 'string', 'max:255'],
            // 'statut' n'est volontairement pas modifiable ici : la bascule
            // prospect -> client est automatique via MissionObserver (MissionCreated).
            // Toute modification manuelle contournerait ce garde-fou metier.
            'notes' => ['nullable', 'string'],
        ];
    }
}
