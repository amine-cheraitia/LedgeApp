<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Avoir {{ $avoir->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #222;
            line-height: 1.4;
        }

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

        .page { padding: 0 0 40px 0; }

        .header-band {
            background-color: #7c3aed;
            padding: 14px 22px 16px 22px;
            margin-bottom: 0;
        }
        .header-band td { vertical-align: middle; }

        .ledge-logo-box {
            display: inline-block;
            background-color: #a78bfa;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            width: 18px;
            height: 18px;
            text-align: center;
            line-height: 18px;
            border-radius: 3px;
        }
        .ledge-brand {
            font-size: 7.5pt;
            color: #ddd6fe;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            vertical-align: middle;
            margin-left: 4px;
        }
        .header-brand-row { margin-bottom: 8px; }
        .header-band .cabinet-nom {
            font-size: 16pt;
            font-weight: bold;
            color: #ffffff;
        }
        .header-band .avoir-label {
            text-align: right;
            vertical-align: bottom;
        }
        .avoir-badge {
            display: inline-block;
            background: #ede9fe;
            color: #5b21b6;
            padding: 4px 11px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-radius: 2px;
        }

        .subheader {
            background-color: #f5f3ff;
            padding: 8px 22px;
            border-bottom: 2px solid #7c3aed;
            margin-bottom: 22px;
        }
        .subheader .doc-title {
            font-size: 13pt;
            font-weight: bold;
            color: #5b21b6;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .subheader .doc-numero { font-size: 9pt; color: #6b7280; }

        .box-inner {
            border: 1px solid #c8d4e3;
            border-top: 3px solid #7c3aed;
            padding: 10px 12px;
        }
        .box-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #5b21b6;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .dest-nom {
            font-size: 11pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 7px;
        }

        .section-wrap { padding: 0 22px; margin-bottom: 18px; }
        .section-label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #5b21b6;
            margin-bottom: 6px;
        }

        table.presta {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table.presta thead tr { background-color: #7c3aed; color: #fff; }
        table.presta thead th {
            padding: 7px 10px;
            font-size: 8pt;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
        }
        table.presta thead th.r { text-align: right; }
        table.presta tbody tr { background-color: #faf5ff; }
        table.presta tbody td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.presta tbody td.r { text-align: right; white-space: nowrap; }
        .presta-label { font-size: 9.5pt; font-weight: bold; color: #1e293b; }

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
            background-color: #7c3aed;
            color: #fff;
            font-size: 10.5pt;
            font-weight: bold;
            padding: 8px 10px;
            border-bottom: none;
        }

        .motif-wrap { padding: 0 22px; margin-bottom: 20px; }
        .motif-inner {
            background-color: #faf5ff;
            border-left: 3px solid #7c3aed;
            padding: 9px 12px;
            font-size: 9pt;
            color: #334155;
        }

        .sign-wrap { padding: 0 22px; margin-top: 30px; }
        table.sign { width: 100%; border-collapse: collapse; }
        table.sign td { vertical-align: top; width: 50%; }
        table.sign td:last-child { text-align: right; }
        .sign-label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #5b21b6;
            letter-spacing: 0.4px;
            margin-bottom: 50px;
        }
        .sign-line { border-top: 1px solid #334155; width: 75%; margin-top: 52px; }
        .sign-line-r { border-top: 1px solid #334155; width: 75%; margin-top: 52px; margin-left: auto; }
        .sign-sub { font-size: 8pt; color: #64748b; margin-top: 5px; }
        .sign-sub-r { font-size: 8pt; color: #64748b; margin-top: 5px; text-align: right; }
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

    {{-- En-tête violet --}}
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
            <td class="avoir-label">
                <span class="avoir-badge">Note de crédit</span>
            </td>
        </tr>
    </table>

    {{-- Titre + numéro --}}
    <table class="subheader" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="doc-title">Avoir / Note de crédit</div>
                <div class="doc-numero">N° {{ $avoir->numero }} — Facture d'origine : {{ $avoir->factureOrigine->numero }}</div>
            </td>
        </tr>
    </table>

    {{-- Infos avoir + Destinataire --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 0 22px; margin-bottom: 20px;">
        <tr>
            <td style="width: 46%; vertical-align: top; padding-right: 14px;">
                <div class="box-inner">
                    <div class="box-title">Informations avoir</div>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; width:100px; padding-bottom:4px;">Date</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b; padding-bottom:4px;">{{ $avoir->date_avoir->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:4px;">Facture origine</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b; padding-bottom:4px;">{{ $avoir->factureOrigine->numero }}</td>
                        </tr>
                        @if($avoir->factureOrigine->mission)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:4px;">Mission</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b; padding-bottom:4px;">{{ $avoir->factureOrigine->mission->reference }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8;">Exercice</td>
                            <td style="font-size:9pt; font-weight:600; color:#1e293b;">{{ $avoir->exercice->annee ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 48%; vertical-align: top;">
                <div class="box-inner">
                    <div class="box-title">Destinataire</div>
                    <div class="dest-nom">{{ $avoir->factureOrigine->entreprise->raison_sociale }}</div>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        @if($avoir->factureOrigine->entreprise->nif)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; width:55px; padding-bottom:3px;">NIF</td>
                            <td style="font-size:8.5pt; color:#334155; padding-bottom:3px;">{{ $avoir->factureOrigine->entreprise->nif }}</td>
                        </tr>
                        @endif
                        @if($avoir->factureOrigine->entreprise->nis)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:3px;">NIS</td>
                            <td style="font-size:8.5pt; color:#334155; padding-bottom:3px;">{{ $avoir->factureOrigine->entreprise->nis }}</td>
                        </tr>
                        @endif
                        @if($avoir->factureOrigine->entreprise->num_rc)
                        <tr>
                            <td style="font-size:8pt; color:#94a3b8; padding-bottom:3px;">RC</td>
                            <td style="font-size:8.5pt; color:#334155; padding-bottom:3px;">{{ $avoir->factureOrigine->entreprise->num_rc }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Détail --}}
    <div class="section-wrap">
        <div class="section-label">Détail de l'avoir</div>
        <table class="presta" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width:55%">Désignation</th>
                    <th class="r" style="width:22%">Montant HT</th>
                    <th class="r" style="width:23%">TVA ({{ number_format((float)$avoir->taux_tva_snapshot, 0) }}%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="presta-label">Avoir — {{ $avoir->factureOrigine->numero }}</div>
                        <div style="font-size:8pt; color:#64748b; margin-top:2px;">{{ $avoir->motif }}</div>
                    </td>
                    <td class="r">{{ number_format((float)$avoir->montant_ht, 2, ',', ' ') }} DA</td>
                    <td class="r">{{ number_format((float)$avoir->montant_tva, 2, ',', ' ') }} DA</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Totaux --}}
    <div class="totaux-wrap">
        <table class="totaux-outer" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:52%">&nbsp;</td>
                <td style="width:48%; vertical-align:top;">
                    <table class="totaux-inner" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>Montant HT</td>
                            <td>{{ number_format((float)$avoir->montant_ht, 2, ',', ' ') }} DA</td>
                        </tr>
                        <tr>
                            <td>TVA ({{ number_format((float)$avoir->taux_tva_snapshot, 0) }}%)</td>
                            <td>{{ number_format((float)$avoir->montant_tva, 2, ',', ' ') }} DA</td>
                        </tr>
                        <tr class="ttc">
                            <td>TOTAL AVOIR TTC</td>
                            <td>{{ number_format((float)$avoir->montant_ttc, 2, ',', ' ') }} DA</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Montant en lettres --}}
    <div class="totaux-wrap" style="margin-top: -10px; margin-bottom: 20px;">
        <div style="background-color:#f5f3ff; border-left:3px solid #7c3aed; padding:8px 12px; font-size:8.5pt; color:#5b21b6; font-style:italic;">
            Arrêté le présent avoir à la somme de <strong>{{ $montantEnLettres }}</strong>.
        </div>
    </div>

    {{-- Motif --}}
    <div class="motif-wrap">
        <div class="section-label">Motif de l'avoir</div>
        <div class="motif-inner">{{ $avoir->motif }}</div>
    </div>

    {{-- Signatures --}}
    <div class="sign-wrap">
        <table class="sign" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="sign-label">Pour {{ $cabinet['nom'] }}</div>
                    <div class="sign-line"></div>
                    <div class="sign-sub">Signature et cachet</div>
                </td>
                <td>
                    <div class="sign-label" style="text-align:right;">{{ $avoir->factureOrigine->entreprise->raison_sociale }}</div>
                    <div class="sign-line-r"></div>
                    <div class="sign-sub-r">Signature et cachet</div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
