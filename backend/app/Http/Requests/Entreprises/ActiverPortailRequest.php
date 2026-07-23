<?php

declare(strict_types=1);

namespace App\Http\Requests\Entreprises;

use Illuminate\Foundation\Http\FormRequest;

class ActiverPortailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }
}
