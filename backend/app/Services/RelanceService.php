<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\RelanceClientMail;
use App\Models\Facture;
use App\Models\Relance;
use App\Models\Setting;
use DomainException;
use Illuminate\Support\Facades\Mail;

class RelanceService
{
    /**
     * Envoie une relance manuelle sur une facture.
     * L'admin choisit le niveau (1, 2 ou 3).
     */
    public function envoyerManuelle(Facture $facture, int $niveau, int $userId): Relance
    {
        if ($facture->statut_paiement === 'solde') {
            throw new DomainException('Impossible d\'envoyer une relance sur une facture soldee.');
        }

        $email = $facture->entreprise?->emailDestinataire();

        if (empty($email)) {
            throw new DomainException("Cette entreprise n'a pas d'adresse mail. Renseignez l'email de l'entreprise ou de son contact principal avant l'envoi.");
        }

        $message = $this->resolveTemplate($niveau, $facture);

        $relance = Relance::create([
            'facture_id' => $facture->id,
            'sent_by' => $userId,
            'niveau' => $niveau,
            'type' => 'manuelle',
            'email_destinataire' => $email,
            'envoyee_le' => now(),
            'statut' => 'envoyee',
            'message' => $message,
        ]);

        Mail::to($email)->send(new RelanceClientMail($facture, $relance));

        return $relance->load('sentBy');
    }

    /**
     * Envoie une relance automatique (appelee par le cron job).
     * Verifie qu'aucune relance de ce niveau n'existe deja sur cette facture.
     */
    public function envoyerAutomatique(Facture $facture, int $niveau): ?Relance
    {
        if ($facture->statut_paiement === 'solde') {
            return null;
        }

        $dejaEnvoyee = Relance::where('facture_id', $facture->id)
            ->where('niveau', $niveau)
            ->whereIn('statut', ['en_attente', 'envoyee'])
            ->exists();

        if ($dejaEnvoyee) {
            return null;
        }

        // Verifier que le niveau precedent a bien ete envoye (sauf niveau 1)
        if ($niveau > 1) {
            $niveauPrecedentEnvoye = Relance::where('facture_id', $facture->id)
                ->where('niveau', $niveau - 1)
                ->where('statut', 'envoyee')
                ->exists();

            if (! $niveauPrecedentEnvoye) {
                return null;
            }
        }

        $email = $facture->entreprise?->emailDestinataire();

        if (empty($email)) {
            return null;
        }

        $message = $this->resolveTemplate($niveau, $facture);

        $relance = Relance::create([
            'facture_id' => $facture->id,
            'sent_by' => null,
            'niveau' => $niveau,
            'type' => 'automatique',
            'email_destinataire' => $email,
            'envoyee_le' => now(),
            'statut' => 'envoyee',
            'message' => $message,
        ]);

        Mail::to($email)->send(new RelanceClientMail($facture, $relance));

        return $relance;
    }

    /**
     * Resout le template du niveau donne en remplacant les variables.
     */
    private function resolveTemplate(int $niveau, Facture $facture): string
    {
        $template = Setting::get("relance_template_n{$niveau}", $this->defaultTemplate($niveau));

        return str_replace(
            ['{{client}}', '{{montant}}', '{{numero_facture}}', '{{echeance}}'],
            [
                $facture->entreprise?->raison_sociale ?? '',
                number_format($facture->montantRestant(), 2, ',', ' ').' DA',
                $facture->numero,
                $facture->date_echeance?->format('d/m/Y') ?? '',
            ],
            $template
        );
    }

    private function defaultTemplate(int $niveau): string
    {
        return match ($niveau) {
            1 => "Bonjour {{client}},\n\nNous vous rappelons que votre facture {{numero_facture}} d'un montant de {{montant}} est echue depuis le {{echeance}}.\n\nMerci de bien vouloir regulariser votre situation dans les meilleurs delais.\n\nCordialement,\nLe Cabinet",
            2 => "Bonjour {{client}},\n\nMalgre notre premiere relance, nous constatons que la facture {{numero_facture}} d'un montant de {{montant}} reste impayee depuis le {{echeance}}.\n\nNous vous demandons de regulariser cette situation sous 48 heures.\n\nCordialement,\nLe Cabinet",
            3 => "Bonjour {{client}},\n\nNous vous mettons en demeure de regler la facture {{numero_facture}} d'un montant de {{montant}} echue depuis le {{echeance}}.\n\nA defaut de paiement sous 72 heures, nous nous reservons le droit de prendre toutes mesures legales necessaires.\n\nCordialement,\nLe Cabinet",
            default => '',
        };
    }
}
