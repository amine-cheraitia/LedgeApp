<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de reinitialisation de mot de passe (libre-service depuis l'ecran de
 * connexion). Reutilise le meme lien securise que l'invitation.
 */
class ReinitialisationMotDePasseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nom,
        public readonly string $lien,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reinitialisation de votre mot de passe Ledge');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reset-password',
            with: [
                'nom' => $this->nom,
                'lien' => $this->lien,
            ],
        );
    }
}
