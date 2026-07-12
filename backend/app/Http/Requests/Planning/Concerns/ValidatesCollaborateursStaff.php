<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;

/**
 * Garantit que chaque collaborateur_id designe un membre du personnel
 * (role admin ou collaborateur) et non un client ou un compte hors staff.
 */
trait ValidatesCollaborateursStaff
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = array_values(array_unique(array_filter((array) $this->input('collaborateur_ids', []))));

            if ($ids === []) {
                return;
            }

            $staff = User::whereIn('id', $ids)->role(['admin', 'collaborateur'])->count();

            if ($staff !== count($ids)) {
                $validator->errors()->add(
                    'collaborateur_ids',
                    'Les collaborateurs doivent etre des membres du personnel (admin ou collaborateur).'
                );
            }
        });
    }
}
