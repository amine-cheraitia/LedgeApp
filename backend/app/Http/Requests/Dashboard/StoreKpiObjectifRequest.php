<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreKpiObjectifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exercice_id' => ['required', 'integer', 'exists:exercices,id'],
            'type' => ['required', 'in:ca_ht,missions_cloturees,taches_terminees'],
            'valeur' => ['required', 'numeric', 'min:0'],
        ];
    }
}
