<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de clôture — Exercice {{ $exercice->annee }}</title>
    <style>
        @page {
            margin-top: 0;
            margin-bottom: 50px;
            margin-left: 0;
            margin-right: 0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #1a1a2e;
            line-height: 1.6;
            background: #fff;
        }

        /* ─── FOOTER FIXE ─── */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 36px;
            background: #0f1f3d;
            padding: 0 48px;
        }
        .footer-inner {
            border-top: 2px solid #c9a84c;
            height: 36px;
            padding-top: 8px;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { font-size: 7pt; color: #8fa3bf; vertical-align: middle; }
        .footer-table td.right { text-align: right; }
        .footer-table td.center { text-align: center; }
        .footer-accent { color: #c9a84c; font-weight: bold; }

        /* ─── EN-TÊTE ─── */
        .header-block { background: #0f1f3d; }
        .header-gold-bar { height: 4px; background: #c9a84c; }
        .header-content { padding: 28px 48px 24px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: bottom; }
        .header-table td.right { text-align: right; }

        .cabinet-monogram {
            display: inline-block;
            width: 44px; height: 44px;
            background: #c9a84c;
            border-radius: 4px;
            text-align: center;
            line-height: 44px;
            font-size: 18pt;
            font-weight: 900;
            color: #0f1f3d;
            margin-right: 12px;
            vertical-align: middle;
        }
        .cabinet-name {
            font-size: 16pt;
            font-weight: 700;
            color: #ffffff;
            vertical-align: middle;
        }
        .cabinet-subtitle {
            font-size: 8pt;
            color: #8fa3bf;
            display: block;
            margin-top: 2px;
        }
        .doc-label {
            font-size: 8pt;
            color: #c9a84c;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .doc-ref {
            font-size: 18pt;
            font-weight: 900;
            color: #ffffff;
        }
        .doc-date {
            font-size: 8pt;
            color: #8fa3bf;
            margin-top: 2px;
        }

        /* ─── CORPS ─── */
        .body-content { padding: 32px 48px; }

        /* ─── SECTION TITRE ─── */
        .section-title {
            font-size: 10pt;
            font-weight: 700;
            color: #0f1f3d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #c9a84c;
            padding-bottom: 4px;
            margin-bottom: 12px;
            margin-top: 24px;
        }
        .section-title:first-child { margin-top: 0; }

        /* ─── BLOC INFO ─── */
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .info-grid td { padding: 4px 8px 4px 0; vertical-align: top; }
        .info-grid td.label { font-size: 8pt; color: #6b7280; width: 38%; }
        .info-grid td.value { font-size: 9pt; font-weight: 600; color: #1a1a2e; }

        /* ─── KPI CARDS ─── */
        .kpi-row { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .kpi-row td { width: 25%; padding: 4px; vertical-align: top; }
        .kpi-card {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-top: 3px solid #c9a84c;
            border-radius: 4px;
            padding: 12px 14px;
            text-align: center;
        }
        .kpi-label { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 13pt; font-weight: 800; color: #0f1f3d; margin-top: 4px; }
        .kpi-sub { font-size: 7pt; color: #9ca3af; margin-top: 2px; }

        /* ─── TABLEAU ─── */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .data-table th {
            background: #0f1f3d;
            color: #ffffff;
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            text-align: left;
        }
        .data-table th.right { text-align: right; }
        .data-table td { padding: 6px 10px; font-size: 8.5pt; border-bottom: 1px solid #f0f0f0; }
        .data-table td.right { text-align: right; }
        .data-table td.center { text-align: center; }
        .data-table tr:nth-child(even) td { background: #fafafa; }
        .data-table tfoot td {
            background: #0f1f3d;
            color: #ffffff;
            font-weight: 700;
            font-size: 8.5pt;
            padding: 7px 10px;
        }
        .data-table tfoot td.right { text-align: right; }
        .data-table tfoot td.gold { color: #c9a84c; }

        /* ─── BADGE STATUT ─── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: 600;
        }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }

        /* ─── VIDE ─── */
        .empty-block {
            text-align: center;
            color: #9ca3af;
            font-size: 8.5pt;
            padding: 16px;
            background: #fafafa;
            border: 1px dashed #e5e7eb;
            border-radius: 4px;
        }

        .gold { color: #c9a84c; }
        .muted { color: #6b7280; }
        .bold { font-weight: 700; }
        .mt-8 { margin-top: 8px; }
    </style>
</head>
<body>

{{-- FOOTER FIXE --}}
<div class="footer">
    <div class="footer-inner">
        <table class="footer-table">
            <tr>
                <td>{{ $cabinet['nom'] }}</td>
                <td class="center">
                    @if($cabinet['nif']) <span class="footer-accent">NIF</span> {{ $cabinet['nif'] }} @endif
                    @if($cabinet['nis']) &nbsp;|&nbsp; <span class="footer-accent">NIS</span> {{ $cabinet['nis'] }} @endif
                </td>
                <td class="right">Rapport de clôture — Exercice {{ $exercice->annee }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- EN-TÊTE --}}
<div class="header-block">
    <div class="header-gold-bar"></div>
    <div class="header-content">
        <table class="header-table">
            <tr>
                <td>
                    <span class="cabinet-monogram">{{ strtoupper(substr($cabinet['nom'] ?? 'C', 0, 1)) }}</span>
                    <span class="cabinet-name">{{ $cabinet['nom'] }}
                        @if($cabinet['soustitre'])
                            <span class="cabinet-subtitle">{{ $cabinet['soustitre'] }}</span>
                        @endif
                    </span>
                </td>
                <td class="right">
                    <div class="doc-label">Rapport de clôture</div>
                    <div class="doc-ref">Exercice {{ $exercice->annee }}</div>
                    <div class="doc-date">Généré le {{ now()->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="body-content">

    {{-- SECTION 1 — INFORMATIONS EXERCICE --}}
    <div class="section-title">Informations de l'exercice</div>
    <table class="info-grid">
        <tr>
            <td class="label">Année fiscale</td>
            <td class="value">{{ $exercice->annee }}</td>
            <td class="label">Statut</td>
            <td class="value">{{ ucfirst($exercice->statut) }}</td>
        </tr>
        <tr>
            <td class="label">Date d'ouverture</td>
            <td class="value">{{ $exercice->date_ouverture ? $exercice->date_ouverture->format('d/m/Y') : '—' }}</td>
            <td class="label">Date de clôture</td>
            <td class="value">{{ $exercice->date_cloture ? $exercice->date_cloture->format('d/m/Y') : '—' }}</td>
        </tr>
        @if($cabinet['adresse'])
        <tr>
            <td class="label">Cabinet</td>
            <td class="value" colspan="3">{{ $cabinet['nom'] }} — {{ $cabinet['adresse'] }}</td>
        </tr>
        @endif
    </table>

    {{-- SECTION 2 — SYNTHÈSE ANNUELLE --}}
    <div class="section-title">Synthèse financière annuelle</div>
    <table class="kpi-row">
        <tr>
            <td>
                <div class="kpi-card">
                    <div class="kpi-label">Chiffre d'affaires HT</div>
                    <div class="kpi-value">{{ number_format($totaux['ca'], 0, ',', ' ') }}</div>
                    <div class="kpi-sub">Dinars Algériens</div>
                </div>
            </td>
            <td>
                <div class="kpi-card">
                    <div class="kpi-label">TVA collectée</div>
                    <div class="kpi-value">{{ number_format($totaux['tva'], 0, ',', ' ') }}</div>
                    <div class="kpi-sub">Dinars Algériens</div>
                </div>
            </td>
            <td>
                <div class="kpi-card">
                    <div class="kpi-label">Timbre collecté</div>
                    <div class="kpi-value">{{ number_format($totaux['timbre'], 0, ',', ' ') }}</div>
                    <div class="kpi-sub">Dinars Algériens</div>
                </div>
            </td>
            <td>
                <div class="kpi-card">
                    <div class="kpi-label">Factures émises</div>
                    <div class="kpi-value">{{ $totaux['nb'] }}</div>
                    <div class="kpi-sub">Total exercice</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- SECTION 3 — CA MENSUEL --}}
    <div class="section-title">Chiffre d'affaires mensuel</div>
    @php
        $nomsMois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $moisAvecDonnees = array_filter($mois, fn($m) => $m['nb'] > 0);
    @endphp

    @if(count($moisAvecDonnees) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Mois</th>
                <th class="right">Factures</th>
                <th class="right">CA HT (DA)</th>
                <th class="right">TVA (DA)</th>
                <th class="right">Timbre (DA)</th>
                <th class="right">Total TTC (DA)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mois as $num => $data)
                @if($data['nb'] > 0)
                <tr>
                    <td class="bold">{{ $nomsMois[$num] }}</td>
                    <td class="right">{{ $data['nb'] }}</td>
                    <td class="right">{{ number_format($data['ca'], 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format($data['tva'], 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format($data['timbre'], 2, ',', ' ') }}</td>
                    <td class="right bold">{{ number_format($data['ca'] + $data['tva'] + $data['timbre'], 2, ',', ' ') }}</td>
                </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="gold">TOTAL</td>
                <td class="right gold">{{ $totaux['nb'] }}</td>
                <td class="right gold">{{ number_format($totaux['ca'], 2, ',', ' ') }}</td>
                <td class="right gold">{{ number_format($totaux['tva'], 2, ',', ' ') }}</td>
                <td class="right gold">{{ number_format($totaux['timbre'], 2, ',', ' ') }}</td>
                <td class="right gold">{{ number_format($totaux['ca'] + $totaux['tva'] + $totaux['timbre'], 2, ',', ' ') }}</td>
            </tr>
        </tfoot>
    </table>
    @else
        <div class="empty-block">Aucune facture émise sur cet exercice.</div>
    @endif

    {{-- SECTION 4 — IMPAYÉS --}}
    <div class="section-title">Impayés en fin d'exercice</div>
    @if($impayes->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Numéro</th>
                <th>Client</th>
                <th class="center">Date</th>
                <th class="center">Échéance</th>
                <th class="right">Montant TTC (DA)</th>
                <th class="right">Payé (DA)</th>
                <th class="right">Restant (DA)</th>
                <th class="center">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($impayes as $facture)
            @php
                $restant = max(0, (float)$facture->montant_ttc - (float)$facture->montant_paye);
                $depasse = $facture->date_echeance && $facture->date_echeance->isPast();
            @endphp
            <tr>
                <td class="bold">{{ $facture->numero }}</td>
                <td>{{ $facture->entreprise?->raison_sociale ?? '—' }}</td>
                <td class="center">{{ $facture->date_facture ? $facture->date_facture->format('d/m/Y') : '—' }}</td>
                <td class="center">{{ $facture->date_echeance ? $facture->date_echeance->format('d/m/Y') : '—' }}</td>
                <td class="right">{{ number_format((float)$facture->montant_ttc, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format((float)$facture->montant_paye, 2, ',', ' ') }}</td>
                <td class="right bold">{{ number_format($restant, 2, ',', ' ') }}</td>
                <td class="center">
                    @if($depasse)
                        <span class="badge badge-danger">En retard</span>
                    @else
                        <span class="badge badge-warning">{{ ucfirst(str_replace('_', ' ', $facture->statut_paiement)) }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="gold">TOTAL IMPAYÉS</td>
                <td class="right gold">{{ number_format($impayes->sum(fn($f) => max(0, (float)$f->montant_ttc - (float)$f->montant_paye)), 2, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @else
        <div class="empty-block">Aucun impayé en fin d'exercice — recouvrement complet.</div>
    @endif

</div>
</body>
</html>
