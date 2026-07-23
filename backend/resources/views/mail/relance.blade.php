<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $relance->niveau === 3 ? 'Mise en demeure' : 'Relance' }} — Facture {{ $facture->numero }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; background: #f8fafc; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .header { background: #1e40af; padding: 24px 32px; }
        .header-brand { color: #ffffff; font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .header-sub { color: #93c5fd; font-size: 12px; margin-top: 4px; }
        .badge-niveau { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-top: 8px; }
        .badge-1 { background: #dbeafe; color: #1d4ed8; }
        .badge-2 { background: #fef3c7; color: #b45309; }
        .badge-3 { background: #fee2e2; color: #b91c1c; }
        .body { padding: 32px; }
        .message { white-space: pre-line; line-height: 1.7; color: #334155; }
        .facture-box { background: #f1f5f9; border-radius: 6px; padding: 16px 20px; margin: 24px 0; }
        .facture-box table { width: 100%; border-collapse: collapse; }
        .facture-box td { padding: 4px 0; font-size: 13px; color: #475569; }
        .facture-box td:last-child { text-align: right; font-weight: bold; color: #1e293b; }
        .footer { border-top: 1px solid #e2e8f0; padding: 16px 32px; font-size: 11px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="header-brand">LEDGE</div>
        <div class="header-sub">Cabinet de conseil &amp; expertise comptable</div>
        @php
            $badgeClass = match($relance->niveau) { 1 => 'badge-1', 2 => 'badge-2', 3 => 'badge-3', default => 'badge-1' };
            $badgeLabel = match($relance->niveau) { 1 => 'Niveau 1 — Rappel', 2 => 'Niveau 2 — Relance ferme', 3 => 'Niveau 3 — Mise en demeure', default => '' };
        @endphp
        <span class="badge-niveau {{ $badgeClass }}">{{ $badgeLabel }}</span>
    </div>

    <div class="body">
        <p class="message">{{ $corps }}</p>

        <div class="facture-box">
            <table>
                <tr>
                    <td>Numéro de facture</td>
                    <td>{{ $facture->numero }}</td>
                </tr>
                <tr>
                    <td>Date d'échéance</td>
                    <td>{{ $facture->date_echeance?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>Montant TTC</td>
                    <td>{{ number_format((float)$facture->montant_ttc, 2, ',', ' ') }} DA</td>
                </tr>
                <tr>
                    <td>Montant réglé</td>
                    <td>{{ number_format((float)$facture->montant_paye, 2, ',', ' ') }} DA</td>
                </tr>
                <tr>
                    <td><strong>Montant restant dû</strong></td>
                    <td><strong>{{ number_format($facture->montantRestant(), 2, ',', ' ') }} DA</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        Ce message a été généré automatiquement par Ledge. Merci de ne pas y répondre directement.
    </div>
</div>
</body>
</html>
