<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis {{ $devis->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #222;
            line-height: 1.4;
        }

        /* ── Footer fixe ───────────────────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 28px;
            border-top: 1px solid #c8d4e3;
            text-align: center;
            font-size: 7.5pt;
            color: #999;
            padding-top: 6px;
        }

        /* ── Conteneur principal ───────────────────── */
        .page { padding: 0 0 40px 0; }

        /* ── Bande d'en-tête ───────────────────────── */
        .header-band {
            background-color: #1e3a5f;
            padding: 14px 22px 16px 22px;
            margin-bottom: 0;
        }
        .header-band td { vertical-align: middle; }

        /* Logo Ledge */
        .ledge-logo-box {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            width: 18px;
            height: 18px;
            text-align: center;
            line-height: 18px;
            border-radius: 3px;
            letter-spacing: 0;
        }
        .ledge-brand {
            font-size: 7.5pt;
            color: #7ba7d4;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            vertical-align: middle;
            padding-left: 4px;
        }
        .header-brand-row { margin-bottom: 8px; }

        .header-band .cabinet-nom {
            font-size: 16pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.5px;
        }
        .header-band .statut-cell {
            text-align: right;
            vertical-align: bottom;
        }

        /* ── Badge statut ──────────────────────────── */
        .badge {
            display: inline-block;
            padding: 4px 11px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-radius: 2px;
        }
        .badge-brouillon { background: #e5e7eb; color: #6b7280; }
        .badge-envoye    { background: #dbeafe; color: #1d4ed8; }
        .badge-accepte   { background: #dcfce7; color: #166534; }
        .badge-refuse    { background: #fee2e2; color: #991b1b; }
        .badge-expire    { background: #fef9c3; color: #854d0e; }

        /* ── Bande bleue claire sous le header ─────── */
        .subheader {
            background-color: #eef3f9;
            padding: 8px 22px;
            border-bottom: 2px solid #1e3a5f;
            margin-bottom: 22px;
        }
        .subheader td { font-size: 8.5pt; color: #4a5568; }
        .subheader .doc-title {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .subheader .doc-numero { font-size: 9pt; color: #6b7280; }

        /* ── Section infos + destinataire ──────────── */
        .meta-table { width: 100%; margin: 0 22px; margin-bottom: 20px; }
        .meta-table td { vertical-align: top; }

        .info-box {
            width: 46%;
            padding-right: 14px;
        }
        .dest-box {
            width: 48%;
        }

        .box-inner {
            border: 1px solid #c8d4e3;
            border-top: 3px solid #1e3a5f;
            padding: 10px 12px;
        }
        .box-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #1e3a5f;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row { width: 100%; margin-bottom: 4px; }
        .info-row .lbl { font-size: 8pt; color: #94a3b8; width: 70px; }
        .info-row .val { font-size: 9pt; color: #1e293b; font-weight: 500; }

        .dest-nom {
            font-size: 11pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 7px;
        }
        .dest-row { width: 100%; margin-bottom: 3px; }
        .dest-row .lbl { font-size: 8pt; color: #94a3b8; width: 55px; }
        .dest-row .val { font-size: 8.5pt; color: #334155; }

        /* ── Tableau prestation ─────────────────────── */
        .section-wrap { padding: 0 22px; margin-bottom: 18px; }

        .section-label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #1e3a5f;
            margin-bottom: 6px;
        }

        table.presta {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table.presta thead tr {
            background-color: #1e3a5f;
            color: #fff;
        }
        table.presta thead th {
            padding: 7px 10px;
            font-size: 8pt;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.presta thead th.r { text-align: right; }
        table.presta tbody tr { background-color: #f8fafd; }
        table.presta tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.presta tbody td.r { text-align: right; white-space: nowrap; }
        .presta-code  { font-size: 7.5pt; color: #94a3b8; margin-bottom: 2px; }
        .presta-label { font-size: 9.5pt; font-weight: bold; color: #1e293b; }
        .presta-desc  { font-size: 8pt; color: #64748b; margin-top: 3px; }

        /* ── Totaux ─────────────────────────────────── */
        .totaux-wrap { padding: 0 22px; margin-bottom: 28px; }
        table.totaux-outer { width: 100%; border-collapse: collapse; }
        table.totaux-inner { width: 100%; border-collapse: collapse; }
        table.totaux-inner tr td {
            padding: 5px 10px;
            font-size: 9pt;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        table.totaux-inner tr td:last-child {
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }
        table.totaux-inner tr.ttc td {
            background-color: #1e3a5f;
            color: #fff;
            font-size: 10.5pt;
            font-weight: bold;
            padding: 8px 10px;
            border-bottom: none;
        }

        /* ── Notes ──────────────────────────────────── */
        .notes-wrap { padding: 0 22px; margin-bottom: 28px; }
        .notes-inner {
            background-color: #f8fafd;
            border-left: 3px solid #1e3a5f;
            padding: 9px 12px;
            font-size: 9pt;
            color: #334155;
        }

        /* ── Signatures ─────────────────────────────── */
        .sign-wrap { padding: 0 22px; margin-top: 30px; }
        table.sign { width: 100%; border-collapse: collapse; }
        table.sign td { vertical-align: top; width: 50%; }
        table.sign td:last-child { text-align: right; }
        .sign-label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a5f;
            letter-spacing: 0.4px;
            margin-bottom: 50px;
        }
        .sign-line {
            border-top: 1px solid #334155;
            width: 75%;
            margin-top: 52px;
        }
        .sign-line-r {
            border-top: 1px solid #334155;
            width: 75%;
            margin-top: 52px;
            margin-left: auto;
        }
        .sign-sub {
            font-size: 8pt;
            color: #64748b;
            margin-top: 5px;
        }
        .sign-sub-r {
            font-size: 8pt;
            color: #64748b;
            margin-top: 5px;
            text-align: right;
        }
    </style>
</head>
<body>

<div class="footer">
    {{ $cabinet['nom'] }}
    @if($cabinet['nif']) &nbsp;·&nbsp; NIF&nbsp;: {{ $cabinet['nif'] }} @endif
    @if($cabinet['nis']) &nbsp;·&nbsp; NIS&nbsp;: {{ $cabinet['nis'] }} @endif
    @if($cabinet['rib']) &nbsp;·&nbsp; RIB&nbsp;: {{ $cabinet['rib'] }} @endif
</div>

<div class="page">

    {{-- ── En-tête bleu ─────────────────────────────────────── --}}
    <table class="header-band" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="header-brand-row">
                    <table cellpadding="0" cellspacing="0"><tr>
                        <td class="ledge-logo-box">L</td>
                        <td class="ledge-brand">Ledge</td>
                    </tr></table>
                </div>
                <div class="cabinet-nom">{{ $cabinet['nom'] }}</div>
            </td>
            <td class="statut-cell">
                <span class="badge badge-{{ $devis->statut }}">{{ ucfirst($devis->statut) }}</span>
            </td>
        </tr>
    </table>

    {{-- ── Sous-en-tête titre + numéro ──────────────────────── --}}
    <table class="subheader" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="doc-title">Devis</div>
                <div class="doc-numero">N° {{ $devis->numero }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Infos devis + Destinataire ────────────────────────── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 0 22px; margin-bottom: 20px;">
        <tr>
            <td style="width: 46%; vertical-align: top; padding-right: 14px;">
                <div class="box-inner">
                    <div class="box-title">Informations devis</div>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; width:75px; padding-bottom:4px;">Date</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b; padding-bottom:4px;">{{ $devis->date_devis->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:4px;">Validité</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b; padding-bottom:4px;">{{ $devis->date_validite->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8;">Exercice</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b;">{{ $devis->exercice->annee ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="box-inner">
                    <div class="box-title">Destinataire</div>
                    <div class="dest-nom">{{ $devis->entreprise->raison_sociale }}</div>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        @if($devis->entreprise->nif)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; width:55px; padding-bottom:3px;">NIF</td>
                            <td style="font-size:8.5pt; color:#334155; padding-bottom:3px;">{{ $devis->entreprise->nif }}</td>
                        </tr>
                        @endif
                        @if($devis->entreprise->nis)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:3px;">NIS</td>
                            <td style="font-size:8.5pt; color:#334155; padding-bottom:3px;">{{ $devis->entreprise->nis }}</td>
                        </tr>
                        @endif
                        @if($devis->entreprise->num_rc)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:3px;">RC</td>
                            <td style="font-size:8.5pt; color:#334155; padding-bottom:3px;">{{ $devis->entreprise->num_rc }}</td>
                        </tr>
                        @endif
                        @if($devis->entreprise->adresse)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8;">Adresse</td>
                            <td style="font-size:8.5pt; color:#334155;">{{ $devis->entreprise->adresse }}@if($devis->entreprise->ville), {{ $devis->entreprise->ville }}@endif</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── Tableau prestation ─────────────────────────────────── --}}
    <div class="section-wrap">
        <div class="section-label">Détail de la prestation</div>
        <table class="presta" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width:55%">Désignation</th>
                    <th class="r" style="width:22%">Prix HT</th>
                    <th class="r" style="width:23%">TVA (19%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="presta-code">{{ $devis->prestation->code }}</div>
                        <div class="presta-label">{{ $devis->prestation->designation }}</div>
                        @if($devis->prestation->description)
                            <div class="presta-desc">{{ $devis->prestation->description }}</div>
                        @endif
                    </td>
                    <td class="r">{{ number_format((float)$devis->montant_ht, 2, ',', ' ') }} DA</td>
                    <td class="r">{{ number_format((float)$devis->montant_tva, 2, ',', ' ') }} DA</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Totaux ──────────────────────────────────────────────── --}}
    <div class="totaux-wrap">
        <table class="totaux-outer" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:52%">&nbsp;</td>
                <td style="width:48%; vertical-align:top;">
                    <table class="totaux-inner" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>Montant HT</td>
                            <td>{{ number_format((float)$devis->montant_ht, 2, ',', ' ') }} DA</td>
                        </tr>
                        <tr>
                            <td>TVA (19%)</td>
                            <td>{{ number_format((float)$devis->montant_tva, 2, ',', ' ') }} DA</td>
                        </tr>
                        <tr class="ttc">
                            <td>TOTAL TTC</td>
                            <td>{{ number_format((float)$devis->montant_ttc, 2, ',', ' ') }} DA</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Montant en lettres ─────────────────────────────────── --}}
    <div class="totaux-wrap" style="margin-top: -10px; margin-bottom: 20px;">
        <div style="background-color:#f0f4f9; border-left:3px solid #1e3a5f; padding:8px 12px; font-size:8.5pt; color:#1e3a5f; font-style:italic;">
            Arrêté le présent devis à la somme de <strong>{{ $montantEnLettres }}</strong>.
        </div>
    </div>

    {{-- ── Notes ──────────────────────────────────────────────── --}}
    @if($devis->notes)
    <div class="notes-wrap">
        <div class="section-label">Observations</div>
        <div class="notes-inner">{{ $devis->notes }}</div>
    </div>
    @endif

    {{-- ── Signatures ──────────────────────────────────────────── --}}
    <div class="sign-wrap">
        <table class="sign" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="sign-label">Pour {{ $cabinet['nom'] }}</div>
                    <div class="sign-line"></div>
                    <div class="sign-sub">Signature et cachet</div>
                </td>
                <td>
                    <div class="sign-label" style="text-align:right;">{{ $devis->entreprise->raison_sociale }}</div>
                    <div class="sign-line-r"></div>
                    <div class="sign-sub-r">Signature et cachet<br>Mention : « Bon pour accord »</div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
