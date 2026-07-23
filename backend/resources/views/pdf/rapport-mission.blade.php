<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de fin de mission — {{ $mission->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* Espace en haut de CHAQUE page (page 1 comprise, facon papier a
           en-tete) : sans lui, le contenu demarrait au bord physique du
           papier des la page 2. NE PAS tenter de l'annuler en page 1 par une
           marge negative sur .header-band : dompdf propage mal cette marge
           et supprime l'espace des pages suivantes. */
        @page {
            margin: 26px 0 0 0;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            line-height: 1.4;
        }

        /* ── Footer fixe (identique facture/devis) ── */
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

        /* ── En-tête bleu nuit (identique facture/devis) ── */
        .header-band {
            background-color: #152138; /* fallback si gradient non rendu */
            background-image: linear-gradient(125deg, #28406b 0%, #182842 45%, #0d1525 100%);
            padding: 18px 30px 20px 30px;
        }
        .header-inner td { vertical-align: top; }

        /* Logo damier 2x2 */
        .logo-grid { border-collapse: separate; }
        .logo-grid td {
            width: 15px;
            height: 15px;
            border-radius: 4px;
        }
        .logo-light { background-color: #cbd5e1; }
        .logo-dark  { background-color: #51607a; }

        .brand-name {
            font-size: 20pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }
        .brand-sub {
            font-size: 8.5pt;
            color: #93a4bd;
            margin-top: 1px;
        }

        .doc-title {
            font-size: 22pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 3px;
        }
        .doc-sub { font-size: 9pt; color: #93a4bd; margin-top: 1px; }
        .doc-num { font-size: 9.5pt; color: #93a4bd; margin-top: 3px; }

        /* Pilule statut (liseré, identique facture) */
        .pill {
            display: inline-block;
            padding: 4px 12px;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-radius: 11px;
            border: 1px solid #93a4bd;
            color: #93a4bd;
        }
        .pill-en_cours  { border-color: #60a5fa; color: #93c5fd; }
        .pill-terminee  { border-color: #4ade80; color: #86efac; }
        .pill-suspendue { border-color: #d4af37; color: #e2c14e; }
        .pill-annulee   { border-color: #f87171; color: #fca5a5; }

        /* ── Cartes (identique facture) ── */
        td.card {
            background-color: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 14px 16px;
            vertical-align: top;
        }
        .card-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            margin-bottom: 11px;
        }
        .kv { width: 100%; border-collapse: collapse; }
        .kv td { padding-bottom: 6px; }
        .kv td.k { font-size: 8.5pt; color: #94a3b8; }
        .kv td.v { font-size: 9pt; font-weight: bold; color: #1e293b; text-align: right; }
        .dest-nom { font-size: 11.5pt; font-weight: bold; color: #1e293b; margin-bottom: 9px; }

        /* ── Sections ── */
        .section-wrap { padding: 0 30px; }
        .section-label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .section-count { font-weight: normal; letter-spacing: 0.3px; text-transform: none; }

        /* ── KPI résumé exécutif (3 cartes) ── */
        .kpi-value { font-size: 15pt; font-weight: bold; color: #152138; line-height: 1.2; }
        .kpi-unit { font-size: 9.5pt; font-weight: normal; color: #64748b; }
        .kpi-sub { font-size: 8pt; color: #64748b; margin-top: 4px; }
        .kpi-sub-green { font-size: 8pt; color: #166534; font-weight: bold; margin-top: 2px; }
        .kpi-sub-amber { font-size: 8pt; color: #a16207; font-weight: bold; margin-top: 2px; }
        .kpi-empty { font-size: 12pt; font-weight: bold; color: #cbd5e1; }

        .progress-wrap { background: #e8ecf1; height: 6px; border-radius: 3px; margin-top: 8px; width: 100%; }
        .progress-fill { background: #152138; height: 6px; border-radius: 3px; }

        /* ── Chronologie ── */
        .timeline-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .timeline-table td { vertical-align: top; width: 25%; padding: 0 8px; text-align: center; }
        .timeline-dot {
            width: 11px; height: 11px;
            background: #d4af37;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 6px;
        }
        .timeline-dot-empty {
            width: 11px; height: 11px;
            background: #ffffff;
            border: 2px solid #d4af37;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 6px;
        }
        .timeline-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 3px; }
        .timeline-date { font-size: 9pt; font-weight: bold; color: #1e293b; }
        .timeline-date-empty { font-size: 9pt; color: #cbd5e1; font-style: italic; }
        .timeline-sub { font-size: 7.5pt; color: #94a3b8; margin-top: 2px; }

        /* ── Tableaux (thead navy, identique facture) ── */
        table.presta { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.presta thead th {
            background-color: #152138;
            color: #ffffff;
            padding: 9px 14px;
            font-size: 8pt;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        table.presta thead th.r { text-align: right; }
        table.presta thead th.c { text-align: center; }
        table.presta thead th:first-child { border-top-left-radius: 6px; }
        table.presta thead th:last-child { border-top-right-radius: 6px; }
        table.presta tbody td { padding: 10px 14px; border-bottom: 1px solid #e8ecf1; vertical-align: middle; }
        table.presta tbody td.r { text-align: right; white-space: nowrap; font-weight: bold; color: #1e293b; }
        table.presta tbody td.c { text-align: center; font-weight: bold; color: #1e293b; }
        table.presta tbody tr.total td { border-bottom: none; border-top: 2px solid #152138; font-weight: bold; background: #f8fafc; }

        .stat-bar-wrap { background: #e8ecf1; height: 7px; border-radius: 3px; width: 100%; }
        .stat-bar { height: 7px; border-radius: 3px; }

        /* ── Badges (pastel lisibles sur blanc) ── */
        .badge { display: inline-block; padding: 2px 9px; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.5px; border-radius: 4px; }
        .badge-a_faire   { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .badge-en_cours  { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
        .badge-terminee  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-bloquee   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-suspendue { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .badge-annulee   { background: #ffe4e6; color: #881337; border: 1px solid #fda4af; }
        .badge-p1 { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        .badge-p2 { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-p3 { background: #fffbeb; color: #a16207; border: 1px solid #fde68a; }
        .badge-p4 { background: #fff1f2; color: #be123c; border: 1px solid #fda4af; }
        .badge-en_attente { background: #fefce8; color: #854d0e; border: 1px solid #fde68a; }
        .badge-partiel    { background: #fff7ed; color: #9a3412; border: 1px solid #fdba74; }
        .badge-solde      { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }

        /* ── Blocs tâche / facture ── */
        .item-block { border: 1px solid #e8ecf1; border-radius: 8px; margin-bottom: 12px; page-break-inside: avoid; }
        .item-head { background: #f8fafc; border-bottom: 1px solid #e8ecf1; padding: 10px 14px; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .item-head-table { width: 100%; border-collapse: collapse; }
        .item-head-table td { vertical-align: middle; }
        .item-head-table td.right { text-align: right; white-space: nowrap; }
        .item-eyebrow { font-size: 7pt; color: #94a3b8; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 2px; }
        .item-title { font-size: 10pt; font-weight: bold; color: #1e293b; }
        .item-meta { font-size: 8pt; color: #64748b; margin-top: 2px; }

        .tache-meta-bar { padding: 6px 14px; border-bottom: 1px solid #e8ecf1; background: #fdfdfe; }
        .tache-meta-table { width: 100%; border-collapse: collapse; }
        .tache-meta-table td { font-size: 8pt; color: #94a3b8; padding-right: 16px; vertical-align: middle; }
        .tache-meta-table td span.mv { color: #1e293b; font-weight: bold; }
        .tache-desc { padding: 9px 14px; font-size: 8.5pt; color: #475569; border-bottom: 1px solid #e8ecf1; font-style: italic; }

        .sub-list-label { padding: 7px 14px; background: #f8fafc; border-bottom: 1px solid #e8ecf1; font-size: 7pt; text-transform: uppercase; letter-spacing: 1.2px; color: #94a3b8; font-weight: bold; }
        .sub-list { padding: 0 14px; }
        .sub-item { padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
        .sub-item:last-child { border-bottom: none; }
        .sub-item-table { width: 100%; border-collapse: collapse; }
        .sub-item-table td { vertical-align: middle; }
        .sub-item-table td.right { text-align: right; white-space: nowrap; width: 130px; }
        .comment-author { font-size: 8.5pt; font-weight: bold; color: #1e293b; }
        .comment-dot { display: inline-block; width: 7px; height: 7px; background: #d4af37; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
        .comment-date { font-size: 7.5pt; color: #94a3b8; }
        .comment-text { font-size: 9pt; color: #334155; margin-top: 4px; padding-left: 13px; line-height: 1.55; }
        .empty-line { padding: 10px 14px; font-size: 8.5pt; color: #cbd5e1; font-style: italic; }

        .paiement-date { font-size: 8.5pt; font-weight: bold; color: #1e293b; }
        .paiement-mode { font-size: 8.5pt; color: #64748b; }
        .paiement-montant { font-size: 8.5pt; font-weight: bold; color: #166534; }
        .paiement-dot { display: inline-block; width: 6px; height: 6px; background: #4ade80; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
        .reste-line { padding: 6px 0 9px; font-size: 8pt; color: #a16207; text-align: right; border-top: 1px dashed #e8ecf1; }

        /* ── Bloc solde global (style TTC facture) ── */
        .ttc-box { background-color: #152138; border-radius: 8px; padding: 12px 16px; margin-top: 6px; page-break-inside: avoid; }
        .ttc-box table { width: 100%; border-collapse: collapse; }
        .ttc-box td { vertical-align: middle; padding: 3px 0; }
        .solde-label { font-size: 8pt; color: #93a4bd; text-transform: uppercase; letter-spacing: 1px; }
        .solde-val { font-size: 9.5pt; font-weight: bold; color: #e2e8f0; text-align: right; white-space: nowrap; }
        .solde-val-green { font-size: 9.5pt; font-weight: bold; color: #86efac; text-align: right; white-space: nowrap; }
        .solde-final td { border-top: 1px solid #28406b; padding-top: 8px; }
        .solde-final .solde-val-gold { font-size: 12pt; font-weight: bold; color: #e2c14e; text-align: right; white-space: nowrap; }
        .solde-final .solde-val-ok { font-size: 12pt; font-weight: bold; color: #86efac; text-align: right; white-space: nowrap; }

        .text-muted-line { color: #94a3b8; font-style: italic; font-size: 9pt; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>

{{-- ─── FOOTER FIXE (identique facture/devis) ─── --}}
<div class="footer">
    <div class="f-cabinet">{{ $cabinet['nom'] }}</div>
    <div class="f-legal">
        @if($cabinet['adresse']){{ $cabinet['adresse'] }}@endif
        @if($cabinet['nif']) &nbsp;·&nbsp; NIF : {{ $cabinet['nif'] }}@endif
        @if($cabinet['nis']) &nbsp;·&nbsp; NIS : {{ $cabinet['nis'] }}@endif
        @if($cabinet['agrement']) &nbsp;·&nbsp; N° d'agrément : {{ $cabinet['agrement'] }}@endif
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
    $totalTaches     = $mission->taches->count();
    $tachesTerminees = $mission->taches->where('statut', 'terminee')->count();
    $avancementPct   = $totalTaches > 0 ? round(($tachesTerminees / $totalTaches) * 100) : 0;

    $totalFacture  = (float) $mission->factures->sum('montant_ttc');
    $totalEncaisse = (float) $mission->factures->sum('montant_paye');
    $resteDu       = $totalFacture - $totalEncaisse;

    $dateDebut  = $mission->date_debut ? \Carbon\Carbon::parse($mission->date_debut) : null;
    $dateFin    = $mission->date_fin   ? \Carbon\Carbon::parse($mission->date_fin)   : null;
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
        'a_faire'  => $mission->taches->where('statut', 'a_faire')->count(),
        'en_cours' => $mission->taches->where('statut', 'en_cours')->count(),
        'terminee' => $tachesTerminees,
        'bloquee'  => $mission->taches->where('statut', 'bloquee')->count(),
    ];
    $statsBarColors = ['a_faire' => '#cbd5e1', 'en_cours' => '#60a5fa', 'terminee' => '#4ade80', 'bloquee' => '#f87171'];
@endphp

<div class="page">

    {{-- ── En-tête (identique facture/devis) ── --}}
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
                <div class="doc-title">RAPPORT</div>
                <div class="doc-sub">de fin de mission</div>
                <div class="doc-num">Mission {{ $mission->reference }} &nbsp;·&nbsp; généré le {{ now()->format('d/m/Y') }}</div>
                <div style="margin-top: 9px;">
                    <span class="pill pill-{{ $mission->statut }}">{{ $statutLabels[$mission->statut] ?? $mission->statut }}</span>
                </div>
            </td>
        </tr>
    </table>
    </div>

    {{-- ── Cartes mission + client (patron facture) ── --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 26px 30px 0 30px;" class="avoid-break">
        <tr>
            <td class="card" style="width: 48%;">
                <div class="card-title">Informations mission</div>
                <table class="kv">
                    <tr><td class="k">Prestation</td><td class="v">{{ $mission->prestation->designation }}</td></tr>
                    <tr><td class="k">Exercice</td><td class="v">{{ $mission->exercice->annee ?? '—' }}</td></tr>
                    <tr><td class="k">Début</td><td class="v">{{ $dateDebut ? $dateDebut->format('d/m/Y') : '—' }}</td></tr>
                    @if(!$portailMode)
                    <tr><td class="k">Fin</td><td class="v">{{ $dateFin ? $dateFin->format('d/m/Y') : '—' }}</td></tr>
                    <tr><td class="k" style="padding-bottom:0;">Honoraires HT</td><td class="v" style="padding-bottom:0;">{{ number_format((float)$mission->prix_ht, 2, ',', ' ') }} DA</td></tr>
                    @else
                    <tr><td class="k" style="padding-bottom:0;">Fin</td><td class="v" style="padding-bottom:0;">{{ $dateFin ? $dateFin->format('d/m/Y') : '—' }}</td></tr>
                    @endif
                </table>
            </td>
            <td style="width: 4%;"></td>
            <td class="card" style="width: 48%;">
                <div class="card-title">Client</div>
                <div class="dest-nom">{{ $mission->entreprise->raison_sociale }}</div>
                <table class="kv">
                    @if($mission->entreprise->nif)
                    <tr><td class="k">NIF</td><td class="v">{{ $mission->entreprise->nif }}</td></tr>
                    @endif
                    @if($mission->entreprise->nis)
                    <tr><td class="k">NIS</td><td class="v">{{ $mission->entreprise->nis }}</td></tr>
                    @endif
                    @if($mission->entreprise->num_rc)
                    <tr><td class="k">RC</td><td class="v">{{ $mission->entreprise->num_rc }}</td></tr>
                    @endif
                    @if($mission->entreprise->adresse)
                    <tr><td class="k" style="padding-bottom:0;">Adresse</td><td class="v" style="padding-bottom:0;">{{ $mission->entreprise->adresse }}@if($mission->entreprise->ville), {{ $mission->entreprise->ville }}@endif</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- ── 1. Résumé exécutif ── --}}
    <div class="section-wrap avoid-break" style="margin-top: 24px;">
        <div class="section-label">Résumé exécutif</div>
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="card" style="width: 32%;">
                    <div class="card-title">Durée de la mission</div>
                    @if($dureeJours !== null)
                        <div class="kpi-value">{{ $dureeJours }} <span class="kpi-unit">jours</span></div>
                        <div class="kpi-sub">Du {{ $dateDebut->format('d/m/Y') }} au {{ $dateFin->format('d/m/Y') }}</div>
                    @elseif($dateDebut)
                        <div class="kpi-value" style="font-size:12pt;">En cours</div>
                        <div class="kpi-sub">Depuis le {{ $dateDebut->format('d/m/Y') }}</div>
                    @else
                        <div class="kpi-empty">—</div>
                    @endif
                </td>
                <td style="width: 2%;"></td>
                <td class="card" style="width: 32%;">
                    <div class="card-title">Avancement des tâches</div>
                    <div class="kpi-value">{{ $avancementPct }} <span class="kpi-unit">%</span></div>
                    <div class="kpi-sub">{{ $tachesTerminees }} / {{ $totalTaches }} tâche{{ $totalTaches > 1 ? 's' : '' }} terminée{{ $tachesTerminees > 1 ? 's' : '' }}</div>
                    <div class="progress-wrap">
                        <div class="progress-fill" style="width: {{ $avancementPct }}%;"></div>
                    </div>
                </td>
                <td style="width: 2%;"></td>
                <td class="card" style="width: 32%;">
                    <div class="card-title">Situation financière</div>
                    @if($totalFacture > 0)
                        <div class="kpi-value" style="font-size:11.5pt;">{{ number_format($totalFacture, 0, ',', ' ') }} <span class="kpi-unit">DA TTC</span></div>
                        <div class="kpi-sub">Total facturé</div>
                        <div class="kpi-sub-green">{{ number_format($totalEncaisse, 0, ',', ' ') }} DA encaissés</div>
                        @if($resteDu > 0.01)
                        <div class="kpi-sub-amber">{{ number_format($resteDu, 0, ',', ' ') }} DA restant dus</div>
                        @else
                        <div class="kpi-sub-green">Intégralement soldé</div>
                        @endif
                    @else
                        <div class="kpi-empty">—</div>
                        <div class="kpi-sub">Aucune facture émise</div>
                    @endif
                </td>
            </tr>
        </table>

        @if($mission->collaborateurs->isNotEmpty() || $mission->notes)
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
            <tr>
                <td class="card">
                    @if($mission->collaborateurs->isNotEmpty())
                    <div class="card-title" style="margin-bottom: 6px;">Équipe assignée</div>
                    <div style="font-size: 9pt; font-weight: bold; color: #1e293b;">
                        {{ $mission->collaborateurs->pluck('name')->implode(' · ') }}
                    </div>
                    @endif
                    @if($mission->notes)
                    <div class="card-title" style="margin: {{ $mission->collaborateurs->isNotEmpty() ? '10px' : '0' }} 0 6px;">Notes</div>
                    <div style="font-size: 8.5pt; color: #475569;">{{ $mission->notes }}</div>
                    @endif
                </td>
            </tr>
        </table>
        @endif
    </div>

    {{-- ── 2. Chronologie ── --}}
    <div class="section-wrap avoid-break" style="margin-top: 24px;">
        <div class="section-label">Chronologie de la mission</div>
        <table class="timeline-table" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="timeline-dot"></div>
                    <div class="timeline-label">Création</div>
                    <div class="timeline-date">{{ $dateCreation }}</div>
                </td>
                <td>
                    @if($premiereFacture)
                        <div class="timeline-dot"></div>
                        <div class="timeline-label">1ère facture</div>
                        <div class="timeline-date">{{ \Carbon\Carbon::parse($premiereFacture->date_facture)->format('d/m/Y') }}</div>
                        <div class="timeline-sub">{{ $premiereFacture->numero }}</div>
                    @else
                        <div class="timeline-dot-empty"></div>
                        <div class="timeline-label">1ère facture</div>
                        <div class="timeline-date-empty">Non encore émise</div>
                    @endif
                </td>
                <td>
                    @if($derniereTacheTerminee)
                        <div class="timeline-dot"></div>
                        <div class="timeline-label">Dernière tâche terminée</div>
                        <div class="timeline-date">{{ \Carbon\Carbon::parse($derniereTacheTerminee->updated_at)->format('d/m/Y') }}</div>
                        <div class="timeline-sub">{{ mb_strimwidth($derniereTacheTerminee->titre, 0, 25, '…') }}</div>
                    @else
                        <div class="timeline-dot-empty"></div>
                        <div class="timeline-label">Dernière tâche terminée</div>
                        <div class="timeline-date-empty">Aucune encore</div>
                    @endif
                </td>
                <td>
                    @if($dateCloture)
                        <div class="timeline-dot"></div>
                        <div class="timeline-label">Clôture</div>
                        <div class="timeline-date">{{ $dateCloture }}</div>
                    @elseif($dateFinPrevue)
                        <div class="timeline-dot-empty"></div>
                        <div class="timeline-label">Fin prévue</div>
                        <div class="timeline-date" style="color:#64748b;">{{ $dateFinPrevue }}</div>
                    @else
                        <div class="timeline-dot-empty"></div>
                        <div class="timeline-label">Clôture</div>
                        <div class="timeline-date-empty">Non définie</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── 3. Statistiques des tâches ── --}}
    @if($totalTaches > 0)
    <div class="section-wrap avoid-break" style="margin-top: 24px;">
        <div class="section-label">Statistiques des tâches</div>
        <table class="presta" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 22%;">Statut</th>
                    <th class="c" style="width: 12%;">Nombre</th>
                    <th style="width: 50%;">Répartition</th>
                    <th class="r" style="width: 16%;">Part</th>
                </tr>
            </thead>
            <tbody>
                @foreach([['a_faire','À faire'],['en_cours','En cours'],['terminee','Terminées'],['bloquee','Bloquées']] as $row)
                @php
                    $key   = $row[0];
                    $count = $statsStatuts[$key];
                    $pct   = $totalTaches > 0 ? round(($count / $totalTaches) * 100) : 0;
                @endphp
                <tr>
                    <td><span class="badge badge-{{ $key }}">{{ $row[1] }}</span></td>
                    <td class="c">{{ $count }}</td>
                    <td>
                        <div class="stat-bar-wrap">
                            @if($pct > 0)
                            <div class="stat-bar" style="background: {{ $statsBarColors[$key] }}; width: {{ $pct }}%;"></div>
                            @endif
                        </div>
                    </td>
                    <td class="r">{{ $pct }} %</td>
                </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="c">{{ $totalTaches }}</td>
                    <td style="font-size: 8pt; color: #64748b;">Taux de complétion</td>
                    <td class="r">{{ $avancementPct }} %</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    {{-- ── 4. Tâches et commentaires — demarre toujours en haut d'une nouvelle page,
         avec un padding en plus de la marge @page pour respirer ── --}}
    <div class="section-wrap" style="page-break-before: always; padding-top: 16px;">
        <div class="section-label">
            Tâches et commentaires
            <span class="section-count">· {{ $totalTaches }} tâche{{ $totalTaches > 1 ? 's' : '' }}</span>
        </div>

        @forelse($mission->taches as $index => $tache)
        <div class="item-block">
            <div class="item-head">
                <table class="item-head-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <div class="item-eyebrow">Tâche {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="item-title">{{ $tache->titre }}</div>
                        </td>
                        <td class="right">
                            <span class="badge badge-{{ $tache->statut }}">{{ $statutTacheLabels[$tache->statut] ?? $tache->statut }}</span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="tache-meta-bar">
                <table class="tache-meta-table" cellpadding="0" cellspacing="0">
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
            <div class="sub-list-label">Commentaires &nbsp;·&nbsp; {{ $tache->commentaires->count() }}</div>
            <div class="sub-list">
                @foreach($tache->commentaires as $commentaire)
                <div class="sub-item">
                    <table class="sub-item-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td><span class="comment-dot"></span><span class="comment-author">{{ $commentaire->user->name ?? 'Inconnu' }}</span></td>
                            <td class="right"><span class="comment-date">{{ \Carbon\Carbon::parse($commentaire->created_at)->format('d/m/Y') }} à {{ \Carbon\Carbon::parse($commentaire->created_at)->format('H:i') }}</span></td>
                        </tr>
                    </table>
                    <div class="comment-text">{{ $commentaire->contenu }}</div>
                </div>
                @endforeach
            </div>
            @else
            <div class="empty-line">Aucun commentaire enregistré pour cette tâche.</div>
            @endif
        </div>
        @empty
        <p class="text-muted-line">Aucune tâche enregistrée pour cette mission.</p>
        @endforelse
    </div>

    {{-- ── 5. Facturation ── --}}
    @if(!$portailMode && $mission->factures->isNotEmpty())
    <div class="section-wrap" style="margin-top: 24px;">
        <div class="section-label">
            Facturation
            <span class="section-count">· {{ $mission->factures->count() }} facture{{ $mission->factures->count() > 1 ? 's' : '' }}</span>
        </div>

        @foreach($mission->factures as $facture)
        <div class="item-block">
            <div class="item-head">
                <table class="item-head-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <div class="item-title">{{ $facture->numero }}</div>
                            <div class="item-meta">
                                Émise le {{ $facture->date_facture ? \Carbon\Carbon::parse($facture->date_facture)->format('d/m/Y') : '—' }}
                                @if($facture->date_echeance)
                                &nbsp;·&nbsp; Échéance {{ \Carbon\Carbon::parse($facture->date_echeance)->format('d/m/Y') }}
                                @endif
                            </div>
                        </td>
                        <td class="right">
                            <div class="item-title">{{ number_format((float)$facture->montant_ttc, 2, ',', ' ') }} DA TTC</div>
                            <div class="item-meta">
                                HT : {{ number_format((float)$facture->montant_ht, 2, ',', ' ') }} DA
                                &nbsp;&nbsp;
                                <span class="badge badge-{{ $facture->statut_paiement }}">{{ $paiementLabels[$facture->statut_paiement] ?? $facture->statut_paiement }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            @if($facture->paiements->isNotEmpty())
            <div class="sub-list-label">Paiements reçus &nbsp;·&nbsp; {{ $facture->paiements->count() }}</div>
            <div class="sub-list">
                @foreach($facture->paiements->sortBy('date_paiement') as $paiement)
                <div class="sub-item">
                    <table class="sub-item-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>
                                <span class="paiement-dot"></span>
                                <span class="paiement-date">{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</span>
                                <span class="paiement-mode">
                                    &nbsp;—&nbsp; {{ $modeLabels[$paiement->mode_paiement] ?? $paiement->mode_paiement }}
                                    @if($paiement->reference)
                                    (réf : {{ $paiement->reference }})
                                    @endif
                                </span>
                            </td>
                            <td class="right"><span class="paiement-montant">{{ number_format((float)$paiement->montant, 2, ',', ' ') }} DA</span></td>
                        </tr>
                    </table>
                </div>
                @endforeach
                @php $resteFacture = (float)$facture->montant_ttc - $facture->paiements->sum('montant'); @endphp
                @if($resteFacture > 0.01)
                <div class="reste-line">
                    Reste à encaisser sur cette facture : <strong>{{ number_format($resteFacture, 2, ',', ' ') }} DA</strong>
                </div>
                @endif
            </div>
            @else
            <div class="empty-line">Aucun paiement enregistré pour cette facture.</div>
            @endif
        </div>
        @endforeach

        {{-- Bloc solde global (style total TTC facture) --}}
        <div class="ttc-box">
            <table cellpadding="0" cellspacing="0">
                <tr>
                    <td class="solde-label">Total facturé (TTC)</td>
                    <td class="solde-val">{{ number_format($totalFacture, 2, ',', ' ') }} DA</td>
                </tr>
                <tr>
                    <td class="solde-label">Total encaissé</td>
                    <td class="solde-val-green">{{ number_format($totalEncaisse, 2, ',', ' ') }} DA</td>
                </tr>
                @if($resteDu > 0.01)
                <tr class="solde-final">
                    <td class="solde-label">Reste dû</td>
                    <td class="solde-val-gold">{{ number_format($resteDu, 2, ',', ' ') }} DA</td>
                </tr>
                @else
                <tr class="solde-final">
                    <td class="solde-label">Intégralement soldé</td>
                    <td class="solde-val-ok">0,00 DA</td>
                </tr>
                @endif
            </table>
        </div>
    </div>
    @endif

</div>
</body>
</html>
