<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email d'invitation : l'utilisateur clique sur le lien et definit lui-meme
 * son mot de passe. Aucun mot de passe n'est transmis.
 */
class InvitationCompteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nom,
        public readonly string $lien,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activez votre acces Ledge');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invitation',
            with: [
                'nom' => $this->nom,
                'lien' => $this->lien,
            ],
        );
    }
}
