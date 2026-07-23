<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de reinitialisation de mot de passe (libre-service depuis l'ecran de
 * connexion). Reutilise le meme lien securise que l'invitation.
 *
 * Mis en file (ShouldQueue) : l'envoi est asynchrone et le temps de reponse de
 * /forgot-password ne depend plus de l'existence du compte (anti-oracle temporel).
 */
class ReinitialisationMotDePasseMail extends Mailable implements ShouldQueue
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
