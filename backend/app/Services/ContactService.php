<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\Entreprise;

class ContactService
{
    public function creer(Entreprise $entreprise, array $data): Contact
    {
        if (! empty($data['est_principal'])) {
            $entreprise->contacts()->update(['est_principal' => false]);
        }

        return $entreprise->contacts()->create($data);
    }

    public function mettreAJour(Contact $contact, array $data): Contact
    {
        if (! empty($data['est_principal'])) {
            $contact->entreprise->contacts()
                ->where('id', '!=', $contact->id)
                ->update(['est_principal' => false]);
        }

        $contact->update($data);

        return $contact->fresh();
    }

    public function supprimer(Contact $contact): void
    {
        $contact->delete();
    }
}
