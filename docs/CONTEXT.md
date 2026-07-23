# Ledge — Contexte Projet

> Derniere mise a jour : 23 Juillet 2026 — Architecture N-tier (Vue.js + Laravel API)
> RNCP 39583 - Expert en Developpement Logiciel - YNOV

---

## Identite

| | |
|---|---|
| **Nom** | Ledge |
| **Type** | Systeme de gestion integre pour cabinets de conseil / comptabilite |
| **Marche cible** | Algerie — cabinet pilote en premier, extensible nationalement |
| **Contexte** | Le cabinet ne dispose d'aucun outil numerique centralise. Gestion sur Excel / papier -> pertes d'information, erreurs de facturation, relances oubliees, aucune tracabilite. Ledge remplace tout ca. |
| **Deadline** | Juillet 2026 — MVP complet + tous les livrables RNCP |

---

## Stack Technique

| Couche | Choix | Version |
|---|---|---|
| **Architecture** | N-tier 3 couches (presentation / metier / donnees) | — |
| **Frontend** | Vue 3 + TypeScript + PrimeVue + Pinia + Vue Router | Vue 3.5 / PrimeVue 4 |
| **Backend** | Laravel (API REST) + Sanctum + PHP | Laravel 12 / PHP 8.3 |
| **BDD** | MySQL | 8.0 (Docker demo / production) — 9.1 en dev local WAMP |
| **Auth** | Laravel Sanctum (SPA cookie-based) | v4.3 |
| **Permissions** | Spatie Laravel Permission | v7.2 |
| **PDF** | DomPDF | v3.1 |
| **Queue / Cache** | Database driver (WAMP local) / Redis (prod) | — |
| **Stockage docs** | Disque local + compatible S3 | PDF factures, documents cabinet |
| **Serveur cible** | Nginx + PHP-FPM | VPS Linux Ubuntu 22 LTS |
| **CI/CD** | GitHub Actions | Deploiement automatise |
| **Dev tooling** | WAMP (Windows) + VS Code | Environnement local |

> **Dev local :** WAMP sur Windows. Fix MySQL 9 requis : `ROW_FORMAT=DYNAMIC` dans `config/database.php`.

---

## Structure du projet

```
Ledge/
├── backend/              # Laravel 12 — API REST
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/   # Par domaine (Auth/, Entreprises/, Facturation/...)
│   │   │   ├── Requests/      # FormRequests par domaine
│   │   │   ├── Resources/     # API Resources JSON par domaine
│   │   │   └── Middleware/    # EnsureBackofficeAccess, EnsurePortailAccess
│   │   ├── Models/            # 19 modeles Eloquent
│   │   └── Providers/
│   ├── routes/api.php         # Toutes les routes API /api/v1/*
│   ├── database/              # Migrations + seeders
│   └── tests/
├── frontend/             # Vue 3 + TypeScript + PrimeVue
│   ├── src/
│   │   ├── api/           # Client Axios avec intercepteurs CSRF
│   │   ├── assets/        # CSS mobile-first + RGAA
│   │   ├── layouts/       # AdminLayout, PortailLayout
│   │   ├── pages/         # Pages par domaine
│   │   ├── router/        # Vue Router avec guards par role
│   │   ├── stores/        # Pinia stores (auth, etc.)
│   │   └── types/         # Interfaces TypeScript
│   └── package.json
├── .github/              # PR template RNCP, GitHub Actions
├── docs/                 # Documentation projet
├── CHANGELOG.md
└── README.md
```

---

## Acces Local (Developpement)

| Service | URL | Commande |
|---|---|---|
| **Backend API** | `http://localhost:8000/api/v1/*` | `cd backend && php artisan serve` |
| **Frontend** | `http://localhost:5173` | `cd frontend && npm run dev` |
| **Stack Docker (demo/jury)** | `http://localhost:5173` | `docker compose up --build` (racine) — voir [MANUEL-DEPLOIEMENT.md](MANUEL-DEPLOIEMENT.md) |

**Compte admin :** `admin@ledge.dz` / valeur de `ADMIN_PASSWORD` (variable d'environnement,
jamais versionnee — defaut de demonstration Docker documente dans le manuel de deploiement).

---

## Flux Metier Global

```
Entreprise (prospect)
    |
Devis (exercice + prestation + total calcule)
    |
Mission (dates debut/fin - statut - total - calendrier)
    |-- Mandat (document officiel, genere auto)
    |-- Convention (contrat signe, genere auto)
    |-- Taches (assignees collaborateurs - commentaires internes)
    +-- Factures (FF facture | FA avoir)
            +-- Paiements (Cheque / Virement / Espece)
                    +-- Relances (auto J+15/J+30/J+60 ou manuelles)
```

---

## Modules Metier

### Core / Settings
Auth, roles et permissions (Spatie Laravel Permission), **parametres globaux configurables sans code** :
- Taux TVA avec **historique versionne** (date d'entree en vigueur)
- Coordonnees cabinet (nom, adresse, NIF, NIS, RIB, logo)
- Numerotation factures (prefixe, format annuel ou sequentiel)
- Delais de relance (J+X par niveau)
- Modeles mails de relance (templates personnalisables)
- Devise (DA par defaut)
- KPI — seuils d'alerte et periodes de calcul
- Grille tarifaire : tarifs de base prestations, indices regime fiscal, indices categorie

**Roles applicatifs** : `admin` - `collaborateur` - `secretaire` - `client`

**Regle d'affectation** : l'utilisateur s'inscrit sans role. L'Admin affecte le role manuellement. Aucun acces fonctionnel sans role affecte.

**Colonne `users.entreprise_id`** : nullable — renseignee **uniquement** pour le role `client`. Les users `admin`, `collaborateur`, `secretaire` ont cette colonne a `NULL`. C'est via cette FK que le portail client sait quelles factures et documents afficher, et que le scope Eloquent isole les donnees par entreprise.

| Fonctionnalite | Admin | Collaborateur | Secretaire | Client |
|---|:---:|:---:|:---:|:---:|
| Parametrage / TVA / tarifs / exercices | oui | - | - | - |
| Gestion utilisateurs & affectation roles | oui | - | - | - |
| KPI global + performance collaborateurs | oui | - | - | - |
| Clients / Prospects (CRUD) | oui | - | - | - |
| Devis & Facturation (creer, emettre, PDF) | oui | - | - | - |
| Missions & Planning global | oui | - | - | - |
| Taches — voir ses taches assignees | oui | oui | - | - |
| Taches — changer le statut | oui | oui | - | - |
| Taches — ajouter un commentaire | oui | oui | - | - |
| Taches — modifier/supprimer son commentaire | oui | oui (siens) | - | - |
| Consulter les creances | oui | - | oui | - |
| Relance manuelle (bouton) | oui | - | oui | - |
| Relance automatique (config queue) | oui | - | - | - |
| Portail client (ses factures / docs) | - | - | - | oui |

---

### Clients / Dossiers
Fiche entreprise avec **deux statuts distincts** :
- **Prospect** : entreprise ayant demande un devis mais sans mission en cours. Peut avoir un ou plusieurs devis. Aucune facture.
- **Client** : entreprise ayant au moins une mission active ou passee.

**Bascule automatique** : Observer `MissionCreated` — quand une mission est creee pour une entreprise prospect, elle passe automatiquement en statut `client`.

**Contacts multiples** par entreprise avec designation du contact principal.

Donnees : contacts, NIF/NIS, numero RC, article d'imposition, regime fiscal, categorie (TPE/PME/GE), secteur d'activite, historique complet des missions et factures.

---

### Facturation
Devis, factures, avoirs. Separation obligatoire par **exercice fiscal (annee)**. Calcul automatique TVA. Generation PDF conforme DGI. Logs immuables (piste d'audit).

**Calcul du prix HT d'une mission :**
```
Prix HT = prestation.tarif_initial x regime_fiscal.indice x categorie.indice
```

| Composant | Exemples |
|---|---|
| `tarif_initial` (prestation) | CAC = 300 000 DA - ACMPT = 120 000 DA - AENT = 80 000 DA |
| `indice` regime fiscal | Forfait = x1.0 - Reel = x1.5 |
| `indice` categorie | TPE = x1.0 - PME = x1.75 - GE = x2.0 |

Exemple : ACMPT pour une PME au regime Reel -> `120 000 x 1.5 x 1.75 = 315 000 DA HT`

**Calcul TVA — devis :**
```
TTC devis = Prix HT + (Prix HT x taux_tva)
```

**Calcul TVA — facture :**
```
Montant TVA    = Prix HT x taux_tva_en_vigueur_a_la_date_de_facture
Prix TTC       = Prix HT + Montant TVA
```

> Tous les indices et tarifs de base sont **parametrables via Settings** — aucun redeploiement pour ajuster la grille tarifaire.

**Tranches de facturation :**
```
Tranche 1 = 30% du total mission
Tranche 2 = 30% du total mission
Tranche 3 = 40% du total mission (solde)
```

**Statut facture** recalcule automatiquement : `en_attente -> partiel -> solde`

**Snapshots immuables** : le taux TVA est fige a la creation de la facture (pas de recalcul retroactif).

**Logs immuables** (piste d'audit) sur toutes les transactions financieres.

**PDF devis** : montant TTC en lettres (« Arrêté le présent devis à la somme de ... Dinars Algériens ») via `NumberFormatter` locale `fr`.
**PDF facture** : generation conforme DGI avec montant en lettres — a implementer (US-14).

---

### Planning
FullCalendar (Vue.js), missions, taches, assignation collaborateurs, commentaires internes, drag & drop.

**Statuts taches** (4) : `a_faire`, `en_cours`, `termine`, `bloque`

**Documents generes par mission** : mandat + convention (generation automatique a la creation).

---

### Relances / Mails
Deux modes de relance :
- **Automatique** : regles parametrables (J+15, J+30, J+60) executees via queue Laravel (cron quotidien). Aucune intervention manuelle necessaire.
- **Manuelle** : bouton "Envoyer la relance" accessible a l'Admin et a la Secretaire uniquement.
- **Templates mails** personnalisables avec variables : `{{client}}`, `{{montant}}`, `{{echeance}}`
- **Observer `InvoicePaid`** : annulation automatique des relances en cours des qu'un paiement solde la facture.

Suivi statut paiement par facture (en_attente / partiel / solde). Journal des relances envoyees.

---

### Portail Client
Routes Vue.js separees (`/portail`) — role `client` uniquement, lecture seule.

**Le client ne s'inscrit jamais lui-meme.** Flux d'acces :
1. Admin ouvre la fiche Entreprise (statut = `client`)
2. Admin clique **"Activer l'acces portail"**
3. Ledge cree automatiquement un `User` avec `entreprise_id` renseigne et le role `client`
4. Email envoye au contact principal avec lien de definition de mot de passe
5. Le client definit son mot de passe et accede au portail
6. L'Admin peut revoquer l'acces a tout moment via `portail_actif = 0`

Le client accede 24h/24 a ses factures filtrees par exercice fiscal, telecharge ses documents, consulte l'historique de ses missions. Le scope Eloquent garantit qu'il ne voit que les donnees de **son** entreprise.

---

### KPI / Reporting
Performance personnel : dossiers traites, taux de recouvrement, delais moyens, nombre de relances necessaires avant paiement. Objectifs vs realise parametrables par role. Alertes si KPI sous seuil.

**Dashboards par role :**
- **Admin** : CA mensuel (graphique), missions en cours/achevees, CA annuel, taches en cours, factures impayees
- **Secretaire** : total impayees, retard 15-30j, retard >30j
- **Collaborateur** : ses taches en cours / achevees

---

### Documents
Generation PDF (DomPDF), stockage, versioning, partage via portail client.

---

## Regles Metier Critiques

### TVA — Historisation obligatoire

La TVA change avec chaque loi de finances. Il faut **toujours retrouver le taux en vigueur a la date de la facture**, meme des annees plus tard.

```
tva_rates
|-- id
|-- taux          (ex: 19.00)
|-- designation   (ex: "TVA standard LF 2024")
|-- date_debut    (ex: 2024-01-01)
|-- date_fin      (ex: 2027-12-31 — NULL si encore en vigueur)
+-- type          (standard | reduit | exonere)
```

**Regle de calcul** : pour une facture datee du 15/03/2026, appliquer le taux dont `date_debut <= 2026-03-15 <= date_fin`.

```php
// Toujours utiliser cette methode, jamais un taux en dur
$tva = TvaRate::enVigueurLe($facture->date_facture);
```

Exemple concret :
- TVA 19% en vigueur en 2026 -> facture 2026 = 19%
- TVA passe a 29% en 2028 -> facture 2028 = 29%, mais facture 2026 **reste a 19%**

### Exercices Fiscaux — Separation stricte
Numerotation reinitialisee chaque annee : `FF2026-001`, `FF2027-001`...
Recherche et filtres toujours contextuels a un exercice. Le portail client affiche les factures par exercice. Les KPI sont calcules par exercice.

### Protection suppression

| Entite | Condition de blocage |
|---|---|
| Entreprise | Si devis ou missions associes |
| Mission | Si factures associees |
| Facture | Si paiements ou avoirs associes |
| Tache | Si commentaires associes |

### Documents legaux
- **Mandats** : generes par mission, numerotation `MD{yy}-XXX`, PDF
- **Conventions** : generes par mission, numerotation `CV{yy}-XXX`, PDF

### Events Laravel (decouplage)
- `MissionCreated` -> bascule prospect -> client
- `InvoicePaid` -> annule relances en cours
- `FiscalYearClosed` -> archive les documents de l'exercice

---

## Types de prestations (donnees reelles du cabinet)

| Code | Designation | Tarif base | Duree |
|---|---|---|---|
| CAC | Audit Legal (Commissariat aux comptes) | 300 000 DA | 36 mois |
| ACMPT | Assistance Comptable | 120 000 DA | 12 mois |
| AENT | Accompagnement d'entreprise | 80 000 DA | 12 mois |
| ASSC | Assainissement de la comptabilite | 100 000 DA | 12 mois |
| A&C | Audit et conseil | 110 000 DA | 12 mois |

---

## Ce que Ledge apporte au cabinet (vs gestion Excel / papier)

| Probleme identifie | Solution dans Ledge |
|---|---|
| TVA calculee a la main, sources d'erreurs | Calcul auto + historisation des taux |
| Aucun suivi du statut des paiements | Statut auto + relances automatiques |
| Aucun acces client a ses documents | Module Portail (Vue.js `/portail`) |
| Aucune gestion des droits | Spatie Laravel Permission (granulaire) |
| Aucun indicateur de pilotage | Module KPI/Reporting + Statistiques |
| Pas de separation par exercice | Exercices fiscaux + numerotation annuelle |
| Aucune supervision / MCO | UptimeRobot + Sentry + Laravel Health |
| Accessibilite absente des outils bureautiques | RGAA integre des le dev |
| Saisies dispersees (classeurs, papier) | SPA Vue 3 + PrimeVue centralisee |
| Aucun controle qualite | PHPUnit + tests composants Vue |

---

## Specificites Reglementaires Algeriennes

| Regle | Detail |
|---|---|
| TVA standard | 19% — LF 2023, art. 21 (historise) |
| TVA reduite | 9% — services exoneres, art. 23 (historise) |
| Mentions obligatoires facture | NIF + NIS + RC + Art. imposition, numero chronologique, date |
| Format DGI | Factures conformes Direction Generale des Impots |
| Facturation electronique | Projet de loi en cours — architecture prete |
| Piste d'audit | Logs immuables sur toutes les transactions financieres |
| Exercice fiscal | Annee civile (janvier -> decembre) |

---

## Schema BDD — Tables principales

```
users                  -> auth + roles Spatie (entreprise_id nullable, portail_actif)
entreprises            -> clients & prospects (statut, regime, categorie)
exercices              -> exercices fiscaux par annee
tva_rates              -> historique taux TVA (date_debut / date_fin)
settings               -> parametres cle/valeur (cabinet, facturation, relances)
prestations            -> catalogue avec tarif_initial
regimes_fiscaux        -> Forfait (x1.0) / Reel (x1.5)
categories_entreprise  -> TPE (x1.0) / PME (x1.75) / GE (x2.0)
missions               -> missions par entreprise + exercice + prestation
mission_user           -> affectation collaborateurs aux missions
taches                 -> taches par mission, assignees a un collaborateur
tache_commentaires     -> commentaires sur taches
devis                  -> devis par entreprise + exercice (une prestation unique, prix_ht calcule a la creation)
factures               -> FF (facture) / FA (avoir), TVA historisee
facture_lignes         -> lignes de facture
paiements              -> paiements recus par facture
relances               -> journal des relances (auto + manuelles)
documents              -> fichiers PDF et documents partages portail
```

---

## Decisions Architecturales Cles

**Architecture N-tier retenue** — separation claire entre :
- **Tier 1 (Presentation)** : Vue 3 + PrimeVue — SPA accessible RGAA, responsive mobile-first
- **Tier 2 (Metier)** : Laravel API REST + Sanctum — controllers organises par domaine
- **Tier 3 (Donnees)** : MySQL via Eloquent ORM

**Organisation backend classique avec sous-dossiers par domaine** — pas de modules avec ServiceProviders separes.

**Sanctum SPA mode** — authentification cookie-based (pas de tokens Bearer) pour le meme domaine.

**Event Bus Laravel** — decouplage metier via Events/Observers (`InvoicePaid`, `MissionCreated`, `FiscalYearClosed`).

**`users.entreprise_id` nullable** — `NULL` pour admin/collaborateur/secretaire, renseigne uniquement pour le role `client`. Isolation automatique des donnees dans le portail via scope Eloquent.

**Table `tva_rates` versionnee** — taux TVA historises avec date d'entree en vigueur. Aucun redeploiement pour les mises a jour reglementaires.

**Table `settings` cle/valeur** — parametres metier en base, modifiables par l'admin sans code.

**Separation par exercice fiscal** — toute la facturation est cloisonnee par annee. Numerotation reinitialisee chaque 1er janvier.

---

## Exigences Non-Fonctionnelles

### Accessibilite RGAA (C2.2.3)

| Critere | Implementation |
|---|---|
| Contraste couleurs (AA min 4.5:1) | Theme PrimeVue Aura valide |
| Navigation clavier | Tab/Enter/Esc sur tous les composants |
| Labels formulaires | `<label>` ou `aria-label` sur chaque input |
| Messages d'erreur | `aria-live` ou `role="alert"` |
| Images decoratives | `alt=""` sur les images non informatives |
| Focus visible | `outline` CSS sur `:focus-visible` |
| Skip link | "Aller au contenu principal" sur chaque page |
| Titres hierarchiques | Structure `h1 > h2 > h3` coherente |
| Liens explicites | Pas de "cliquez ici" — textes de liens descriptifs |

**Outils de test :** axe DevTools, WAVE, Lighthouse, test clavier manuel.

> A documenter dans le **cahier de recettes** (C2.3.1) avec captures d'ecran des scores Lighthouse.

### Securite OWASP

| Regle | Implementation |
|---|---|
| A01 — Broken Access Control | Middlewares par role, Policies Laravel |
| A03 — Injection | FormRequests obligatoires, jamais de `DB::raw()` avec input |
| A07 — XSS | Sanitisation Vue.js (pas de `v-html` avec donnees utilisateur) |
| CSRF | Sanctum cookie + CSRF token automatique |

### Responsive Mobile-First
- Breakpoints : 768px (tablette), 1024px (desktop)
- Layout sidebar desktop, hamburger mobile
- DataTable responsive avec scroll horizontal

---

## Strategie Git — Gitflow 5 Phases RNCP

Voir [docs/GITFLOW.md](GITFLOW.md) pour le detail complet.

---

## Bloc 4 — MCO & Supervision

| Outil | Usage | Competence |
|---|---|---|
| UptimeRobot (gratuit) | Ping HTTP toutes les 5 min, alerte mail/SMS si down | C4.1.2 |
| Laravel Health (spatie) | Endpoint `/health` (BDD, stockage, queue) | C4.1.2 |
| Sentry (free tier) | Remontee automatique des erreurs PHP avec contexte | C4.2.1 |
| Laravel Log + rotation | Fichiers logs quotidiens `storage/logs/laravel-YYYY-MM-DD.log` | C4.2.1 |
| GitHub Releases | Journal des versions + CHANGELOG | C4.3.2 |

### Alertes automatiques prevues
- Factures impayees au-dela du delai parametre -> relance + alerte manager
- KPI collaborateur sous seuil -> alerte responsable
- Espace disque > 80% -> alerte admin
- Echec de job queue (mail non envoye) -> retry + log
- Erreur 500 -> Sentry + log

---

## RNCP 39583 — Suivi des Competences

> Regle : 50% minimum par bloc + **toutes les obligatoires**

### Bloc 1 — Cadrer le projet (Termine)

| Competence | Obligatoire | Statut |
|---|---|---|
| C1.1.1 Cartographie parties prenantes | oui | fait |
| C1.1.2 Analyse de la demande | | fait |
| C1.2.1 SWOT | | fait |
| C1.2.2 Faisabilite technique | oui | fait |
| C1.2.3 Cartographie des risques | | fait |
| C1.3.1 Veille technologique & reglementaire | | fait |
| C1.3.2 Comparatif solutions techniques | oui | fait |
| C1.4.1 Charge de travail | oui | fait |
| C1.4.2 Budget previsionnel | | fait |
| C1.5 Modelisation architecture | | fait |
| C1.6 Preconisation client | oui | fait |

### Bloc 2 — Concevoir & Developper (En cours)

| Competence | Obligatoire | Statut | Notes |
|---|---|---|---|
| C2.1.1 Environnement deploiement / tests | | en cours | WAMP local + staging VPS |
| C2.1.2 Integration continue | | en cours | GitHub Actions |
| C2.2.1 Prototype fonctionnel | oui | en cours | MVP Ledge |
| C2.2.2 Tests unitaires | oui | en cours | PHPUnit — Facturation et KPI en priorite |
| C2.2.3 Securite OWASP & accessibilite RGAA | oui | en cours | axe + Lighthouse |
| C2.2.4 Deploiement progressif | | en cours | |
| C2.3.1 Cahier de recettes | oui | en cours | Inclut scores Lighthouse accessibilite |
| C2.3.2 Plan de correction des bugs | | en cours | |
| C2.4.1 Documentation technique | | en cours | README + doc API |

### Bloc 3 — Piloter le projet (A faire)

| Competence | Obligatoire | Statut | Notes |
|---|---|---|---|
| C3.1 Planning / methodologie | oui | en cours | Gantt 12 semaines |
| C3.2.1 Suivi avancement & indicateurs | oui | en cours | Burndown par sprint |
| C3.2.2 Arbitrages | | en cours | |
| C3.3.1 Management equipe | | en cours | |
| C3.3.2 Besoins en competences | | en cours | |
| C3.4.1 Comptes rendus client | | en cours | |
| C3.4.2 Demonstration live | oui | en cours | **CRITIQUE — app doit tourner en live** |

### Bloc 4 — MCO (A faire)

| Competence | Obligatoire | Statut | Notes |
|---|---|---|---|
| C4.1.1 Mises a jour dependances | | fait | Audits CI bloquants + remediations documentees (Guzzle, dompdf — voir SECURITY.md) |
| C4.1.2 Supervision & alertes | oui | en cours | UptimeRobot + Laravel Health + Sentry |
| C4.2.1 Consignation anomalies | oui | en cours | Sentry + Laravel logs rotatifs |
| C4.2.2 Correctif CI/CD | | fait | Pipeline GitHub Actions (lint, tests, audits, E2E) |
| C4.3.1 Axes d'amelioration | | en cours | Retour utilisateurs post-MVP |
| C4.3.2 Journal des versions | oui | fait | CHANGELOG.md + release v1.0.0 taguee (GitHub Release, SemVer) |
| C4.3.3 Collaboration support client | | en cours | Guide utilisateur + procedure d'escalade |

---

## Planning — 12 Semaines

| Semaines | Phase | Contenu | Statut |
|---|---|---|---|
| S1-S2 | Cadrage & Bloc 1 | Dossier de cadrage, SWOT, comparatif, charge, budget, architecture | fait |
| S3-S4 | Architecture & Setup | Schema BDD, migrations, doc technique, Gantt | fait |
| S5-S6 | Sprint 1 — Core | Auth/roles, Clients, Facturation (calcul HT/TVA/PDF devis), Settings, Exercices | fait |
| S7-S8 | Sprint 2 — Avance | Planning calendrier, Relances mails, Portail client, KPI | fait |
| S9 | Sprint 3 — Qualite | OWASP Top 10, RGAA (axe + Lighthouse), tests unitaires | fait |
| S10-S11 | Recette & MCO | Cahier de recettes, anomalies, CHANGELOG, supervision | fait |
| S12 | Soutenance | Slides Blocs 2/3/4, repetition demo live (C3.4.2), argumentation jury | en preparation |
