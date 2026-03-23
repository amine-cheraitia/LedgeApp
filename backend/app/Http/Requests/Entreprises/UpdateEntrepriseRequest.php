<?php

namespace App\Http\Requests\Entreprises;

use Illuminate\Foundation\Http\FormRequest;

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
            'nif' => ['nullable', 'string', 'max:50'],
            'nis' => ['nullable', 'string', 'max:50'],
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
            'statut' => ['sometimes', 'in:prospect,client,ancien_client'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
