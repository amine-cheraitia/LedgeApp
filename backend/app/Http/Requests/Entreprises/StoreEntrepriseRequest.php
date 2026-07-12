<?php

declare(strict_types=1);

namespace App\Http\Requests\Entreprises;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntrepriseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raison_sociale' => ['required', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:50', Rule::unique('entreprises', 'nif')->whereNull('deleted_at')],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('entreprises', 'nis')->whereNull('deleted_at')],
            'num_rc' => ['nullable', 'string', 'max:50'],
            'article_imposition' => ['nullable', 'string', 'max:50'],
            'regime_fiscal' => ['required', 'string', 'max:50'],
            'categorie' => ['required', 'string', 'max:50'],
            'secteur_activite' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'ville' => ['nullable', 'string', 'max:100'],
            'wilaya' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_principal' => ['nullable', 'string', 'max:255'],
            'statut' => ['required', 'in:prospect,client'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
