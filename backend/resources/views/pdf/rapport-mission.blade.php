<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de fin de mission — {{ $mission->reference }}</title>
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

        /* ─────────────────────────────────────────
           FOOTER FIXE
        ───────────────────────────────────────── */
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
        .footer-table td {
            font-size: 7pt;
            color: #8fa3bf;
            vertical-align: middle;
        }
        .footer-table td.right { text-align: right; }
        .footer-table td.center { text-align: center; }
        .footer-accent { color: #c9a84c; font-weight: bold; }

        /* ─────────────────────────────────────────
           EN-TÊTE
        ───────────────────────────────────────── */
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
            color: #0f1f3d;
            font-size: 20pt;
            font-weight: bold;
            text-align: center;
            line-height: 44px;
            margin-bottom: 8px;
        }
        .cabinet-nom { font-size: 15pt; font-weight: bold; color: #ffffff; letter-spacing: 0.5px; }
        .cabinet-sous { font-size: 7.5pt; color: #8fa3bf; margin-top: 2px; }
        .doc-label-line { font-size: 7pt; color: #c9a84c; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 6px; }
        .doc-title-main { font-size: 14pt; font-weight: bold; color: #ffffff; letter-spacing: 0.5px; }
        .doc-ref-line { font-size: 8pt; color: #8fa3bf; margin-top: 5px; }
        .doc-ref-value { color: #c9a84c; font-weight: bold; }

        .header-sub-bar { background: #162a4a; border-top: 1px solid #1e3a5f; padding: 8px 48px; }
        .header-sub-table { width: 100%; border-collapse: collapse; }
        .header-sub-table td { font-size: 7.5pt; color: #8fa3bf; vertical-align: middle; padding-right: 24px; }
        .header-sub-table td span.val { color: #e2e8f0; font-weight: bold; }

        /* ─────────────────────────────────────────
           BODY
        ───────────────────────────────────────── */
        .body-content { padding: 28px 48px 70px; }
        .section { margin-bottom: 24px; }

        .section-title {
            font-size: 8pt;
            font-weight: bold;
            color: #0f1f3d;
            text-transform: uppercase;
            letter-spacing: 2px;
            padding-bottom: 6px;
            margin-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0;
            width: 36px; height: 2px;
            background: #c9a84c;
        }
        .section-count {
            font-weight: normal;
            color: #94a3b8;
            letter-spacing: 0;
            font-size: 7.5pt;
            margin-left: 6px;
        }

        /* ─────────────────────────────────────────
           1. RÉSUMÉ EXÉCUTIF
        ───────────────────────────────────────── */
        .exec-table { width: 100%; border-collapse: collapse; }
        .exec-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
        }
        .exec-table td:first-child { border-left: 3px solid #c9a84c; }
        .exec-kpi-label {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .exec-kpi-value {
            font-size: 16pt;
            font-weight: bold;
            color: #0f1f3d;
            line-height: 1.2;
        }
        .exec-kpi-sub {
            font-size: 8pt;
            color: #64748b;
            margin-top: 4px;
        }
        .exec-kpi-sub-gold { font-size: 8pt; color: #c9a84c; font-weight: bold; margin-top: 2px; }
        .exec-kpi-sub-green { font-size: 8pt; color: #166534; font-weight: bold; margin-top: 2px; }

        /* Barre de progression inline */
        .progress-bar-wrap {
            background: #e2e8f0;
            height: 6px;
            border-radius: 3px;
            margin-top: 8px;
            width: 100%;
        }
        .progress-bar-fill {
            height: 6px;
            border-radius: 3px;
            background: #c9a84c;
        }

        /* ─────────────────────────────────────────
           2. INFORMATIONS
        ───────────────────────────────────────── */
        table.info-grid { width: 100%; border-collapse: collapse; }
        table.info-grid td { padding: 10px 14px; vertical-align: top; width: 50%; border: 1px solid #eef0f4; }
        table.info-grid td:first-child { border-left: 3px solid #c9a84c; }
        table.info-grid tr:nth-child(odd) td { background: #fafbfc; }
        .info-label { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
        .info-value { font-size: 9.5pt; font-weight: bold; color: #0f1f3d; }
        .info-value-mono { font-size: 10.5pt; font-weight: bold; color: #c9a84c; }

        .collab-chip {
            display: inline-block;
            background: #eef4ff;
            color: #1e3a5f;
            border: 1px solid #c8d8f0;
            border-radius: 2px;
            padding: 2px 10px;
            font-size: 8pt;
            margin-right: 5px;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .notes-block {
            background: #fafbfc;
            border-left: 3px solid #c9a84c;
            padding: 10px 14px;
            font-size: 9pt;
            color: #334155;
            margin-top: 10px;
        }
        .notes-label { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }

        /* ─────────────────────────────────────────
           3. CHRONOLOGIE
        ───────────────────────────────────────── */
        .timeline-table { width: 100%; border-collapse: collapse; }
        .timeline-table td { vertical-align: top; width: 25%; padding: 0; }
        .timeline-node {
            text-align: center;
            padding: 0 8px;
        }
        .timeline-dot {
            width: 12px; height: 12px;
            background: #c9a84c;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 6px;
        }
        .timeline-dot-empty {
            width: 12px; height: 12px;
            background: #e2e8f0;
            border: 2px solid #c9a84c;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 6px;
        }
        .timeline-line {
            border-top: 2px dashed #c9a84c;
            margin-top: 5px;
            opacity: 0.4;
        }
        .timeline-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 3px; }
        .timeline-date { font-size: 9pt; font-weight: bold; color: #0f1f3d; }
        .timeline-date-empty { font-size: 9pt; color: #cbd5e1; font-style: italic; }

        /* ─────────────────────────────────────────
           4. STATISTIQUES TÂCHES
        ───────────────────────────────────────── */
        .stats-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .stats-table td { padding: 7px 10px; border-bottom: 1px solid #eef0f4; vertical-align: middle; }
        .stats-table tr:last-child td { border-bottom: none; }
        .stats-table tr.total-row td { border-top: 2px solid #c9a84c; font-weight: bold; background: #fafbfc; }
        .stats-label { color: #334155; }
        .stats-count { text-align: center; font-weight: bold; color: #0f1f3d; width: 40px; }
        .stats-bar-cell { width: 55%; padding-right: 8px; }
        .stats-pct { text-align: right; color: #64748b; font-size: 8.5pt; width: 40px; }

        /* ─────────────────────────────────────────
           5. TÂCHES ET COMMENTAIRES
        ───────────────────────────────────────── */
        .tache-block { border: 1px solid #e2e8f0; margin-bottom: 16px; page-break-inside: avoid; }
        .tache-header { background: #0f1f3d; padding: 10px 16px; }
        .tache-header-table { width: 100%; border-collapse: collapse; }
        .tache-header-table td { vertical-align: middle; }
        .tache-header-table td.right { text-align: right; white-space: nowrap; }
        .tache-num { font-size: 7pt; color: #c9a84c; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 2px; }
        .tache-titre { font-size: 10pt; font-weight: bold; color: #ffffff; }
        .tache-meta-bar { background: #162a4a; padding: 6px 16px; border-bottom: 1px solid #1e3a5f; }
        .tache-meta-table { width: 100%; border-collapse: collapse; }
        .tache-meta-table td { font-size: 7.5pt; color: #8fa3bf; padding-right: 18px; vertical-align: middle; }
        .tache-meta-table td span.mv { color: #d4e4f7; font-weight: bold; }
        .tache-desc { padding: 10px 16px; font-size: 8.5pt; color: #475569; border-bottom: 1px solid #eef0f4; background: #fdfdfd; font-style: italic; }

        .comments-header { padding: 8px 16px; background: #f8fafc; border-bottom: 1px solid #eef0f4; }
        .comments-header-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; font-weight: bold; }
        .comments-list { padding: 0 16px; }
        .comment-item { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .comment-item:last-child { border-bottom: none; }
        .comment-meta-table { width: 100%; border-collapse: collapse; }
        .comment-meta-table td { vertical-align: middle; }
        .comment-meta-table td.right { text-align: right; }
        .comment-author { font-size: 8.5pt; font-weight: bold; color: #0f1f3d; }
        .comment-author-dot { display: inline-block; width: 7px; height: 7px; background: #c9a84c; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
        .comment-date { font-size: 7.5pt; color: #94a3b8; }
        .comment-text { font-size: 9pt; color: #334155; margin-top: 5px; padding-left: 13px; line-height: 1.55; }
        .no-comment { padding: 12px 16px; font-size: 8.5pt; color: #cbd5e1; font-style: italic; }

        /* ─────────────────────────────────────────
           6. FACTURATION
        ───────────────────────────────────────── */
        .facture-block { border: 1px solid #e2e8f0; margin-bottom: 14px; page-break-inside: avoid; }
        .facture-block-header { background: #fafbfc; border-bottom: 1px solid #e2e8f0; padding: 10px 16px; }
        .facture-header-table { width: 100%; border-collapse: collapse; }
        .facture-header-table td { vertical-align: middle; }
        .facture-header-table td.right { text-align: right; }
        .facture-numero { font-size: 10pt; font-weight: bold; color: #0f1f3d; }
        .facture-meta { font-size: 8pt; color: #64748b; margin-top: 2px; }
        .facture-montant { font-size: 10pt; font-weight: bold; color: #0f1f3d; }

        /* Paiements */
        .paiements-header { padding: 7px 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
        .paiements-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; font-weight: bold; }
        .paiements-list { padding: 0 16px; }
        .paiement-row { padding: 7px 0; border-bottom: 1px solid #f8fafc; }
        .paiement-row:last-child { border-bottom: none; }
        .paiement-table { width: 100%; border-collapse: collapse; }
        .paiement-table td { vertical-align: middle; font-size: 8.5pt; }
        .paiement-table td.right { text-align: right; font-weight: bold; color: #166534; }
        .paiement-dot { display: inline-block; width: 6px; height: 6px; background: #86efac; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
        .no-paiement { padding: 8px 16px; font-size: 8.5pt; color: #cbd5e1; font-style: italic; }

        /* Bloc solde global */
        .solde-global { background: #0f1f3d; padding: 16px 20px; margin-top: 4px; }
        .solde-table { width: 100%; border-collapse: collapse; }
        .solde-table td { padding: 5px 8px; font-size: 9.5pt; color: #8fa3bf; vertical-align: middle; }
        .solde-table td.right { text-align: right; font-weight: bold; }
        .solde-table td.label { color: #8fa3bf; font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; }
        .solde-table tr.solde-facture td { color: #e2e8f0; }
        .solde-table tr.solde-encaisse td { color: #86efac; }
        .solde-table tr.solde-reste td { color: #c9a84c; font-size: 11pt; border-top: 1px solid #1e3a5f; padding-top: 10px; margin-top: 4px; }
        .solde-table tr.solde-reste-zero td { color: #86efac; font-size: 11pt; border-top: 1px solid #1e3a5f; padding-top: 10px; }

        /* ─────────────────────────────────────────
           BADGES
        ───────────────────────────────────────── */
        .badge { display: inline-block; padding: 2px 9px; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.5px; border-radius: 2px; }
        .badge-a_faire   { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .badge-en_cours  { background: #dbeafe; color: #1e3a5f; border: 1px solid #93c5fd; }
        .badge-terminee  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-bloquee   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-suspendue { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .badge-annulee   { background: #ffe4e6; color: #881337; border: 1px solid #fda4af; }
        .badge-p1 { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }
        .badge-p2 { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .badge-p3 { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-p4 { background: #fff1f2; color: #be123c; border: 1px solid #fda4af; }
        .badge-en_attente { background: #fefce8; color: #854d0e; border: 1px solid #fde68a; }
        .badge-partiel    { background: #fff7ed; color: #9a3412; border: 1px solid #fdba74; }
        .badge-solde      { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }

        .page-break { page-break-before: always; }
        .text-right { text-align: right; }
        .text-muted { color: #94a3b8; font-style: italic; font-size: 9pt; }
    </style>
</head>
<body>

{{-- ─── FOOTER FIXE ─── --}}
<div class="footer">
    <div class="footer-inner">
        <table class="footer-table" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <span class="footer-accent">{{ $cabinet['nom'] }}</span>
                    @if($cabinet['nif']) &nbsp;·&nbsp; NIF {{ $cabinet['nif'] }} @endif
                    @if($cabinet['nis']) &nbsp;·&nbsp; NIS {{ $cabinet['nis'] }} @endif
                </td>
                <td class="center"><span style="color:#c9a84c;">—</span>&nbsp; Document confidentiel &nbsp;<span style="color:#c9a84c;">—</span></td>
                <td class="right" style="color:#c9a84c; font-weight:bold;">{{ $mission->reference }}</td>
            </tr>
        </table>
    </div>
</div>

@php
    // ── Calculs globaux ──
    $statutLabels      = ['en_cours' => 'En cours', 'terminee' => 'Terminée', 'suspendue' => 'Suspendue', 'annulee' => 'Annulée'];
    $statutTacheLabels = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'bloquee' => 'Bloquée'];
    $prioriteLabels    = [1 => 'Faible', 2 => 'Normale', 3 => 'Haute', 4 => 'Urgente'];
    $prioriteBadges    = [1 => 'badge-p1', 2 => 'badge-p2', 3 => 'badge-p3', 4 => 'badge-p4'];
    $modeLabels        = ['virement' => 'Virement bancaire', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'autre' => 'Autre'];
    $paiementLabels    = ['en_attente' => 'En attente', 'partiel' => 'Partiel', 'solde' => 'Soldé'];

    // Résumé exécutif
    $totalTaches    = $mission->taches->count();
    $tachesTerminees = $mission->taches->where('statut', 'terminee')->count();
    $avancementPct  = $totalTaches > 0 ? round(($tachesTerminees / $totalTaches) * 100) : 0;

    $totalFacture   = (float) $mission->factures->sum('montant_ttc');
    $totalEncaisse  = (float) $mission->factures->sum('montant_paye');
    $resteDu        = $totalFacture - $totalEncaisse;

    $dateDebut = $mission->date_debut ? \Carbon\Carbon::parse($mission->date_debut) : null;
    $dateFin   = $mission->date_fin   ? \Carbon\Carbon::parse($mission->date_fin)   : null;
    $dureeJours = ($dateDebut && $dateFin) ? $dateDebut->diffInDays($dateFin) : null;

    // Chronologie
    $dateCreation          = $mission->created_at->format('d/m/Y');
    $premiereFacture       = $mission->factures->sortBy('date_facture')->first();
    $derniereTacheTerminee = $mission->taches->where('statut', 'terminee')->sortByDesc('updated_at')->first();
    $missionClose          = in_array($mission->statut, ['terminee', 'annulee']);
    $dateCloture           = ($dateFin && $missionClose) ? $dateFin->format('d/m/Y') : null;
    $dateFinPrevue         = ($dateFin && !$missionClose) ? $dateFin->format('d/m/Y') : null;

    // Stats tâches
    $statsStatuts = [
        'a_faire'   => $mission->taches->where('statut', 'a_faire')->count(),
        'en_cours'  => $mission->taches->where('statut', 'en_cours')->count(),
        'terminee'  => $tachesTerminees,
        'bloquee'   => $mission->taches->where('statut', 'bloquee')->count(),
    ];
@endphp

<div class="page">

    {{-- ─── EN-TÊTE ─── --}}
    <div class="header-block">
        <div class="header-gold-bar"></div>
        <div class="header-content">
            <table class="header-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <div class="cabinet-monogram">{{ mb_strtoupper(mb_substr($cabinet['nom'], 0, 1)) }}</div>
                        <div class="cabinet-nom">{{ $cabinet['nom'] }}</div>
                        @if($cabinet['soustitre'])<div class="cabinet-sous">{{ $cabinet['soustitre'] }}</div>@endif
                        @if($cabinet['adresse'])<div class="cabinet-sous">{{ $cabinet['adresse'] }}</div>@endif
                    </td>
                    <td class="right">
                        <div class="doc-label-line">Document officiel</div>
                        <div class="doc-title-main">Rapport de fin de mission</div>
                        <div class="doc-ref-line">Référence &nbsp;<span class="doc-ref-value">{{ $mission->reference }}</span></div>
                        <div class="doc-ref-line">Généré le &nbsp;<span class="doc-ref-value">{{ now()->format('d/m/Y') }}</span></div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="header-sub-bar">
            <table class="header-sub-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>Client &nbsp;<span class="val">{{ $mission->entreprise->raison_sociale }}</span></td>
                    <td>Prestation &nbsp;<span class="val">{{ $mission->prestation->designation }}</span></td>
                    <td>Statut &nbsp;<span class="val">{{ $statutLabels[$mission->statut] ?? $mission->statut }}</span></td>
                    @if(!$portailMode)
                    <td style="text-align:right; color:#c9a84c; font-weight:bold; font-size:9pt;">{{ number_format((float)$mission->prix_ht, 2, ',', ' ') }} DA</td>
                    @endif
                </tr>
            </table>
        </div>
    </div>

    <div class="body-content">

        {{-- ─── 1. RÉSUMÉ EXÉCUTIF ─── --}}
        <div class="section">
            <div class="section-title">Résumé exécutif</div>
            <table class="exec-table" cellspacing="0" cellpadding="0">
                <tr>
                    {{-- Durée --}}
                    <td>
                        <div class="exec-kpi-label">Durée de la mission</div>
                        @if($dureeJours !== null)
                            <div class="exec-kpi-value">{{ $dureeJours }}<span style="font-size:10pt; font-weight:normal; color:#64748b;"> j</span></div>
                            <div class="exec-kpi-sub">
                                {{ $dateDebut->format('d/m/Y') }} → {{ $dateFin->format('d/m/Y') }}
                            </div>
                        @elseif($dateDebut)
                            <div class="exec-kpi-value" style="font-size:12pt;">En cours</div>
                            <div class="exec-kpi-sub">Depuis le {{ $dateDebut->format('d/m/Y') }}</div>
                        @else
                            <div class="exec-kpi-value" style="font-size:12pt; color:#cbd5e1;">—</div>
                        @endif
                    </td>

                    {{-- Avancement --}}
                    <td>
                        <div class="exec-kpi-label">Avancement des tâches</div>
                        <div class="exec-kpi-value">{{ $avancementPct }}<span style="font-size:10pt; font-weight:normal; color:#64748b;">%</span></div>
                        <div class="exec-kpi-sub">{{ $tachesTerminees }} / {{ $totalTaches }} tâche{{ $totalTaches > 1 ? 's' : '' }} terminée{{ $tachesTerminees > 1 ? 's' : '' }}</div>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:{{ $avancementPct }}%;"></div>
                        </div>
                    </td>

                    {{-- Financier --}}
                    <td>
                        <div class="exec-kpi-label">Situation financière</div>
                        @if($totalFacture > 0)
                            <div class="exec-kpi-value" style="font-size:11pt;">{{ number_format($totalFacture, 0, ',', ' ') }} DA</div>
                            <div class="exec-kpi-sub">Facturé (TTC)</div>
                            <div class="exec-kpi-sub-green">+ {{ number_format($totalEncaisse, 0, ',', ' ') }} DA encaissé</div>
                            @if($resteDu > 0)
                            <div class="exec-kpi-sub-gold">► {{ number_format($resteDu, 0, ',', ' ') }} DA restant dû</div>
                            @else
                            <div class="exec-kpi-sub-green">✓ Intégralement soldé</div>
                            @endif
                        @else
                            <div class="exec-kpi-value" style="font-size:12pt; color:#cbd5e1;">—</div>
                            <div class="exec-kpi-sub">Aucune facture émise</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- ─── 2. INFORMATIONS DE LA MISSION ─── --}}
        <div class="section">
            <div class="section-title">Informations de la mission</div>
            <table class="info-grid" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <div class="info-label">Exercice fiscal</div>
                        <div class="info-value">{{ $mission->exercice->annee ?? '—' }}</div>
                    </td>
                    <td>
                        <div class="info-label">Statut</div>
                        <div class="info-value"><span class="badge badge-{{ $mission->statut }}">{{ $statutLabels[$mission->statut] ?? $mission->statut }}</span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="info-label">Date de début</div>
                        <div class="info-value">{{ $dateDebut ? $dateDebut->format('d/m/Y') : '—' }}</div>
                    </td>
                    <td>
                        <div class="info-label">Date de fin</div>
                        <div class="info-value">{{ $dateFin ? $dateFin->format('d/m/Y') : '—' }}</div>
                    </td>
                </tr>
                @if(!$portailMode)
                <tr>
                    <td colspan="2">
                        <div class="info-label">Montant honoraires HT</div>
                        <div class="info-value-mono">{{ number_format((float)$mission->prix_ht, 2, ',', ' ') }} DA</div>
                    </td>
                </tr>
                @endif
            </table>

            @if($mission->collaborateurs->isNotEmpty())
            <div style="margin-top:12px;">
                <div style="font-size:7pt; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">Équipe assignée</div>
                <div>@foreach($mission->collaborateurs as $collab)<span class="collab-chip">{{ $collab->name }}</span>@endforeach</div>
            </div>
            @endif

            @if($mission->notes)
            <div class="notes-block">
                <div class="notes-label">Notes</div>
                {{ $mission->notes }}
            </div>
            @endif
        </div>

        {{-- ─── 3. CHRONOLOGIE ─── --}}
        <div class="section">
            <div class="section-title">Chronologie de la mission</div>
            <table class="timeline-table" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <div class="timeline-node">
                            <div class="timeline-dot"></div>
                            <div class="timeline-label">Création</div>
                            <div class="timeline-date">{{ $dateCreation }}</div>
                        </div>
                    </td>
                    <td>
                        <div class="timeline-node">
                            @if($premiereFacture)
                                <div class="timeline-dot"></div>
                                <div class="timeline-label">1ère facture</div>
                                <div class="timeline-date">{{ \Carbon\Carbon::parse($premiereFacture->date_facture)->format('d/m/Y') }}</div>
                                <div style="font-size:7.5pt; color:#94a3b8; margin-top:2px;">{{ $premiereFacture->numero }}</div>
                            @else
                                <div class="timeline-dot-empty"></div>
                                <div class="timeline-label">1ère facture</div>
                                <div class="timeline-date-empty">Non encore émise</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="timeline-node">
                            @if($derniereTacheTerminee)
                                <div class="timeline-dot"></div>
                                <div class="timeline-label">Dernière tâche terminée</div>
                                <div class="timeline-date">{{ \Carbon\Carbon::parse($derniereTacheTerminee->updated_at)->format('d/m/Y') }}</div>
                                <div style="font-size:7.5pt; color:#94a3b8; margin-top:2px;">{{ mb_strimwidth($derniereTacheTerminee->titre, 0, 25, '…') }}</div>
                            @else
                                <div class="timeline-dot-empty"></div>
                                <div class="timeline-label">Dernière tâche terminée</div>
                                <div class="timeline-date-empty">Aucune encore</div>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="timeline-node">
                            @if($dateCloture)
                                <div class="timeline-dot"></div>
                                <div class="timeline-label">Clôture</div>
                                <div class="timeline-date">{{ $dateCloture }}</div>
                            @elseif($dateFinPrevue)
                                <div class="timeline-dot-empty"></div>
                                <div class="timeline-label">Date de fin prévue</div>
                                <div class="timeline-date" style="color:#8fa3bf;">{{ $dateFinPrevue }}</div>
                            @else
                                <div class="timeline-dot-empty"></div>
                                <div class="timeline-label">Clôture</div>
                                <div class="timeline-date-empty">Non définie</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ─── 4. STATISTIQUES DES TÂCHES ─── --}}
        @if($totalTaches > 0)
        <div class="section">
            <div class="section-title">Statistiques des tâches</div>
            <table class="stats-table" cellspacing="0" cellpadding="0">
                @foreach([['a_faire','À faire'],['en_cours','En cours'],['terminee','Terminées'],['bloquee','Bloquées']] as $row)
                @php
                    $key   = $row[0];
                    $label = $row[1];
                    $count = $statsStatuts[$key];
                    $pct   = $totalTaches > 0 ? round(($count / $totalTaches) * 100) : 0;
                @endphp
                <tr>
                    <td class="stats-label"><span class="badge badge-{{ $key }}" style="margin-right:6px;">{{ $label }}</span></td>
                    <td class="stats-count">{{ $count }}</td>
                    <td class="stats-bar-cell">
                        <div style="background:#e2e8f0; height:8px; border-radius:4px; width:100%;">
                            @if($pct > 0)
                            <div style="background:{{ $key === 'terminee' ? '#86efac' : ($key === 'bloquee' ? '#fca5a5' : ($key === 'en_cours' ? '#93c5fd' : '#e2e8f0')) }}; height:8px; border-radius:4px; width:{{ $pct }}%;"></div>
                            @endif
                        </div>
                    </td>
                    <td class="stats-pct">{{ $pct }}%</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td class="stats-label">Total</td>
                    <td class="stats-count">{{ $totalTaches }}</td>
                    <td class="stats-bar-cell" style="color:#c9a84c; font-size:8.5pt;">Taux de complétion</td>
                    <td class="stats-pct" style="color:#c9a84c;">{{ $avancementPct }}%</td>
                </tr>
            </table>
        </div>
        @endif

        {{-- ─── 5. TÂCHES ET COMMENTAIRES ─── --}}
        <div class="section">
            <div class="section-title">
                Tâches et commentaires
                <span class="section-count">{{ $totalTaches }} tâche{{ $totalTaches > 1 ? 's' : '' }}</span>
            </div>

            @forelse($mission->taches as $index => $tache)
            <div class="tache-block">
                <div class="tache-header">
                    <table class="tache-header-table" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <div class="tache-num">Tâche {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
                                <div class="tache-titre">{{ $tache->titre }}</div>
                            </td>
                            <td class="right">
                                <span class="badge badge-{{ $tache->statut }}">{{ $statutTacheLabels[$tache->statut] ?? $tache->statut }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="tache-meta-bar">
                    <table class="tache-meta-table" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>Assignée à &nbsp;<span class="mv">{{ $tache->assignee->name ?? 'Non assignée' }}</span></td>
                            <td>Échéance &nbsp;<span class="mv">{{ $tache->date_echeance ? \Carbon\Carbon::parse($tache->date_echeance)->format('d/m/Y') : '—' }}</span></td>
                            <td>Priorité &nbsp;<span class="badge {{ $prioriteBadges[$tache->priorite] ?? 'badge-p1' }}">{{ $prioriteLabels[$tache->priorite] ?? '—' }}</span></td>
                        </tr>
                    </table>
                </div>
                @if($tache->description)
                <div class="tache-desc">{{ $tache->description }}</div>
                @endif
                @if($tache->commentaires->isNotEmpty())
                <div class="comments-header">
                    <span class="comments-header-label">Commentaires &nbsp;·&nbsp; {{ $tache->commentaires->count() }}</span>
                </div>
                <div class="comments-list">
                    @foreach($tache->commentaires as $commentaire)
                    <div class="comment-item">
                        <table class="comment-meta-table" cellspacing="0" cellpadding="0">
                            <tr>
                                <td><span class="comment-author-dot"></span><span class="comment-author">{{ $commentaire->user->name ?? 'Inconnu' }}</span></td>
                                <td class="right"><span class="comment-date">{{ \Carbon\Carbon::parse($commentaire->created_at)->format('d/m/Y') }} à {{ \Carbon\Carbon::parse($commentaire->created_at)->format('H:i') }}</span></td>
                            </tr>
                        </table>
                        <div class="comment-text">{{ $commentaire->contenu }}</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="no-comment">Aucun commentaire enregistré pour cette tâche.</div>
                @endif
            </div>
            @empty
            <p class="text-muted">Aucune tâche enregistrée pour cette mission.</p>
            @endforelse
        </div>

        {{-- ─── 6. FACTURATION ─── --}}
        @if(!$portailMode && $mission->factures->isNotEmpty())
        <div class="section">
            <div class="section-title">
                Facturation
                <span class="section-count">{{ $mission->factures->count() }} facture{{ $mission->factures->count() > 1 ? 's' : '' }}</span>
            </div>

            @foreach($mission->factures as $facture)
            <div class="facture-block">
                <div class="facture-block-header">
                    <table class="facture-header-table" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <div class="facture-numero">{{ $facture->numero }}</div>
                                <div class="facture-meta">
                                    Émise le {{ $facture->date_facture ? \Carbon\Carbon::parse($facture->date_facture)->format('d/m/Y') : '—' }}
                                    @if($facture->date_echeance)
                                    &nbsp;·&nbsp; Échéance {{ \Carbon\Carbon::parse($facture->date_echeance)->format('d/m/Y') }}
                                    @endif
                                </div>
                            </td>
                            <td class="right">
                                <div class="facture-montant">{{ number_format((float)$facture->montant_ttc, 2, ',', ' ') }} DA TTC</div>
                                <div style="font-size:8pt; color:#94a3b8; margin-top:2px; text-align:right;">
                                    HT : {{ number_format((float)$facture->montant_ht, 2, ',', ' ') }} DA
                                    &nbsp;&nbsp;
                                    <span class="badge badge-{{ $facture->statut_paiement }}">{{ $paiementLabels[$facture->statut_paiement] ?? $facture->statut_paiement }}</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                @if($facture->paiements->isNotEmpty())
                <div class="paiements-header">
                    <span class="paiements-label">Paiements reçus &nbsp;·&nbsp; {{ $facture->paiements->count() }}</span>
                </div>
                <div class="paiements-list">
                    @foreach($facture->paiements->sortBy('date_paiement') as $paiement)
                    <div class="paiement-row">
                        <table class="paiement-table" cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    <span class="paiement-dot"></span>
                                    <strong>{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</strong>
                                    &nbsp;—&nbsp;
                                    {{ $modeLabels[$paiement->mode_paiement] ?? $paiement->mode_paiement }}
                                    @if($paiement->reference)
                                    &nbsp;<span style="color:#94a3b8; font-size:8pt;">(réf: {{ $paiement->reference }})</span>
                                    @endif
                                </td>
                                <td class="right">{{ number_format((float)$paiement->montant, 2, ',', ' ') }} DA</td>
                            </tr>
                        </table>
                    </div>
                    @endforeach
                    @php $resteFacture = (float)$facture->montant_ttc - $facture->paiements->sum('montant'); @endphp
                    @if($resteFacture > 0.01)
                    <div style="padding:6px 0; font-size:8pt; color:#c9a84c; text-align:right; border-top:1px dashed #eef0f4; margin-top:4px;">
                        Reste à encaisser sur cette facture : <strong>{{ number_format($resteFacture, 2, ',', ' ') }} DA</strong>
                    </div>
                    @endif
                </div>
                @else
                <div class="no-paiement">Aucun paiement enregistré pour cette facture.</div>
                @endif
            </div>
            @endforeach

            {{-- Bloc solde global --}}
            <div class="solde-global">
                <table class="solde-table" cellspacing="0" cellpadding="0">
                    <tr class="solde-facture">
                        <td class="label">Total facturé (TTC)</td>
                        <td class="right">{{ number_format($totalFacture, 2, ',', ' ') }} DA</td>
                    </tr>
                    <tr class="solde-encaisse">
                        <td class="label">Total encaissé</td>
                        <td class="right">{{ number_format($totalEncaisse, 2, ',', ' ') }} DA</td>
                    </tr>
                    @if($resteDu > 0.01)
                    <tr class="solde-reste">
                        <td class="label">► Reste dû</td>
                        <td class="right">{{ number_format($resteDu, 2, ',', ' ') }} DA</td>
                    </tr>
                    @else
                    <tr class="solde-reste-zero">
                        <td class="label">✓ Intégralement soldé</td>
                        <td class="right">0,00 DA</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        @endif

    </div>{{-- /body-content --}}

</div>
</body>
</html>
