<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $numero }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; background: #f8fafc; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .header { background: #1e40af; padding: 24px 32px; }
        .header-brand { color: #ffffff; font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .header-sub { color: #93c5fd; font-size: 12px; margin-top: 4px; }
        .body { padding: 32px; }
        .message { line-height: 1.7; color: #334155; }
        .doc-box { background: #f1f5f9; border-radius: 6px; padding: 16px 20px; margin: 24px 0; }
        .doc-box table { width: 100%; border-collapse: collapse; }
        .doc-box td { padding: 4px 0; font-size: 13px; color: #475569; }
        .doc-box td:last-child { text-align: right; font-weight: bold; color: #1e293b; }
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
        <p>Bonjour{{ $entreprise?->raison_sociale ? ' '.$entreprise->raison_sociale : '' }},</p>
        <p class="message">{{ $intro }}</p>

        <div class="doc-box">
            <table>
                <tr>
                    <td>{{ $dateLabel }}</td>
                    <td>{{ $date?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>Numéro</td>
                    <td>{{ $numero }}</td>
                </tr>
                <tr>
                    <td><strong>Montant TTC</strong></td>
                    <td><strong>{{ number_format($montantTtc, 2, ',', ' ') }} DA</strong></td>
                </tr>
            </table>
        </div>

        <p class="message">Le document complet est disponible en pièce jointe (PDF).</p>
    </div>

    <div class="footer">
        Ce message a été généré par Ledge. Merci de ne pas y répondre directement.
    </div>
</div>
</body>
</html>
