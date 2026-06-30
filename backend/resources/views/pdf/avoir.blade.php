<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Avoir {{ $avoir->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            line-height: 1.4;
        }

        /* ── Footer fixe centré ────────────────────── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 34px;
            border-top: 1px solid #e2e8f0;
            padding-top: 7px;
            text-align: center;
        }
        .footer .f-cabinet { font-size: 7.5pt; font-weight: bold; color: #475569; }
        .footer .f-legal { font-size: 7pt; color: #94a3b8; margin-top: 1px; }

        .page { padding: 0 0 46px 0; }

        /* ── En-tête gris ardoise ──────────────────── */
        .header-band {
            background-color: #334155; /* fallback si gradient non rendu */
            background-image: linear-gradient(125deg, #475569 0%, #334155 45%, #1e293b 100%);
            padding: 18px 30px 20px 30px;
        }
        .header-inner td { vertical-align: top; }

        /* Logo damier 2x2 */
        .logo-grid { border-collapse: separate; }
        .logo-grid td { width: 15px; height: 15px; border-radius: 4px; }
        .logo-light { background-color: #cbd5e1; }
        .logo-dark  { background-color: #51607a; }

        .brand-name { font-size: 20pt; font-weight: bold; color: #ffffff; letter-spacing: 0.3px; line-height: 1.1; }
        .brand-sub { font-size: 8.5pt; color: #aab4c2; margin-top: 1px; }

        .doc-title { font-size: 22pt; font-weight: bold; color: #ffffff; letter-spacing: 3px; }
        .doc-num { font-size: 9.5pt; color: #aab4c2; margin-top: 3px; }

        /* Pilule "Note de crédit" (liseré) */
        .pill {
            display: inline-block;
            padding: 4px 12px;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-radius: 11px;
            border: 1px solid #94a3b8;
            color: #cbd5e1;
        }

        /* ── Cartes (sur la cellule <td> : même ligne => même hauteur) ── */
        td.card {
            background-color: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 14px 16px;
            vertical-align: top;
        }
        .card-title { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; color: #94a3b8; margin-bottom: 11px; }
        .kv { width: 100%; border-collapse: collapse; }
        .kv td { padding-bottom: 6px; }
        .kv td.k { font-size: 8.5pt; color: #94a3b8; }
        .kv td.v { font-size: 9pt; font-weight: bold; color: #1e293b; text-align: right; }
        .dest-nom { font-size: 11.5pt; font-weight: bold; color: #1e293b; margin-bottom: 9px; }

        /* ── Tableau détail ────────────────────────── */
        .section-wrap { padding: 0 30px; }
        .section-label { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; color: #94a3b8; margin-bottom: 8px; }
        table.presta { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.presta thead th {
            background-color: #334155;
            color: #ffffff;
            padding: 9px 14px;
            font-size: 8pt;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        table.presta thead th.r { text-align: right; }
        table.presta thead th:first-child { border-top-left-radius: 6px; }
        table.presta thead th:last-child { border-top-right-radius: 6px; }
        table.presta tbody td { padding: 12px 14px; border-bottom: 1px solid #e8ecf1; vertical-align: top; }
        table.presta tbody td.r { text-align: right; white-space: nowrap; font-weight: bold; color: #1e293b; }
        .presta-label { font-size: 9.5pt; font-weight: bold; color: #1e293b; }
        .presta-sub { font-size: 8pt; color: #94a3b8; margin-top: 2px; }

        /* ── Totaux ────────────────────────────────── */
        .totaux-wrap { padding: 0 30px; margin-top: 16px; }
        table.totaux-inner { width: 100%; border-collapse: collapse; }
        table.totaux-inner td { padding: 6px 4px; font-size: 9.5pt; color: #475569; }
        table.totaux-inner td.v { text-align: right; font-weight: bold; color: #1e293b; white-space: nowrap; }
        .ttc-box { background-color: #334155; border-radius: 8px; padding: 11px 16px; margin-top: 4px; }
        .ttc-box td { vertical-align: middle; }
        .ttc-label { font-size: 9.5pt; color: #cbd5e1; letter-spacing: 0.5px; }
        .ttc-val { font-size: 13pt; font-weight: bold; color: #ffffff; text-align: right; white-space: nowrap; }

        /* ── Mention + motif ───────────────────────── */
        .mention {
            background-color: #eef2f6;
            border-left: 3px solid #334155;
            border-radius: 0 4px 4px 0;
            padding: 9px 14px;
            font-size: 8.5pt;
            color: #475569;
        }
        .motif-box {
            background-color: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 6px;
            padding: 9px 14px;
            font-size: 9pt;
            color: #334155;
        }
    </style>
</head>
<body>

<div class="footer">
    <div class="f-cabinet">{{ $cabinet['nom'] }}</div>
    <div class="f-legal">
        @if($cabinet['adresse']){{ $cabinet['adresse'] }}@endif
        @if($cabinet['nif']) &nbsp;·&nbsp; NIF : {{ $cabinet['nif'] }}@endif
        @if($cabinet['nis']) &nbsp;·&nbsp; NIS : {{ $cabinet['nis'] }}@endif
        @if($cabinet['agrement']) &nbsp;·&nbsp; N° d'agrément : {{ $cabinet['agrement'] }}@endif
    </div>
</div>

<div class="page">

    {{-- ── En-tête ──────────────────────────────────────────── --}}
    <div class="header-band">
    <table class="header-inner" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align: middle; padding-right: 11px;">
                            <table class="logo-grid" cellpadding="0" cellspacing="3">
                                <tr><td class="logo-light"></td><td class="logo-dark"></td></tr>
                                <tr><td class="logo-dark"></td><td class="logo-light"></td></tr>
                            </table>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="brand-name">Ledge</div>
                            <div class="brand-sub">{{ $cabinet['nom'] }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="text-align: right;">
                <div class="doc-title">AVOIR</div>
                <div class="doc-num">N° {{ $avoir->numero }}</div>
                <div style="margin-top: 9px;">
                    <span class="pill">Note de crédit</span>
                </div>
            </td>
        </tr>
    </table>
    </div>

    {{-- ── Cartes infos avoir + destinataire ─────────────────── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 26px 30px 0 30px;">
        <tr>
            <td class="card" style="width: 48%;">
                <div class="card-title">Informations avoir</div>
                <table class="kv">
                    <tr><td class="k">Date</td><td class="v">{{ $avoir->date_avoir->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">Facture d'origine</td><td class="v">{{ $avoir->factureOrigine->numero }}</td></tr>
                    @if($avoir->factureOrigine->mission)
                    <tr><td class="k">Mission</td><td class="v">{{ $avoir->factureOrigine->mission->reference }}</td></tr>
                    @endif
                    <tr><td class="k" style="padding-bottom:0;">Exercice</td><td class="v" style="padding-bottom:0;">{{ $avoir->exercice->annee ?? '—' }}</td></tr>
                </table>
            </td>
            <td style="width: 4%;"></td>
            <td class="card" style="width: 48%;">
                <div class="card-title">Destinataire</div>
                <div class="dest-nom">{{ $avoir->factureOrigine->entreprise->raison_sociale }}</div>
                <table class="kv">
                    @if($avoir->factureOrigine->entreprise->nif)
                    <tr><td class="k">NIF</td><td class="v">{{ $avoir->factureOrigine->entreprise->nif }}</td></tr>
                    @endif
                    @if($avoir->factureOrigine->entreprise->nis)
                    <tr><td class="k">NIS</td><td class="v">{{ $avoir->factureOrigine->entreprise->nis }}</td></tr>
                    @endif
                    @if($avoir->factureOrigine->entreprise->num_rc)
                    <tr><td class="k">RC</td><td class="v">{{ $avoir->factureOrigine->entreprise->num_rc }}</td></tr>
                    @endif
                    @if($avoir->factureOrigine->entreprise->adresse)
                    <tr><td class="k" style="padding-bottom:0;">Adresse</td><td class="v" style="padding-bottom:0;">{{ $avoir->factureOrigine->entreprise->adresse }}@if($avoir->factureOrigine->entreprise->ville), {{ $avoir->factureOrigine->entreprise->ville }}@endif</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Détail de l'avoir ────────────────────────────────── --}}
    <div class="section-wrap" style="margin-top: 26px;">
        <div class="section-label">Détail de l'avoir</div>
        <table class="presta" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width:54%">Désignation</th>
                    <th class="r" style="width:23%">Montant HT</th>
                    <th class="r" style="width:23%">TVA ({{ number_format((float)$avoir->taux_tva_snapshot, 0) }}%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="presta-label">Avoir sur facture {{ $avoir->factureOrigine->numero }}</div>
                        <div class="presta-sub">{{ $avoir->motif }}</div>
                    </td>
                    <td class="r">{{ number_format((float)$avoir->montant_ht, 2, ',', ' ') }} DA</td>
                    <td class="r">{{ number_format((float)$avoir->montant_tva, 2, ',', ' ') }} DA</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ── Totaux ───────────────────────────────────────────── --}}
    <div class="totaux-wrap">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:54%">&nbsp;</td>
                <td style="width:46%; vertical-align: top;">
                    <table class="totaux-inner" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>Montant HT</td>
                            <td class="v">{{ number_format((float)$avoir->montant_ht, 2, ',', ' ') }} DA</td>
                        </tr>
                        <tr>
                            <td>TVA ({{ number_format((float)$avoir->taux_tva_snapshot, 0) }}%)</td>
                            <td class="v">{{ number_format((float)$avoir->montant_tva, 2, ',', ' ') }} DA</td>
                        </tr>
                    </table>
                    <div class="ttc-box">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="ttc-label">TOTAL AVOIR TTC</td>
                                <td class="ttc-val">{{ number_format((float)$avoir->montant_ttc, 2, ',', ' ') }} DA</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Montant en lettres ───────────────────────────────── --}}
    <div class="section-wrap" style="margin-top: 22px;">
        <div class="mention">
            Arrêté le présent avoir à la somme de <strong>{{ $montantEnLettres }}</strong>.
        </div>
    </div>

    {{-- ── Motif de l'avoir ─────────────────────────────────── --}}
    <div class="section-wrap" style="margin-top: 16px;">
        <div class="section-label">Motif de l'avoir</div>
        <div class="motif-box">{{ $avoir->motif }}</div>
    </div>

</div>
</body>
</html>
