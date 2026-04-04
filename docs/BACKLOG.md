# Ledge — Backlog Produit v2.1

> RNCP 39583 · Expert en Developpement Logiciel · YNOV · Cheraitia Mohamed Amine · 2025
> **43 US · 170 pts · 9 couches de dependances · 3 sprints**

---

## Legende

| Symbole | Signification |
|---|---|
| ★ | Competence RNCP obligatoire |
| ✅ | US terminee |
| 🔧 | US en cours |
| ⭐ | Noeud critique — debloque le plus d'US en aval |
| M | Must Have |
| S | Should Have |
| C | Could Have |

---

## Couche 0 — Socle · Aucune dependance · Peut demarrer immediatement

### US-01 · Auth · M · 3 pts · Sprint 1 ✅

**En tant qu'utilisateur**, je veux m'authentifier avec email et mot de passe **afin d'** acceder a mon espace selon mon role.

- Sanctum SPA cookie-based · session persistante · CSRF · logout
- Guards Vue Router : `meta.zone = 'backoffice'` → bloque clients · `meta.zone = 'portail'` → bloque staff
- Depend de : —

---

### US-03 · Parametrage cabinet · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux configurer les parametres du cabinet (NIF, NIS, RIB, logo, adresse) **afin que** ces donnees apparaissent automatiquement sur tous les documents emis.

- Table `settings` cle/valeur · logo uploadable · donnees figees sur les PDFs a la generation
- Depend de : —

---

### US-04 · TVA & Timbre historises · M · 5 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux gerer les taux TVA et timbre fiscal avec historique versionne **afin que** chaque facture retrouve toujours le taux en vigueur a sa date d'emission.

- Tables `tva_taux` / `timbre_taux` avec `date_debut` / `date_fin`
- `TvaTaux::enVigueurLe($date)` · `TimbreTaux::enVigueurLe($date)`
- Snapshot immuable copie a la creation de chaque facture — **JAMAIS** `Carbon::now()` dans la resolution
- Timbre fiscal : 1%, plafonne 2 500 DA
- Depend de : —

---

### US-05 · Grille tarifaire · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux definir la grille tarifaire **afin de** calculer automatiquement les prix des missions.

- Prestations : CAC (300 000 DA) · ACMPT (120 000 DA) · AENT (80 000 DA) · ASSC (100 000 DA) · A&C (110 000 DA)
- Indices regime fiscal : Forfait x1.0 · Reel x1.5
- Indices categorie : TPE x1.0 · PME x1.75 · GE x2.0
- `Prestation::calculerPrixHt($regime, $categorie)` → `tarif_initial x indice_regime x indice_categorie`
- Modification sans effet retroactif sur les missions existantes
- Depend de : —

---

### US-17 · Exercices fiscaux · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux ouvrir et cloturer un exercice fiscal **afin de** separer strictement les documents par annee civile.

- `Exercice::current()` — un seul exercice ouvert a la fois
- Numerotation reinitialisee au 1er janvier · `lockForUpdate` pour eviter les doublons
- Cloture irreversible · documents archives par exercice
- Depend de : —

---

## Couche 1 — Depend du socle

### US-02 · Gestion utilisateurs · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux creer des comptes et affecter les roles **afin de** controler les acces par profil.

- Spatie Permission · 4 roles : `admin` · `collaborateur` · `secretaire` · `client`
- Inscription sans role · affectation manuelle par l'Admin · desactivation sans suppression
- `users.entreprise_id` : nullable — NULL pour staff, renseigne uniquement pour `client`
- Depend de : **US-01**

---

### US-06 · Fiche entreprise prospect · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux creer une fiche entreprise (raison sociale, NIF, NIS, RC, secteur, regime fiscal, categorie) avec le statut Prospect **afin de** suivre les entreprises en cours de demarche.

- Statut initial = Prospect · validation format NIF/NIS · unicite NIF
- Regime fiscal et categorie obligatoires · pas de facture tant que Prospect
- Protection suppression : bloquee si devis ou missions associes
- Depend de : **US-05**

---

### US-40 · CHANGELOG SemVer · M · 2 pts · Sprint 1 ✅ `C4.3.2 ★`

**En tant que developpeur**, je veux maintenir un `CHANGELOG.md` versionne en SemVer et des GitHub Releases **afin de** documenter chaque version livree.

- Format SemVer `v1.0.0` · sections Added / Changed / Fixed
- GitHub Release par sprint · demarre des le 1er commit
- Depend de : **US-01**

---

### US-43 · CRUD prestations · S · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux ajouter, modifier et supprimer des prestations depuis l'interface **afin de** faire evoluer le catalogue tarifaire sans intervention technique.

- CRUD complet (`store`, `update`, `destroy`) sur `PrestationController` — actuellement lecture seule
- Guard admin uniquement · protection suppression si missions associees
- Interface settings ou page dediee
- Depend de : **US-05**

---

## Couche 2

### US-08 · Contacts entreprise · M · 2 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux gerer les contacts d'une entreprise **afin de** savoir a qui adresser les communications.

- Actuellement : champ `contact_principal` (string) sur la table `entreprises`
- Evolution prevue : table `contacts` separee · plusieurs contacts par entreprise · marquage contact principal
- Le contact principal recoit les mails de relance automatiques
- Depend de : **US-06**

---

### US-09 · Recherche et filtres entreprises · S · 3 pts · Sprint 1

**En tant qu'administrateur**, je veux rechercher et filtrer les entreprises **afin de** retrouver rapidement un dossier.

- Recherche full-text · filtres combinables · filtre Prospect/Client · export CSV
- Depend de : **US-06**

---

### US-11 · Creation devis · M · 5 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux creer un devis pour une entreprise **afin que** le prix HT soit calcule automatiquement selon la grille tarifaire.

- Un devis = une seule prestation — regle immuable
- Calcul : `tarif x indice_regime x indice_categorie` — calcule a la creation, jamais modifiable
- Numerotation `DV{ANNEE}-{NNN}` · statuts : brouillon / envoye / accepte / refuse / expire
- `FacturationService::creerDevis()` avec calcul TVA/timbre automatique
- Frontend : `Select` prestation dans le formulaire, prix HT affiche en lecture seule
- Bouton "Envoyer" : change le statut en `envoye` — **l'envoi par mail (PDF en PJ) est prevu mais non implemente, depend de US-12**
- Depend de : **US-01, US-03, US-04, US-05, US-06, US-17**

---

## Couche 3

### US-12 · PDF devis conforme · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux generer un PDF du devis conforme au format algerien **afin de** l'envoyer a l'entreprise pour validation.

- DomPDF · en-tete cabinet (NIF/NIS/RIB) · NIF/NIS/RC client · tableau prestation/prix/TVA · timbre fiscal · zone signature
- Depend de : **US-11, US-03**

---

### US-18 · Creation mission ⭐ · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux creer une mission liee a un client et une prestation **afin de** suivre l'avancement des travaux.

- Numerotation `M{ANNEE}-{NNN}` · date debut/fin · statuts : en_cours / terminee / suspendue / annulee
- Prix HT fige des la creation — `$prestation->calculerPrixHt($regime, $categorie)` — immuable
- `MissionService::creerMission()` avec generation reference sequentielle
- Conversion devis → mission via `POST /devis/{id}/convertir-en-mission` (stocke `devis_id`)
- Protection suppression : bloquee si factures associees
- Depend de : **US-06, US-05, US-17**

---

### US-29 · Portail client — acces · M · 5 pts · Sprint 2 ✅

**En tant que client**, je veux acceder a un portail securise distinct **afin de** consulter mes donnees en autonomie.

- Activation par l'Admin depuis la fiche Entreprise → User cree avec `entreprise_id` + role `client` + email set-password
- URL `/portail` dediee · scope isolation absolue `->where('entreprise_id', auth()->user()->entreprise_id)`
- `portail_actif = 1` pour activer · `0` pour revoquer
- Middleware `EnsurePortailAccess` deja en place
- Depend de : **US-01, US-02, US-06, US-08**

---

## Couche 4

### US-07 · Bascule Prospect → Client · M · 2 pts · Sprint 1 ✅

**En tant que systeme**, je veux que le statut bascule automatiquement de Prospect a Client lors de la creation de la premiere mission **afin de** distinguer les deux profils sans action manuelle.

- `MissionObserver` dispatch `MissionCreated` → `ConvertProspectToClient` listener
- Indicateur visuel · filtre Prospects/Clients disponible
- Depend de : **US-06, US-18**

---

### US-13 · Creation facture avec tranches ⭐ · M · 8 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux creer une facture a partir d'une mission avec paiement par tranches **afin de** facturer le client en respectant l'exercice fiscal courant.

- Numerotation `FF{ANNEE}-{NNN}` · reset au 1er janvier · `lockForUpdate` anti-doublon
- **3 tranches : T1 = 30% · T2 = 30% · T3 = 40% (solde)**
- Snapshots immuables copies UNE SEULE FOIS a la creation :
  `taux_tva` · `montant_tva` · `montant_timbre` · `montant_ttc`
- `FacturationService::creerFacture()` deja implementee
- Protection suppression : bloquee si paiements ou avoirs associes
- Depend de : **US-18, US-04, US-17, US-03**

---

### US-19 · Creation taches · M · 3 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux creer des taches et les assigner a des collaborateurs **afin d'** organiser le travail en equipe.

- Date debut/fin · statut initial `a_faire` · notification au collaborateur assigne
- Plusieurs taches par mission · `TacheController` nested sous `/missions/{id}/taches`
- Protection suppression : bloquee si commentaires associes
- Depend de : **US-18, US-02**

---

### US-23 · Calendrier interactif FullCalendar · M · 8 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux visualiser toutes les missions et taches dans un calendrier interactif **afin d'** avoir une vue globale de la charge equipe.

- FullCalendar (Vue.js) · vues mois / semaine / jour · glisser-deposer
- Code couleur par statut · filtre par collaborateur
- Depend de : **US-18, US-19**

---

### US-24 · PDF convention et mandat · S · 3 pts · Sprint 2

**En tant qu'administrateur**, je veux generer une convention et un mandat PDF lies a une mission **afin de** formaliser la relation contractuelle.

- DomPDF · numerotation `CV{ANNEE}-{NNN}` / `MD{ANNEE}-{NNN}`
- Telechargeable · visible portail si `visible_portail = true`
- Depend de : **US-18, US-03**

---

## Couche 5

### US-14 · PDF facture conforme DGI · M · 5 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux generer un PDF de facture conforme DGI **afin qu'**il soit juridiquement recevable en Algerie.

- DomPDF · NIF/NIS/RC cabinet + client · TVA 19%/9% · timbre 1% plafonne 2 500 DA
- Montant en lettres (francais) · RIB cabinet · numero chronologique
- Depend de : **US-13, US-03**

---

### US-15 · Paiements avec statut automatique · M · 3 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux enregistrer les paiements par tranche **afin de** suivre le recouvrement en temps reel.

- Mode : virement / cheque / especes / autre · numero de piece
- Statut auto : `en_attente → partiel → solde` · `FacturationService::recalculerStatutPaiement()`
- Event `InvoicePaid` → `CancelRelancesOnPayment` : annulation automatique des relances en cours
- Routes : `POST /factures/{id}/paiements` · `DELETE /factures/{id}/paiements/{id}`
- Depend de : **US-13**

---

### US-20 · Taches collaborateur — lecture · M · 3 pts · Sprint 2 ✅

**En tant que collaborateur**, je veux consulter uniquement mes taches assignees **afin de** savoir ce que j'ai a faire.

- Scope `user_id = auth()` · aucune tache d'un autre collaborateur visible
- Tri statut + date · detail consultable
- Depend de : **US-19**

---

### US-21 · Changer statut tache · M · 2 pts · Sprint 2 ✅

**En tant que collaborateur**, je veux changer le statut de mes taches **afin d'** informer l'admin de l'avancement.

- Statuts : `a_faire → en_cours → termine` · `bloque` possible a tout moment
- Modification reservee aux taches assignees a l'utilisateur connecte
- Depend de : **US-19, US-20**

---

### US-22 · Commentaires sur taches · M · 3 pts · Sprint 2 ✅

**En tant que collaborateur**, je veux ajouter / modifier / supprimer mes commentaires sur une tache **afin de** laisser des remarques sur le deroulement.

- `TacheCommentaireController` avec CRUD (index, store, update, destroy)
- `TacheCommentairePolicy` : modification/suppression reservee a l'auteur · Admin peut tout modifier/supprimer
- Routes : `apiResource('taches.commentaires')` nested
- Depend de : **US-19**

---

### US-30 · Portail — mes factures · M · 3 pts · Sprint 2 ✅

**En tant que client**, je veux consulter mes factures et telecharger les PDFs **afin de** gerer ma comptabilite sans appeler le cabinet.

- Scope `entreprise_id` · filtre exercice + statut · solde impaye en evidence
- Aucune modification possible
- Depend de : **US-29, US-13, US-14**

---

## Couche 6

### US-16 · Avoir sur facture · M · 5 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux emettre un avoir sur une facture **afin de** corriger une erreur de facturation.

- Numerotation `FA{ANNEE}-{NNN}` · reference facture d'origine (`facture_origine_id`) · montants negatifs
- PDF genere · log immuable
- Depend de : **US-13, US-14**

---

### US-25 · Regles de relance · M · 3 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux configurer les regles de relance automatique (J+15, J+30, J+60) **afin d'** automatiser le recouvrement.

- 3 niveaux · delai parametrable
- Templates mails avec variables dynamiques : `{{client}}` · `{{montant}}` · `{{numero_facture}}` · `{{echeance}}`
- Table `relances` deja creee en migration
- Depend de : **US-13, US-08**

---

### US-27 · Creances impayees · M · 3 pts · Sprint 2 ✅

**En tant que secretaire**, je veux consulter la liste des creances **afin de** suivre les encaissements en attente.

- Filtre `statut_paiement = en_attente ou partiel` · tri anciennete
- Montant impaye par client · export CSV · lecture seule
- Depend de : **US-13, US-15**

---

### US-31 · Portail — mes missions · S · 3 pts · Sprint 2 ✅

**En tant que client**, je veux suivre l'avancement de mes missions depuis le portail **afin d'** etre informe sans appeler le cabinet.

- Scope `entreprise_id` · statuts + % avancement · taches visibles sans commentaires internes
- Lecture seule stricte
- Depend de : **US-29, US-18**

---

### US-32 · Portail — mes documents · S · 5 pts · Sprint 2

**En tant que client**, je veux acceder a mes documents partages (conventions, mandats) depuis le portail **afin de** retrouver mon dossier complet en ligne.

- Seuls les docs avec `visible_portail = true` · scope `entreprise_id`
- Telechargement direct · categorise par type · table `documents` deja creee
- Depend de : **US-29, US-24**

---

### US-10 · Vue 360° client · S · 5 pts · Sprint 2

**En tant qu'administrateur**, je veux voir l'historique complet d'un client (missions, factures, paiements, relances) **afin d'** avoir une vue 360° de la relation.

- Vue unifiee · filtrable par exercice · solde impaye en evidence · historique chronologique
- Depend de : **US-06, US-13, US-18**

---

### US-33 · Dashboard KPI · M · 5 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux un tableau de bord avec les indicateurs cles **afin d'** avoir une vision instantanee du cabinet.

- CA du mois · TVA collectee · taux recouvrement · missions actives
- Widgets dynamiques · filtrable par exercice · alertes si seuil depasse
- Depend de : **US-13, US-15, US-18**

---

## Couche 7

### US-26 · Relances automatiques · M · 5 pts · Sprint 2 ✅

**En tant que systeme**, je veux envoyer automatiquement les relances aux clients depassant le delai parametre **afin de** reduire les retards de paiement sans intervention manuelle.

- Job cron quotidien (Laravel Queue) · mail au contact principal
- Annulation automatique si `InvoicePaid`
- Historique conserve · log d'envoi
- Depend de : **US-25, US-08**

---

### US-28 · Relance manuelle · S · 5 pts · Sprint 2 ✅

**En tant qu'administrateur ou secretaire**, je veux declencher manuellement une relance sur une facture impayee **afin de** gerer les cas sensibles hors delais automatiques.

- Bouton "Envoyer relance" · confirmation avant envoi · mail immediat
- Entree dans le journal des relances
- Depend de : **US-25, US-27**

---

### US-34 · KPI objectifs collaborateurs · S · 5 pts · Sprint 2

**En tant qu'administrateur**, je veux definir des objectifs KPI par collaborateur et suivre le realise vs la cible **afin de** piloter la performance individuelle.

- Objectif : CA / missions cloturees / delai moyen facturation
- Comparaison realise vs cible · export PDF
- Depend de : **US-33, US-02**

---

### US-35 · Rapport de cloture d'exercice · S · 3 pts · Sprint 3

**En tant qu'administrateur**, je veux generer un rapport financier de cloture d'exercice PDF **afin de** preparer la cloture annuelle.

- CA par mois · TVA collectee · timbre collecte · impayes fin exercice · filtre par client
- Depend de : **US-13, US-17, US-33**

---

## Couche 8 — Qualite transversale · Sprint 3

### US-36 · OWASP Top 10 · M · 5 pts · Sprint 3 `C2.2.3 ★`

**En tant que developpeur**, je veux implementer les controles OWASP Top 10 **afin que** l'application soit securisee pour un usage en production.

- CSRF sur tous les formulaires · Eloquent uniquement (jamais `DB::raw()` avec input)
- Form Request sur tout `store()` et `update()` · brute-force login · headers HTTP securises
- Pas de `v-html` avec donnees utilisateur (XSS)
- Depend de : tout le code

---

### US-37 · RGAA accessibilite · M · 5 pts · Sprint 3 `C2.2.3 ★`

**En tant que developpeur**, je veux integrer les criteres RGAA **afin de** valider la competence C2.2.3.

- Score Lighthouse accessibilite >= 85 · audit axe DevTools
- `aria-label` sur boutons sans texte · `role="alert"` + `aria-live="polite"` sur erreurs
- Skip link "Aller au contenu principal" · `:focus-visible` outline · labels sur tous les inputs
- Audit documente dans le cahier de recettes
- Depend de : tout le frontend

---

### US-38 · Tests unitaires · M · 5 pts · Sprint 3 `C2.2.2 ★`

**En tant que developpeur**, je veux ecrire les tests unitaires des modules critiques **afin de** garantir la fiabilite des calculs.

- PHPUnit · couverture >= 80% sur les modules testes
- Test calcul HT (`tarif x indices`)
- Test snapshot TVA (date 2026 retourne 19% meme appele en 2030) — `TvaTaux::enVigueurLe()`
- Test statut paiement automatique (`en_attente → partiel → solde`)
- Test numerotation annuelle reset au 1er janvier
- Test tranches 30% / 30% / 40%
- Tests deja existants : 38 tests, 81 assertions (PHPUnit)
- Depend de : **US-04, US-05, US-13, US-15, US-17**

---

### US-39 · Supervision MCO · M · 3 pts · Sprint 3 `C4.1.2 ★`

**En tant qu'administrateur**, je veux que l'application soit supervisee **afin d'** etre alerte immediatement en cas d'anomalie.

- UptimeRobot — ping HTTP toutes les 5 min · alerte mail/SMS si down
- Laravel Health — endpoint `/health` (BDD, Redis, queue, stockage)
- Sentry free tier — remontee automatique des erreurs PHP avec contexte
- Logs rotatifs quotidiens `storage/logs/laravel-YYYY-MM-DD.log`
- Depend de : deploiement staging

---

### US-41 · Protections de suppression · S · 3 pts · Sprint 3

**En tant que developpeur**, je veux mettre en place des protections de suppression sur les entites liees **afin d'** eviter la destruction accidentelle de donnees critiques.

- Entreprise bloquee si devis ou missions associes (deja en place — HTTP 409)
- Mission bloquee si factures associees (deja en place — HTTP 409)
- Facture bloquee si paiements ou avoirs associes (deja en place — HTTP 409)
- Tache bloquee si commentaires associes
- Reponse API `422` avec message explicite
- Depend de : **US-11, US-13, US-15, US-18, US-22**

---

### US-42 · Dashboard collaborateur · S · 3 pts · Sprint 3

**En tant que collaborateur**, je veux un dashboard dedie affichant mes missions et mes KPI personnels **afin d'** avoir une vue de mon propre perimetre.

- Missions assignees · taux de completion des taches
- Distinct du dashboard Admin global
- Depend de : **US-33, US-20**

---

### US-44 · Envoi devis par mail · C · 3 pts · Apres Sprint 3

**En tant qu'administrateur**, je veux envoyer le devis par mail au contact principal de l'entreprise **afin que** le client le recoive directement sans manipulation manuelle.

- Bouton "Envoyer" declenche un `Mailable` Laravel avec le PDF devis en piece jointe
- Mail envoye au `contact_principal` de l'entreprise
- Statut passe automatiquement a `envoye` apres envoi confirme
- Depend de : **US-11, US-12** (PDF devis obligatoire)

---

## Resume

### Par sprint

| Sprint | US | Pts | Contenu principal |
|---|---|---|---|
| Sprint 1 | US-01 ✅, 02 ✅, 03 ✅, 04 ✅, 05 ✅, 06 ✅, 07 ✅, 08, 09, 11 ✅, 12 ✅, 17 ✅, 18 ✅, 40 ✅, 43 ✅ | 49 pts | Auth · Referentiel · Clients · Devis · Mission · Exercice |
| Sprint 2 | US-10, 13 ✅, 14 ✅, 15 ✅, 16 ✅, 19 ✅, 20 ✅, 21 ✅, 22 ✅, 23 ✅, 24, 25 ✅, 26 ✅, 27 ✅, 28 ✅, 29 ✅, 30 ✅, 31 ✅, 32, 33 ✅, 34 | 96 pts | Factures · Taches · Portail · Relances · KPI |
| Sprint 3 | US-35, 36, 37, 38, 39, 41, 42 | 27 pts | Qualite · OWASP · RGAA · Tests · MCO |
| **Total** | **43 US** | **170 pts** | |

### Par priorite MoSCoW

| Priorite | US | Pts |
|---|---|---|
| Must Have | 29 US | 125 pts |
| Should Have | 14 US | 45 pts |

### Avancement

| Statut | US | Pts |
|---|---|---|
| ✅ Termine | 30 US | 108 pts |
| 🔧 En cours | — | — |
| A faire | 13 US | 62 pts |

### Noeuds critiques

| US | Raison |
|---|---|
| **US-18 Mission** ⭐ ✅ | Debloque 11 US en aval (taches, factures, calendrier, portail, KPI, relances) |
| **US-13 Facture** ⭐ ✅ | Debloque 9 US en aval (PDF DGI, paiements, avoirs, relances, portail, KPI) |

> US-18, US-13, US-14, US-15, US-16, US-19, US-20, US-21, US-22, US-25, US-26, US-27, US-28, US-29, US-30, US-31 livrees.
> Sprint 2 peut continuer : US-33 (dashboard KPI), US-23 (calendrier), US-24 (PDF convention/mandat).

---

### Competences RNCP couvertes

| Competence | US |
|---|---|
| C2.2.2 ★ Tests unitaires | US-38 |
| C2.2.3 ★ Securite OWASP + RGAA | US-36, US-37 |
| C4.1.2 ★ Supervision & alertes | US-39 |
| C4.3.2 ★ Journal des versions | US-40 ✅ |
