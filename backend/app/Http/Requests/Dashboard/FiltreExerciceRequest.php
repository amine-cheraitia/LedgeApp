<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtre commun des endpoints de statistiques / KPI : un exercice optionnel.
 * Les FormRequests valident les query params des GET comme un body — permet
 * une strategie de validation uniforme (plus de $request->validate() inline).
 */
class FiltreExerciceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
        ];
    }

    public function exerciceId(): ?int
    {
        return $this->integer('exercice_id') ?: null;
    }
}
