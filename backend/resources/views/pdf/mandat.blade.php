<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mandat {{ $mission->mandat_numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #222; line-height: 1.7; }

        .footer {
            position: fixed; bottom: 0; left: 0; right: 0; height: 28px;
            border-top: 1px solid #c8d4e3; text-align: center;
            font-size: 7.5pt; color: #999; padding-top: 6px;
        }
        .page { padding: 40px 50px 60px 50px; }

        /* Logo damier Ledge (identique facture/devis, teintes lisibles sur blanc) */
        .logo-grid { border-collapse: separate; }
        .logo-grid td {
            width: 22px;
            height: 22px;
            border-radius: 6px;
        }
        .logo-light { background-color: #cbd5e1; }
        .logo-dark  { background-color: #51607a; }
        .cabinet-nom { font-size: 20pt; font-weight: bold; color: #1e3a5f; }
        .cabinet-sous { font-size: 8.5pt; color: #64748b; font-style: italic; margin-bottom: 4px; }
        .header-sep { border-bottom: 2px solid #1e3a5f; margin-bottom: 30px; padding-bottom: 8px; }

        .ref-block { font-size: 9.5pt; margin-bottom: 30px; line-height: 1.8; }
        .ref-block .ref { font-weight: bold; }
        .ref-block .date-alger { text-align: right; font-size: 9.5pt; }

        .titre {
            font-size: 16pt; font-weight: bold; text-align: center;
            text-transform: uppercase; letter-spacing: 1px;
            margin: 50px 0 50px 0;
        }

        .corps { font-size: 10.5pt; line-height: 1.9; margin-bottom: 30px; }

        .incompatibilite { font-size: 10.5pt; line-height: 1.9; margin-bottom: 60px; }

        .sign-block { text-align: right; margin-top: 20px; }
        .sign-nom { font-size: 10.5pt; font-weight: bold; text-decoration: underline; }
        .sign-titre { font-size: 10pt; }
    </style>
</head>
<body>

<div class="footer">
    {{ $cabinet['nom'] }}
    @if($cabinet['nif']) &nbsp;·&nbsp; NIF&nbsp;: {{ $cabinet['nif'] }} @endif
    @if($cabinet['nis']) &nbsp;·&nbsp; NIS&nbsp;: {{ $cabinet['nis'] }} @endif
</div>

<div class="page">

    {{-- En-tête cabinet --}}
    <div class="header-sep">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align: middle; padding-right: 12px; width: 62px;">
                    <table class="logo-grid" cellpadding="0" cellspacing="3">
                        <tr><td class="logo-light"></td><td class="logo-dark"></td></tr>
                        <tr><td class="logo-dark"></td><td class="logo-light"></td></tr>
                    </table>
                </td>
                <td style="vertical-align: middle;">
                    <div class="cabinet-nom">{{ $cabinet['nom'] }}</div>
                    <div class="cabinet-sous">{{ $cabinet['soustitre'] ?? 'Cabinet de comptabilité et commissariat aux comptes' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Référence + date --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
        <tr>
            <td class="ref-block">
                Réf #: <span class="ref">{{ $mission->mandat_numero }}</span><br>
                Date d'impression: <span class="ref">{{ $dateContrat }}</span>
            </td>
            <td class="ref-block" style="text-align: right;">
                {{ $ville }}, le <span class="ref">{{ $dateContrat }}</span>
            </td>
        </tr>
    </table>

    {{-- Titre --}}
    <div class="titre">Acceptation de Mandat</div>

    {{-- Corps --}}
    <div class="corps">
        Je soussigné, <strong>{{ $cabinet['nom'] }}</strong>, Commissaire aux comptes
        @if($cabinet['adresse']) demeurant à {{ $cabinet['adresse'] }},@endif
        @if($cabinet['agrement'])
        inscrit au Tableau de l'Ordre des Commissaires Aux Comptes sous le n°<strong>{{ $cabinet['agrement'] }}</strong>,
        @endif
        déclare l'acceptation du Mandat de <strong>{{ $mission->prestation->designation }}</strong> / Commissariat aux Comptes,
        au profit de <strong>{{ $mission->entreprise->raison_sociale }}</strong>
        @if($mission->entreprise->adresse)
        dont le siège social est sis à {{ $mission->entreprise->adresse }}@if($mission->entreprise->ville), {{ $mission->entreprise->ville }}@endif
        @endif
        au titre des exercices du <strong>{{ $mission->exercice->annee }}</strong> au <strong>{{ $mission->exercice->annee }}</strong>.
    </div>

    <div class="incompatibilite">
        Le commissaire aux comptes déclare n'être frappé d'aucune incompatibilité par la législation et la réglementation en vigueur.
    </div>

    {{-- Signature --}}
    <div class="sign-block">
        <div class="sign-nom">{{ $cabinet['nom'] }}</div>
        <div class="sign-titre">Commissaire Aux Comptes</div>
    </div>

</div>
</body>
</html>
