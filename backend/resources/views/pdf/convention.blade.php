<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Convention {{ $mission->convention_numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; line-height: 1.5; }

        .footer {
            position: fixed; bottom: 0; left: 0; right: 0; height: 28px;
            border-top: 1px solid #c8d4e3; text-align: center;
            font-size: 7.5pt; color: #999; padding-top: 6px;
        }
        .page { padding: 0 0 40px 0; }

        /* ── Page de garde ── */
        .cover { text-align: center; padding: 30px 40px; }
        .cover-institutions { font-size: 9pt; font-weight: bold; margin-bottom: 30px; line-height: 1.8; }
        .cover-institutions p { text-decoration: underline; }
        /* Logo damier Ledge (identique facture/devis, teintes lisibles sur blanc).
           Centrage horizontal : conteneur a largeur fixe (2 cellules de 26px
           + 3 espacements de 3px = 61px) + margin auto — la seule technique
           fiable sous dompdf (align/margin sur la table directement ne le sont pas). */
        .cover-logo-wrap { width: 61px; margin: 0 auto 4px; }
        .cover-logo-grid { border-collapse: separate; }
        .cover-logo-grid td {
            width: 26px;
            height: 26px;
            border-radius: 7px;
        }
        .logo-light { background-color: #cbd5e1; }
        .logo-dark  { background-color: #51607a; }
        .cover-cabinet-nom { font-size: 22pt; font-weight: bold; color: #1e3a5f; margin-bottom: 4px; }
        .cover-cabinet-sous { font-size: 9pt; color: #64748b; font-style: italic; margin-bottom: 30px; }
        .cover-ref { text-align: left; font-size: 10pt; font-weight: bold; margin-bottom: 40px; line-height: 1.8; }
        .cover-title {
            font-size: 14pt; font-weight: bold; text-align: center;
            margin-bottom: 6px; text-transform: uppercase;
        }
        .cover-subtitle {
            font-size: 12pt; font-weight: bold; text-align: center;
            margin-bottom: 40px; text-transform: uppercase;
        }
        .cover-refs { font-size: 9pt; text-align: center; font-weight: bold; line-height: 2; margin-top: 50px; }

        /* ── Corps du contrat ── */
        .body-page { padding: 30px 40px; }
        .intro { font-size: 10pt; margin-bottom: 20px; }
        .partie { margin-bottom: 16px; }
        .partie-nom { font-weight: bold; }
        .article { margin-bottom: 20px; }
        .article-titre { font-weight: bold; font-size: 10.5pt; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .article-corps { font-size: 9.5pt; line-height: 1.7; }
        .article-corps p { margin-bottom: 6px; }
        .article-corps ul { padding-left: 20px; margin-bottom: 8px; }
        .article-corps ul li { margin-bottom: 3px; }
        .article-corps .sous-titre { font-weight: bold; margin-top: 8px; margin-bottom: 4px; }
        .separateur { text-align: center; font-size: 9pt; margin: 16px 0 10px 0; }

        /* ── Signatures ── */
        .sign-wrap { margin-top: 40px; }
        table.sign { width: 100%; border-collapse: collapse; }
        table.sign td { vertical-align: top; width: 50%; padding-top: 10px; }
        .sign-label { font-size: 9pt; font-weight: bold; text-transform: uppercase; color: #1e3a5f; margin-bottom: 50px; }
        .sign-line { border-top: 1px solid #334155; width: 70%; margin-top: 52px; }
        .sign-line-r { border-top: 1px solid #334155; width: 70%; margin-top: 52px; margin-left: auto; }
        .sign-sub { font-size: 8pt; color: #64748b; margin-top: 5px; }
        .sign-sub-r { font-size: 8pt; color: #64748b; margin-top: 5px; text-align: right; }
        .sign-ville { font-size: 9pt; text-align: right; margin-bottom: 30px; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<div class="footer">
    {{ $cabinet['nom'] }}
    @if($cabinet['nif']) &nbsp;·&nbsp; NIF&nbsp;: {{ $cabinet['nif'] }} @endif
    @if($cabinet['nis']) &nbsp;·&nbsp; NIS&nbsp;: {{ $cabinet['nis'] }} @endif
</div>

<div class="page">

    {{-- ── PAGE DE GARDE ── --}}
    <div class="cover">
        <div class="cover-institutions">
            <p>République Algérienne Démocratique et Populaire</p>
            <p>Conseil National de l'Organisation Nationale des Comptables Agréés</p>
            <p>Chambre Nationale des Commissaires aux Comptes</p>
        </div>

        <div class="cover-logo-wrap">
            <table class="cover-logo-grid" cellpadding="0" cellspacing="3">
                <tr><td class="logo-light"></td><td class="logo-dark"></td></tr>
                <tr><td class="logo-dark"></td><td class="logo-light"></td></tr>
            </table>
        </div>
        <div class="cover-cabinet-nom">{{ $cabinet['nom'] }}</div>
        <div class="cover-cabinet-sous">{{ $cabinet['soustitre'] ?? 'Cabinet de comptabilité et commissariat aux comptes' }}</div>

        @if($cabinet['adresse'])
        <div style="font-size:9pt; color:#334155; margin-bottom: 30px;">Adresse : {{ $cabinet['adresse'] }}</div>
        @endif

        <div class="cover-ref">
            N°: {{ $mission->convention_numero }}<br>
            Date : {{ $dateContrat }}
        </div>

        <div class="cover-title">Convention de prestation de services</div>
        <div class="cover-subtitle">"{{ strtoupper($mission->prestation->designation) }}"</div>

        <div class="cover-refs">
            Textes de Référence :<br>
            *Article 45 de la loi 10-01 du 11/07/2010<br>
            * Décret exécutif n° 04/02 du 23/06/2004
        </div>
    </div>

    {{-- ── CORPS DU CONTRAT ── --}}
    <div class="page-break"></div>
    <div class="body-page">

        <p class="intro">En date du {{ $dateContrat }}, Il a été convenu ce qui suit entre :</p>

        <div class="partie">
            <span class="partie-nom">{{ $cabinet['nom'] }}</span>
            @if($cabinet['adresse'])<br>Adresse professionnelle : {{ $cabinet['adresse'] }}@endif
            @if($cabinet['agrement'])<br>Agrément n° : {{ $cabinet['agrement'] }}@endif
            <br>Ci-après dénommé « Le Cabinet »
        </div>

        <p style="margin-bottom: 12px;">D'autre part,</p>

        <div class="partie">
            Raison social : <span class="partie-nom">{{ strtoupper($mission->entreprise->raison_sociale) }}</span>
            @if($mission->entreprise->adresse)<br>Adresse du siège social: {{ $mission->entreprise->adresse }}@if($mission->entreprise->ville), {{ $mission->entreprise->ville }}@endif
            @endif
            @if($mission->entreprise->nif)<br>• NIF : {{ $mission->entreprise->nif }}@endif
            @if($mission->entreprise->article_imposition)<br>• ART : {{ $mission->entreprise->article_imposition }}@endif
            @if($mission->entreprise->num_rc)<br>• RC : {{ $mission->entreprise->num_rc }}@endif
        </div>

        <p style="margin-bottom: 16px;">D'autre part,</p>
        <p style="margin-bottom: 16px;">Les Deux Parties, ayant déclaré disposer de leur capacité à conclure le présent contrat<br>Il ont convenu ce qui suit :</p>

        {{-- Article 01 --}}
        <div class="article">
            <div class="article-titre">Article 01 : Objet du contrat :</div>
            <div class="article-corps">
                <p>- Aux termes du présent alinéa, les deux parties s'engagent, séparément, à :</p>
                <p><strong>• Pour le cabinet :</strong> En vertu des articles 41 et 42 de la loi 10-01 du 11/07/2010</p>

                <p class="sous-titre">01- Services de gestion, suivi et consulting</p>
                <ul>
                    <li>Elaboration des fiches de paie et journal de paie</li>
                    <li>Elaboration et présentation des bilans comptables annuels</li>
                    <li>Répondre aux correspondances fiscales et parafiscales, et leur suivi</li>
                    <li>Elaboration des déclarations fiscales</li>
                    <li>Elaboration et présentation des déclarations parafiscales, mensuelles et annuelles (CASNOS)</li>
                    <li>Se faire extraire l'attestation de paiement des montants dus et l'attestation de non imposition</li>
                </ul>

                <p class="sous-titre">02- Services fiscaux :</p>
                <ul>
                    <li>Actualisation et mise à jour des livrets comptables et commerciaux (selon articles 09, 10 et 11 du code de commerce – Chapitre Livrets commerciaux), (selon article 42 de la loi 10-01)</li>
                    <li>Tenue, élaboration et présentation du bilan fiscal annuel (selon dispositions des articles 99-1 et 151-1 du code des impôts directs et taxes diverses)</li>
                </ul>

                <p class="sous-titre">03- Assistance et représentation :</p>
                <ul>
                    <li>Devant l'administration des impôts, de sécurité sociale, caisse de chômage, CASNOS</li>
                    <li>Devant l'inspection du travail</li>
                    <li>Devant le registre de commerce et la direction de commerce</li>
                    <li>Devant le trésor public</li>
                    <li>Devant les banques et établissement d'investissement</li>
                </ul>
                <p>Selon les dispositions de l'article 43 de la loi 10-01 du 11/07/2010.</p>

                <p style="margin-top:8px;"><strong>• Pour le client :</strong></p>
                <ul>
                    <li>Le Client (Personne physique ou morale) sera soumis au plan de charges, selon le règlement intérieur du Cabinet</li>
                    <li>Le Client devra mettre à la disposition du Cabinet toutes les renseignements et documents comptables et financiers nécessaires pour la bonne exécution de la mission, et s'oblige à respecter les délais fixés</li>
                    <li>Le Client déclare assumer toute responsabilité en matière des informations fournies au Cabinet</li>
                    <li>Il s'engage à fournir tous les états, renseignements, éclaircissements et réponses sur les observations nécessaires pour la bonne exécution de la mission</li>
                </ul>
            </div>
        </div>

        {{-- Article 02 --}}
        <div class="article">
            <div class="article-titre">Article 02 : Le secret professionnel</div>
            <div class="article-corps">
                <p><strong>Alinéa 01 :</strong></p>
                <p>- La Première Partie et ses collaborateurs s'obligent à garder le secret professionnel, étant donné que tous les états et renseignements du Client sont de nature sensible et confidentielle. De ce fait, le cabinet n'est pas autorisé à diffuser ces renseignements, sans l'accord préalable écrit du Client, sauf autrement stipulé, selon l'article 71 de la loi 10-01.</p>
                <p><strong>Alinéa 02 :</strong></p>
                <p>- Le Cabinet est tenu d'aviser le Client, en cas de constations d'une difficulté ou obstacle empêchant la bonne exécution de la mission.</p>
            </div>
        </div>

        {{-- Article 03 --}}
        <div class="article">
            <div class="article-titre">Article 03 : Honoraires :</div>
            <div class="article-corps">
                <p>- En vertu des dispositions de l'article 45 de la loi 10-01 et sur accord des Parties, le Client s'engage à s'acquitter des honoraires dus au Cabinet.</p>
                <p>Le montant total de la prestation est de <strong>{{ number_format((float)$mission->prix_ht, 2, ',', ' ') }} Dinars algériens</strong>, le paiement se ferait en trois tranches :</p>
                <ul>
                    <li>La première tranche est à <strong>30%</strong> du montant total de la prestation.</li>
                    <li>La seconde tranche est à <strong>30%</strong> du montant de la prestation.</li>
                    <li>La dernière est à <strong>40%</strong> du montant de la prestation.</li>
                </ul>
                <ul>
                    <li>A chaque paiement le cabinet s'engage à délivrer une facture.</li>
                    <li>La première partie s'engage à livrer des travaux intermédiaires au paiement de la seconde tranche et la totalité des travaux après le dernier paiement prévu à la fin de la prestation.</li>
                    <li>En cas de rupture du contrat du fait de la deuxième Partie, sans raison légale ou professionnelle, le Cabinet sera en droit de percevoir le reste du montant convenu stipulé dans le présent contrat.</li>
                    <li>Les honoraires seront réglés dans un délai ne dépassant pas un mois (30 jours), soit en espèces, par un chèque ou par virement bancaire.</li>
                </ul>
            </div>
        </div>

        {{-- Article 04 --}}
        <div class="article">
            <div class="article-titre">Article 04 : Force majeure</div>
            <div class="article-corps">
                <p>- Il est entendu par 'Force majeure' tout acte ou événement imprévisible ou incontournable pouvant avoir des impacts directs ou indirects sur l'exécution normale des obligations nées par la présente convention.</p>
                <p>- La partie atteinte par cette force majeure ne sera considérée responsable de cette défaillance, et sera exonérée de cette responsabilité pour raison de suspension provisoire du contrat, jusqu'à disparition des raisons ayant entraîné cette rupture.</p>
            </div>
        </div>

        {{-- Article 05 --}}
        <div class="article">
            <div class="article-titre">Article 05 : Modification :</div>
            <div class="article-corps">
                <p>- Toute modification sur les dispositions du présent contrat sera l'objet d'un avenant approuvé et signé par les deux parties.</p>
            </div>
        </div>

        {{-- Article 06 --}}
        <div class="article">
            <div class="article-titre">Article 06 : Responsabilité</div>
            <div class="article-corps">
                <p>- Le Client accepte et consent d'indemniser et de protéger le Cabinet contre tout préjudice causé par toute erreur, et de toute réclamation du fait de perte ou préjudice subi, à l'exception des cas de mauvais comportement ou négligence de la part du Cabinet.</p>
            </div>
        </div>

        {{-- Article 07 --}}
        <div class="article">
            <div class="article-titre">Article 07 : Rupture du contrat</div>
            <div class="article-corps">
                <p>- Il est autorisé aux parties de mettre fin au contrat de prestation de services, sous condition de présenter à l'autre partie un préavis de (30 jours).</p>
                <p>- En cas de résiliation dudit contrat, des notes d'honoraires portant sur les services faits seront soumises selon les modalités de paiement citées dans le présent contrat.</p>
            </div>
        </div>

        {{-- Article 08 --}}
        <div class="article">
            <div class="article-titre">Article 08 : Litiges</div>
            <div class="article-corps">
                <p>- Tout litige né à l'occasion d'interprétation ou d'exécution du présent contrat sera réglé à l'amiable.</p>
                <p>- A défaut, ledit litige sera exposé devant le tribunal du lieu du Cabinet, notamment les litiges liés à l'exécution ou l'annulation dudit contrat.</p>
            </div>
        </div>

        {{-- Article 09 --}}
        <div class="article">
            <div class="article-titre">Article 09 : Durée du contrat</div>
            <div class="article-corps">
                <p>- Le présent contrat entrera en vigueur à compter de la date de sa signature par les deux parties. Il est valable pour une durée de trois (03 ans). Il sera reconduit d'office, en l'absence d'un préavis de trente (30) jours avant son expiration, de l'une des parties, exprimant son intention de le rompre.</p>
            </div>
        </div>

        {{-- Signatures --}}
        <div class="sign-ville">Fait à {{ $ville }}, le {{ $dateContrat }}</div>
        <div class="sign-wrap">
            <table class="sign" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="sign-label">Le Cabinet</div>
                        <div style="font-size:8pt; color:#64748b; margin-bottom: 45px;">Signature du représentant légal</div>
                        <div class="sign-line"></div>
                    </td>
                    <td style="text-align:right;">
                        <div class="sign-label" style="text-align:right;">Le Client</div>
                        <div style="font-size:8pt; color:#64748b; margin-bottom: 45px; text-align:right;">Signature du représentant légal</div>
                        <div class="sign-line-r"></div>
                    </td>
                </tr>
            </table>
        </div>

    </div>

</div>
</body>
</html>
