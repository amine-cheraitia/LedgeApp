<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; background: #f8fafc; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .header { background: #1e40af; padding: 24px 32px; }
        .header-brand { color: #ffffff; font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .header-sub { color: #93c5fd; font-size: 12px; margin-top: 4px; }
        .body { padding: 32px; }
        .message { line-height: 1.7; color: #334155; }
        .cta-wrap { text-align: center; margin: 28px 0; }
        .cta { display: inline-block; background: #1e40af; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold; font-size: 15px; }
        .fallback { font-size: 12px; color: #64748b; word-break: break-all; margin-top: 20px; }
        .fallback a { color: #1e40af; }
        .note { font-size: 12px; color: #64748b; margin-top: 16px; }
        .footer { border-top: 1px solid #e2e8f0; padding: 16px 32px; font-size: 11px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="header-brand">LEDGE</div>
        <div class="header-sub">Cabinet de conseil &amp; expertise comptable</div>
    </div>

    <div class="body">
        <p>Bonjour{{ $nom ? ' '.$nom : '' }},</p>
        <p class="message">
            Vous avez demandé la réinitialisation de votre mot de passe Ledge.
            Choisissez un nouveau mot de passe en cliquant sur le bouton ci-dessous.
        </p>

        <div class="cta-wrap">
            <a href="{{ $lien }}" class="cta">Réinitialiser mon mot de passe</a>
        </div>

        <p class="note">
            Ce lien est valable {{ intdiv((int) config('auth.passwords.users.expire'), 60) }} heures et ne peut être utilisé qu'une seule fois.
            Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe restera inchangé.
        </p>

        <p class="fallback">
            Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </p>
    </div>

    <div class="footer">
        Ce message a été généré par Ledge. Merci de ne pas y répondre directement.
    </div>
</div>
</body>
</html>
