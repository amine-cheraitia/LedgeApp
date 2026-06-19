<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Messages utilisateur partages des flux d'envoi de mail (devis, factures, relances).
 * Source unique pour eviter la duplication entre FacturationService et RelanceService.
 */
final class MailMessages
{
    public const PAS_D_ADRESSE = "Cette entreprise n'a pas d'adresse mail. Renseignez l'email de l'entreprise ou de son contact principal avant l'envoi.";

    public const ENVOI_ECHOUE = "L'email n'a pas pu etre envoye. Verifiez la configuration d'envoi (SMTP).";
}
