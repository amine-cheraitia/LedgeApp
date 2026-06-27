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

### US-04 · TVA historisee · M · 5 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux gerer les taux TVA avec historique versionne **afin que** chaque facture retrouve toujours le taux en vigueur a sa date d'emission.

- Table `tva_taux` avec `date_debut` / `date_fin`
- `TvaTaux::enVigueurLe($date)`
- Snapshot immuable copie a la creation de chaque facture — **JAMAIS** `Carbon::now()` dans la resolution
- Note : le timbre fiscal a ete retire (toujours nul en pratique) — voir CHANGELOG
- Depend de : —

---

### US-51 · Gestion des taux TVA · M · 3 pts · Sprint 3 ✅

**En tant qu'administrateur**, je veux gerer les taux TVA depuis l'interface **afin de** ne pas acceder directement a la base de donnees.

- Page dediee : `/tva-taux` (admin) — datatable + dialog (categorie, taux, designation, dates, actif)
- Categories : **standard / exonere** (le taux reduit 9% ne s'applique pas a l'activite)
- Suppression bloquee si factures associees au taux (HTTP 409)
- **Toujours >= 1 taux Standard actif en vigueur ET >= 1 taux Exonere actif en vigueur** : desactiver, changer le type ou supprimer le dernier taux utilisable d'un type est bloque (HTTP 409) — sinon la facturation n'a plus de taux a appliquer
- `actif` conditionne l'application : `TvaTaux::enVigueurLe()` ne resout que les taux actifs
- Routes API : `GET /api/v1/referentiels/tva-taux` · `POST` · `PUT` · `DELETE`
- Choix de la **categorie TVA (standard / exonere)** a la creation d'une facture/devis — la modal affiche **Standard (taux actif en vigueur)** ; valeur fixee par la date (historisation)
- Depend de : **US-04**

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

### US-09 · Recherche et filtres entreprises · S · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux rechercher et filtrer les entreprises **afin de** retrouver rapidement un dossier.

- Recherche full-text · filtres combinables · filtre Prospect/Client · export CSV
- Depend de : **US-06**

---

### US-11 · Creation devis · M · 5 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux creer un devis pour une entreprise **afin que** le prix HT soit calcule automatiquement selon la grille tarifaire.

- Un devis = une seule prestation — regle immuable
- Calcul : `tarif x indice_regime x indice_categorie` — calcule a la creation, jamais modifiable
- Numerotation `DV{ANNEE}-{NNN}` · statuts : brouillon / envoye / accepte / refuse / expire
- `FacturationService::creerDevis()` avec calcul TVA automatique
- Frontend : `Select` prestation dans le formulaire, prix HT affiche en lecture seule
- Bouton "Envoyer" : change le statut en `envoye` — **l'envoi par mail (PDF en PJ) est prevu mais non implemente, depend de US-12**
- Depend de : **US-01, US-03, US-04, US-05, US-06, US-17**

---

## Couche 3

### US-12 · PDF devis conforme · M · 3 pts · Sprint 1 ✅

**En tant qu'administrateur**, je veux generer un PDF du devis conforme au format algerien **afin de** l'envoyer a l'entreprise pour validation.

- DomPDF · en-tete cabinet (NIF/NIS/RIB) · NIF/NIS/RC client · tableau prestation/prix/TVA · zone signature
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
  - 🐛 Correctif arrondi (#52) : T3 calculee comme **solde exact** (`prix_ht − T1 − T2`) — invariant `T1 + T2 + T3 == prix_ht` garanti et teste, y compris prix a centimes
- Snapshots immuables copies UNE SEULE FOIS a la creation :
  `taux_tva` · `montant_tva` · `montant_ttc`
- `FacturationService::creerFacture()` deja implementee
- Protection suppression : bloquee si paiements ou avoirs associes
- Depend de : **US-18, US-04, US-17, US-03**

---

### US-19 · Creation taches · M · 3 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux creer des taches et les assigner a des collaborateurs **afin d'** organiser le travail en equipe.

- Date debut/fin · statut initial `a_faire` · notification au collaborateur assigne
- Plusieurs taches par mission · `TacheController` nested sous `/missions/{id}/taches`
- Protection suppression : bloquee si commentaires associes
- 🔄 **Bornage dates & role d'affectation (fix/correctifs-planning)** : `date_debut` >= debut mission et `date_echeance` <= fin mission (sauf mission **en retard** → echeance libre au-dela) ; une tache n'est affectable qu'a un **collaborateur ou un administrateur** (jamais la secretaire). Regles appliquees cote backend (trait `ValidatesTacheDates`, rejet 422).
- 🔄 **Isolation des taches par role (fix/correctifs-planning)** : `Tache::scopeVisiblePour` + `TachePolicy::view` — un collaborateur ne **voit que ses taches** (liste mission, calendrier, fiche tache : **403** sinon) et ne peut **commenter** qu'une tache qui lui est affectee ; **immutabilite** des commentaires d'autrui (admin compris) preservee. Affine le perimetre d'**US-45**.
- Depend de : **US-18, US-02**

---

### US-23 · Calendrier interactif FullCalendar · M · 8 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux visualiser toutes les missions et taches dans un calendrier interactif **afin d'** avoir une vue globale de la charge equipe.

- FullCalendar (Vue.js) · vues mois / semaine / jour · glisser-deposer
- Code couleur par statut · filtre par collaborateur
- Refonte en onglets **Calendrier** / **Equipe** · vue annuelle (12 mois, plugin `multimonth`) par defaut · loader overlay · bouton « Nouvelle mission »
- Onglet **Equipe** : grille de disponibilite collaborateur x jour (charge **Disponible / Modere / Charge**)
- Legende dynamique par prestation (palette de couleurs)
- Tache avec **date de debut + echeance** affichee en **plage** (barre) ; drag = decale les 2 dates, resize = ajuste l'echeance ; filtre planning par **chevauchement** de plage
- 🔄 **Correctifs planning par role (fix/correctifs-planning)** : **collaborateur** = un seul calendrier de **ses taches**, colorees **par priorite**, non editable ; **admin** = onglets Missions / Equipe, clic sur une tache d'Equipe ouvre le meme modal que les missions ; onglet **Missions = missions uniquement** ; **4 vues** partout (**Annee / Mois / Semaine / Liste**) ; decalage d'un jour corrige (fin `allDay` exclusive FullCalendar) ; toasts d'erreur portant le message backend
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

- DomPDF · NIF/NIS/RC cabinet + client · TVA 19%/9%
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
- **Améliorations UX (feature/ux-encaissements-drawer)** : Drawer latéral remplace le Dialog — historique visible avant saisie, étape de confirmation, validation stricte (montant ≤ 0 / > restant), mise à jour temps réel sans re-ouverture, secrétaire peut corriger ses propres saisies, dark mode conforme RGAA.

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
- Le **1er commentaire** sur une tache `a_faire` la fait passer en `en_cours` (engagement) — une tache `terminee`/`annulee` n'est pas reactivee
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

### US-32 · Portail — mes documents · S · 5 pts · Sprint 2 ✅

**En tant que client**, je veux acceder a mes documents partages (conventions, mandats) depuis le portail **afin de** retrouver mon dossier complet en ligne.

- Seuls les docs avec `visible_portail = true` · scope `entreprise_id`
- Telechargement direct · categorise par type · table `documents` deja creee
- Depend de : **US-29, US-24**

---

### US-10 · Vue 360° client · S · 5 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux voir l'historique complet d'un client (missions, factures, paiements, relances) **afin d'** avoir une vue 360° de la relation.

- Vue unifiee · filtrable par exercice · solde impaye en evidence · historique chronologique
- Depend de : **US-06, US-13, US-18**

---

### US-33 · Dashboard KPI · M · 5 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux un tableau de bord avec les indicateurs cles **afin d'** avoir une vision instantanee du cabinet.

- CA du mois · TVA collectee · taux recouvrement · missions actives
- Widgets dynamiques · filtrable par exercice · alertes si seuil depasse
- **Graphiques (Chart.js / PrimeVue)** : CA mensuel en barres (12 mois de l'exercice) + repartition des missions par statut en camembert — dark mode + RGAA (table alternative `.sr-only`)
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

### US-34 · KPI objectifs collaborateurs · S · 5 pts · Sprint 2 ✅

**En tant qu'administrateur**, je veux definir des objectifs KPI par collaborateur et suivre le realise vs la cible **afin de** piloter la performance individuelle.

- Objectif : CA / missions cloturees / delai moyen facturation
- Comparaison realise vs cible · export PDF
- Depend de : **US-33, US-02**

---

### US-35 · Rapport de cloture d'exercice · S · 3 pts · Sprint 3

**En tant qu'administrateur**, je veux generer un rapport financier de cloture d'exercice PDF **afin de** preparer la cloture annuelle.

- CA par mois · TVA collectee · impayes fin exercice · filtre par client
- Depend de : **US-13, US-17, US-33**

---

## Couche 8 — Qualite transversale · Sprint 3

### US-36 · OWASP Top 10 · M · 5 pts · Sprint 3 `C2.2.3 ★` ✅

**En tant que developpeur**, je veux implementer les controles OWASP Top 10 **afin que** l'application soit securisee pour un usage en production.

- ✅ CSRF sur tous les formulaires (Sanctum cookie-based)
- ✅ Eloquent uniquement (jamais `DB::raw()` avec input utilisateur)
- ✅ Form Request sur tout `store()` et `update()` (23 FormRequests)
- ✅ Brute-force login : `throttle:5,1` sur POST /login
- ✅ Headers HTTP securises : CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- ✅ Policies Laravel sur chaque ressource : User, Prestation, Setting, Facture, Devis, Mission, Avoir
- ✅ Pas de `v-html` avec donnees utilisateur (XSS)
- ✅ CORS : allowed_headers restrictifs (suppression wildcard)
- ✅ A05 Security Misconfiguration : `ApiExceptionRenderer` — aucune fuite de SQL, host, port, stack, chemin fichier sur les routes API meme en `APP_DEBUG=true` (6 tests)
- ✅ A09 Logging & Monitoring : toutes les exceptions API loguees avec contexte structure (URL, methode, IP, user_id) — relayees a Sentry ; journal d'audit metier des actions sensibles (US-47)
- ✅ A06 Composants vulnerables : `composer audit` actif (advisories non silencees) — advisories Symfony 7.x heritees de Laravel 12 documentees et evaluees dans `docs/SECURITY.md` (impact reel faible a nul), remediation suivie
- Depend de : tout le code

---

### US-37 · RGAA accessibilite · M · 5 pts · Sprint 3 `C2.2.3 ★` ✅

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
- Depend de : **US-04, US-05, US-13, US-15, US-17**

**✅ Etat actuel (tests realises)**
- Back : **190 tests PHPUnit** (calcul HT, snapshot TVA, statut paiement automatique, numerotation annuelle reset, tranches 30/30/40)
- Front : **95 tests Vitest** — 18 modules API, store `auth` (login/logout/getters), composables (`useApiError`, `useCountUp`, patron CRUD via `useEntreprises`)
- Cahier de recettes : `docs/CAHIER-RECETTES.md` (scenarios fonctionnels / structurels / securite OWASP) — competence C2.3.1

**🔁 Reste a faire (tests a venir)**
- [ ] **Tests de composants front** (Vue + `@vue/test-utils`) — **apres la refonte design**, sur elements stables (poser des `data-testid` au prealable)
- [ ] **Tests E2E / parcours** (Playwright) — login → devis → facture → paiement, ancres sur `data-testid`
- [ ] **Mesure de couverture** — ajouter `@vitest/coverage-v8` + script `test:coverage`, viser **>= 80%** (la « majorite du code developpe » — ref. C2.2.2)
- [ ] **Extraire les formatters** (`formatDA` inline dans les pages → util partage `src/utils/`) puis les tester unitairement
- [ ] **Executer le cahier de recettes** — valider les scenarios ⏳, dont **RGA-05** (Lighthouse accessibilite >= 85)

---

### US-39 · Supervision MCO · M · 3 pts · Sprint 3 `C4.1.2 ★` ✅

**En tant qu'administrateur**, je veux que l'application soit supervisee **afin d'** etre alerte immediatement en cas d'anomalie.

- UptimeRobot — ping HTTP toutes les 5 min · alerte mail/SMS si down
- Laravel Health — endpoint `/health` (BDD, Redis, queue, stockage)
- Sentry free tier — remontee automatique des erreurs PHP avec contexte
- Logs rotatifs quotidiens `storage/logs/laravel-YYYY-MM-DD.log`
- Depend de : deploiement staging

---

### US-47 · Journal d'audit — piste d'audit des actions utilisateurs · S · 3 pts · Sprint 3 ✅

**En tant qu'administrateur**, je veux consulter une piste d'audit de toutes les actions effectuees sur les entites sensibles **afin de** garantir la tracabilite (qui a cree, modifie ou supprime quoi, et quand) et repondre aux exigences legales du cabinet.

- Package `spatie/laravel-activitylog` · table `activity_log` (causer, sujet, evenement, diff des champs)
- Modeles traces : `Facture`, `Avoir`, `Paiement`, `Devis`, `Entreprise`, `User`, `Setting` (trait `LogsActivity`, `logOnlyDirty`)
- Securite : le `password` et le `remember_token` ne sont jamais journalises (`logExcept`)
- Capture automatique de l'utilisateur connecte (causer) et du diff avant/apres
- Lecture seule, **admin uniquement** : `GET /api/v1/audit-logs` paginee + filtres (entite, action, periode)
- Frontend : page `Journal d'audit` (DataTable, filtres, dialog detail du diff) sous Administration · RGAA
- Complement de l'audit OWASP A09 (US-36) qui ne loggait que les erreurs
- Depend de : **US-36**

---

### US-45 · Droits collaborateur — isolation missions/taches · M · 3 pts · Sprint 3 ✅

**En tant que collaborateur**, je veux n'avoir acces qu'aux missions auxquelles je suis affecte et ne pouvoir modifier que mes propres taches **afin de** respecter le principe de moindre privilege.

- Routes restructurees en 3 groupes Spatie (`role:admin`, `role:admin|secretaire`, tous backoffice) — collaborateur bloque sur facturation, entreprises, referentiels en ecriture
- Missions : visibles si membre (`mission_user`) **ou** assigne a une tache de la mission — `MissionPolicy::view` + `MissionService::listerMissions(User)` (`whereHas('collaborateurs')` **OR** `whereHas('taches', assigned_to)`)
- Missions du collaborateur : les missions **en cours** sont priorisees en tete de liste (tri statut puis date)
- Missions : modification/creation/suppression reservees a admin/secretaire
- Taches : lecture de toutes les taches de la mission autorisee — modification reservee a `assigned_to === user->id` (statut uniquement) via `TachePolicy`
- Taches : creation/suppression reservees a admin/secretaire
- Calendrier : filtre automatique par `collaborateur_id` cote serveur — chaque collaborateur ne voit que ses evenements
- Commentaires : creation autorisee si acces a la mission — modification/suppression reservees a l'auteur ou admin
- `visible_portail` sur `tache_commentaires` : prepare le rapport de cloture (US-35) et le partage client depuis le portail
- Frontend : interface commentaires integree dans `MissionDetailPage` (expandable par tache), guards role complets, panel bienvenue collaborateur sur dashboard, menus/boutons conditionnes par role
- RGAA : `aria-labelledby`, `role="status"`, `aria-expanded`, `aria-live` sur tous les nouveaux elements
- 🔄 **Revise par fix/correctifs-planning** : l'isolation est durcie — un collaborateur ne **voit que ses propres taches** (et non plus toutes celles de la mission : `Tache::scopeVisiblePour` + `TachePolicy::view`, **403** sinon) et ne peut **commenter** qu'une tache qui lui est **affectee** (et non plus toute tache de la mission accessible). L'immutabilite des commentaires d'autrui reste inchangee.
- Depend de : **US-18, US-19, US-22**

---

### US-41 · Protections de suppression · S · 3 pts · Sprint 3 ✅

**En tant que developpeur**, je veux mettre en place des protections de suppression sur les entites liees **afin d'** eviter la destruction accidentelle de donnees critiques.

- ✅ Entreprise bloquee si devis ou missions associes (HTTP 409)
- ✅ Mission bloquee si factures associees (HTTP 409)
- ✅ Facture bloquee si paiements ou avoirs associes (HTTP 409)
- ✅ Tache bloquee si commentaires associes (HTTP 409) — `TacheController::destroy`
- ✅ Frontend affiche le message d'erreur via toast (MissionDetailPage)
- Depend de : **US-11, US-13, US-15, US-18, US-22**

---

### US-42 · Dashboard collaborateur · S · 3 pts · Sprint 3 ✅

**En tant que collaborateur**, je veux un dashboard dedie affichant mes missions et mes KPI personnels **afin d'** avoir une vue de mon propre perimetre.

- Missions assignees · taux de completion des taches
- Distinct du dashboard Admin global
- Depend de : **US-33, US-20**

---

### US-50 · Dashboard secrétaire · M · 3 pts · Sprint 3 ✅

**En tant que secrétaire**, je veux un dashboard dédié et **orienté action** (facturation + recouvrement) **afin de** voir d'un coup d'œil ce que je dois faire et le traiter en un clic.

- **Worklist « À faire »** : actions priorisées (factures en retard, relances à envoyer, devis expirant / en attente / à convertir) avec lien direct vers la page concernée
- KPI recouvrement : impayé total (avoirs déduits), clients débiteurs, factures en retard, relances dues
- KPI facturation : devis en attente (count + montant), encaissements du mois, factures émises ce mois (delta N-1)
- Graphiques (SVG/CSS, sans dépendance) : aging créances 15–29 / 30–59 / 60+ j · donut relances N1/N2/N3 · comparatif factures N vs N-1
- Top 5 débiteurs (barres) · créances urgentes · liens rapides `/creances`, `/factures`, `/devis`
- Refonte graphique « Ledger Edition », **dark mode** complet, **RGAA** (worklist en liste de liens, charts `role="img"`, `prefers-reduced-motion`)
- Endpoint `GET /api/v1/stats/secretaire` (rôle secrétaire uniquement), distinct du dashboard admin (`GET /api/v1/stats`)
- 🔄 **Recadrage périmètre (feature/perimetre-secretaire)** : la secrétaire ne fait plus de **production de facturation**. Périmètre = **CRUD entreprises (sans suppression)** + **créances/recouvrement** (consulter, relancer, enregistrer les paiements) + **envoi des devis** et **transmission des factures (PDF)**. Création/suppression de devis/factures/avoirs et cycle de vie devis (accepter/refuser/convertir) réservés à l'admin. Dashboard secrétaire recentré sur le recouvrement (volet facturation/production retiré : devis en attente / émission de factures)
- 🔄 **Hors Missions & Planning (feature/secretaire-hors-missions-planning)** : la secrétaire n'a **plus accès** aux Missions ni au Planning (menu, routeur, API `role:admin|collaborateur`, `MissionPolicy`/`TachePolicy`). L'onglet Missions de la fiche entreprise est masqué pour elle.
- Depend de : **US-13, US-27**

---

### US-48 · Entreprises — création/modification secrétaire · S · 2 pts · Sprint 3 ✅

**En tant que secrétaire**, je veux créer et modifier des entreprises **afin de** gérer les fiches clients sans passer par l'administrateur.

- Policy `create`/`update` : admin + secrétaire
- Suppression et activation portail : admin uniquement
- UI : bouton « Nouvelle entreprise » et édition visibles ; portail/suppression masqués pour la secrétaire
- Depend de : **US-06**

---

### US-49 · Garde router par rôle · S · 2 pts · Sprint 3 ✅

**En tant qu'utilisateur back-office**, je veux être redirigé proprement si j'accède à une page hors périmètre **afin de** ne pas voir un écran vide ou une erreur API silencieuse.

- `meta.roles` sur les routes Vue Router (admin / admin+secrétaire / tous staff)
- Page `/acces-refuse` avec message clair et retour dashboard
- Menu aligné sur les routes (config relances admin only)
- Depend de : **US-45**

---

### US-46 · Rapport de fin de mission PDF · S · 3 pts · Sprint 3 ✅

**En tant qu'administrateur ou client**, je veux générer un rapport PDF de fin de mission **afin d'** avoir un récapitulatif complet des travaux effectués avec les commentaires associés.

- DomPDF · en-tête cabinet · informations mission (client, prestation, dates, prix HT)
- Tâches listées avec statut, assigné, priorité, échéance
- Commentaires par tâche : auteur + date + contenu
- Mode portail : uniquement les commentaires avec `visible_portail = true` · section facturation masquée
- Accessible via `GET /api/v1/missions/{id}/rapport/pdf` (admin + secrétaire)
- Accessible via `GET /api/v1/portail/missions/{id}/rapport/pdf` (client portail)
- Bouton dans la section Documents de MissionDetailPage · bouton dans le dialog détail portail
- Depend de : **US-18, US-22, US-29**

---

### US-44 · Envoi devis (et factures) par mail · C · 3 pts · Apres Sprint 3 ✅

**En tant qu'administrateur/secretaire**, je veux envoyer le devis (et transmettre la facture) par mail au contact principal de l'entreprise **afin que** le client le recoive directement sans manipulation manuelle.

- Bouton "Envoyer" (devis) / "Transmettre" (facture) declenche un `Mailable` Laravel avec le PDF en piece jointe
- Mail envoye au contact principal de l'entreprise (a defaut email entreprise)
- Devis : statut passe automatiquement a `envoye` apres envoi confirme ; refus 409 si pas d'email
- Transmission facture autorisee a la secretaire (`FacturePolicy::transmettre`)
- Fournisseur configurable par `.env` (Mailpit en dev, Brevo en demo)
- Depend de : **US-11, US-12** (PDF devis obligatoire)

---

## Resume

### Par sprint

| Sprint | US | Pts | Contenu principal |
|---|---|---|---|
| Sprint 1 | US-01 ✅, 02 ✅, 03 ✅, 04 ✅, 05 ✅, 06 ✅, 07 ✅, 08 ✅, 09 ✅, 11 ✅, 12 ✅, 17 ✅, 18 ✅, 40 ✅, 43 ✅ | 49 pts | Auth · Referentiel · Clients · Devis · Mission · Exercice |
| Sprint 2 | US-10 ✅, 13 ✅, 14 ✅, 15 ✅, 16 ✅, 19 ✅, 20 ✅, 21 ✅, 22 ✅, 23 ✅, 24 ✅, 25 ✅, 26 ✅, 27 ✅, 28 ✅, 29 ✅, 30 ✅, 31 ✅, 32 ✅, 33 ✅, 34 ✅ | 96 pts | Factures · Taches · Portail · Relances · KPI |
| Sprint 3 | US-35 ✅, 36 ✅, 37 ✅, 38 ✅, 39 ✅, 41 ✅, 42 ✅, 45 ✅, 46 ✅, 47 ✅, 48 ✅, 49 ✅, 50 ✅ · **US-51 🔧** | 43 pts | Qualite · OWASP · RGAA · Tests · MCO · Droits · PDF mission · Dashboard secrétaire · Settings TVA |
| **Total** | **46 US** | **179 pts** | |

### Par priorite MoSCoW

| Priorite | US | Pts |
|---|---|---|
| Must Have | 30 US | 128 pts |
| Should Have | 14 US | 45 pts |
| Could Have | 2 US | 6 pts |

### Avancement

| Statut | US | Pts |
|---|---|---|
| ✅ Termine | 44 US | 159 pts |
| 🔧 En cours | 0 US | 0 pts |
| A faire | 2 US | 20 pts |

### Noeuds critiques

| US | Raison |
|---|---|
| **US-18 Mission** ⭐ ✅ | Debloque 11 US en aval (taches, factures, calendrier, portail, KPI, relances) |
| **US-13 Facture** ⭐ ✅ | Debloque 9 US en aval (PDF DGI, paiements, avoirs, relances, portail, KPI) |

> Sprint 3 terminé : US-35 ✅, US-36 ✅, US-37 ✅, US-38 ✅, US-39 ✅, US-41 ✅, US-42 ✅, US-45 ✅, US-46 ✅, US-47 ✅, US-48 ✅, US-49 ✅, US-50 ✅.

---

### Competences RNCP couvertes

| Competence | US |
|---|---|
| C2.2.2 ★ Tests unitaires | US-38 |
| C2.2.3 ★ Securite OWASP + RGAA | US-36, US-37 |
| C4.1.2 ★ Supervision & alertes | US-39 |
| C4.3.2 ★ Journal des versions | US-40 ✅ |
