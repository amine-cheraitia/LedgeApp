<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Facture;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FactureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Facture $facture) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre facture '.$this->facture->numero);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.document',
            with: [
                'intro' => 'Veuillez trouver ci-joint votre facture. Nous restons a votre disposition pour toute question.',
                'entreprise' => $this->facture->entreprise,
                'numero' => $this->facture->numero,
                'dateLabel' => 'Date de facture',
                'date' => $this->facture->date_facture,
                'montantTtc' => (float) $this->facture->montant_ttc,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(PdfService::class)->genererFacture($this->facture);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'facture-'.$this->facture->numero.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
