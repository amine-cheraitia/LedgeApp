<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Facture;
use App\Models\Relance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RelanceClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Facture $facture,
        public readonly Relance $relance,
    ) {}

    public function envelope(): Envelope
    {
        $niveauLabels = [
            1 => 'Rappel de paiement',
            2 => 'Relance de paiement',
            3 => 'Mise en demeure',
        ];

        $sujet = ($niveauLabels[$this->relance->niveau] ?? 'Relance')
            .' — Facture '.$this->facture->numero;

        return new Envelope(subject: $sujet);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.relance',
            with: [
                'facture' => $this->facture,
                'relance' => $this->relance,
                'message' => $this->relance->message,
            ],
        );
    }
}
