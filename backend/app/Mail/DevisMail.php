<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Devis;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DevisMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Devis $devis) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre devis '.$this->devis->numero);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.document',
            with: [
                'intro' => 'Veuillez trouver ci-joint votre devis. Nous restons a votre disposition pour toute question.',
                'entreprise' => $this->devis->entreprise,
                'numero' => $this->devis->numero,
                'dateLabel' => 'Date du devis',
                'date' => $this->devis->date_devis,
                'montantTtc' => (float) $this->devis->montant_ttc,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(PdfService::class)->genererDevis($this->devis);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'devis-'.$this->devis->numero.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
