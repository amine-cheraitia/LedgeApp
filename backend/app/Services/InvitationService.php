<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\InvitationCompteMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Service transverse d'invitation : genere un lien securise a usage unique
 * permettant a un utilisateur (client ou membre du staff) de definir lui-meme
 * son mot de passe. Aucun mot de passe n'est jamais genere, affiche ni transmis.
 */
class InvitationService
{
    /**
     * Genere un jeton d'invitation, envoie l'email de definition de mot de passe
     * et retourne l'URL correspondante (repli affichable a l'admin si l'email
     * n'arrive pas — l'URL ne contient qu'un jeton a usage unique, pas de mot de passe).
     */
    public function inviter(User $user): string
    {
        $token = Password::createToken($user);
        $url = $this->lienDefinitionMotDePasse($token, $user->email);

        Mail::to($user->email)->send(new InvitationCompteMail($user->name, $url));

        return $url;
    }

    /**
     * Envoie un lien de reinitialisation de mot de passe (libre-service).
     */
    public function envoyerReinitialisation(User $user): void
    {
        $token = Password::createToken($user);
        $url = $this->lienDefinitionMotDePasse($token, $user->email);

        Mail::to($user->email)->send(new ReinitialisationMotDePasseMail($user->name, $url));
    }

    /**
     * Construit l'URL frontend de definition / reinitialisation du mot de passe.
     */
    public function lienDefinitionMotDePasse(string $token, string $email): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/definir-mot-de-passe?'
            .http_build_query(['token' => $token, 'email' => $email]);
    }
}
