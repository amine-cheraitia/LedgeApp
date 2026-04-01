# CHANGELOG — Ledge

> Format : [Semantic Versioning](https://semver.org) — `MAJOR.MINOR.PATCH`
> - **MAJOR** : rupture de compatibilite (migration BDD obligatoire)
> - **MINOR** : nouvelle fonctionnalite retro-compatible
> - **PATCH** : correctif sans impact sur le schema

---

## [Unreleased]

### Ajouts — Missions : tâches complètes + statuts mission (US-19)

#### Backend
- **`UpdateTacheRequest`** (nouveau) : FormRequest dédié à la mise à jour des tâches — `titre` en `sometimes` (optionnel) permettant la mise à jour partielle du statut seul sans re-valider tous les champs
- **`TacheController::update()`** : injecte désormais `UpdateTacheRequest` au lieu de `StoreTacheRequest` — SRP respecté (store et update ont des règles différentes)

#### Frontend
- **`MissionListPage.vue`** : ajout `MultiSelect` collaborateurs dans le dialog de création de mission
- **`MissionDetailPage.vue`** : refonte complète —
  - Boutons de changement de statut mission (Reprendre / Suspendre / Terminer / Annuler) selon le statut courant
  - Collaborateurs affichés en chips dans le bloc info
  - Sélecteur `assigné à` (Select user) dans le dialog nouvelle tâche
  - Bouton supprimer tâche avec `ConfirmDialog` + gestion erreur 409 (tâche avec commentaires)
  - Cohérence des tags de statut tâche

---

### Corrections — PDF devis : timbre retiré + montant en lettres

#### Backend
- **`FacturationService::creerDevis()`** : `montant_timbre` forcé à 0 — le timbre fiscal s'applique uniquement sur les factures, pas sur les devis
- **`PdfService::montantEnLettres()`** : nouvelle méthode — convertit un montant float en lettres françaises via `NumberFormatter` (PHP intl, locale `fr`), suffixe « Dinars Algériens »
- **Test** `test_tva_calcule_sur_prix_ht_sans_timbre` : assertions mises à jour (`montant_timbre = 0`, TTC = HT + TVA)

#### Frontend (PDF)
- **`pdf/devis.blade.php`** : colonne Timbre et ligne "Timbre fiscal" supprimées du tableau et des totaux
- Bandeau *Arrêté le présent devis à la somme de **[montant en lettres]*** ajouté sous le bloc TOTAL TTC

---

### Ajouts — PDF devis (US-12)

#### Backend
- **PdfService** : service dédié DomPDF — `genererDevis()` charge les données cabinet (`Setting`) et rend la vue Blade ; conçu pour accueillir `genererFacture()` (US-14)
- **Vue Blade `pdf/devis.blade.php`** : template A4 portrait — en-tête cabinet (nom/NIF/NIS/RIB/adresse/tél), bloc destinataire (raison sociale/NIF/NIS/RC), tableau prestation (code, désignation, prix HT, TVA, timbre), récapitulatif totaux, zone double signature avec mention « Bon pour accord »
- **Route** : `GET /api/v1/devis/{id}/pdf` — streame le PDF directement au client
- **DevisController::pdf()** : délègue à `PdfService`, retourne un stream `application/pdf`
- **Test** : `test_pdf_devis_retourne_un_pdf` — vérifie HTTP 200 + Content-Type `application/pdf`

#### Frontend
- **`api/modules/devis.ts`** : ajout `getPdf(id)` avec `responseType: 'blob'`
- **`composables/useDevis.ts`** : ajout `telechargerPdf(id, numero)` — crée un object URL, déclenche le téléchargement, révoque l'URL
- **`pages/devis/DevisListPage.vue`** : bouton PDF (icône `pi-file-pdf`) visible pour tous les statuts sauf `brouillon`

### Refactoring SOLID — Devis workflow

#### Backend
- **SRP** : logique de transition d'état déplacée du controller vers `FacturationService` — nouvelles méthodes `envoyerDevis()`, `accepterDevis()`, `refuserDevis()`, `supprimerDevis()`, `mettreAJourDevis()` ; chaque méthode lève `DomainException` si la transition est invalide
- **FormRequests** : ajout `UpdateDevisRequest` (notes, date_validite) et `ConvertirEnMissionRequest` (date_debut, date_fin, collaborateur_ids) — suppression des `$request->validate()` inline dans `update()` et `convertirEnMission()`
- **DRY** : `MissionService::genererReference()` supprimée — délègue désormais à `FacturationService::genererNumero()` via le nouveau paramètre optionnel `$colonne` (défaut `'numero'`)
- **DevisController** : réduit à validation + délégation + retour Resource ; gestion HTTP 409 centralisée par `catch (DomainException)`

### Corrections — Devis : une prestation unique (US-11)

#### Backend
- **Migration** : suppression `devis_lignes`, ajout `prestation_id` + `prix_ht` sur `devis`
- **FacturationService::creerDevis()** : calcul automatique `tarif × indice_regime × indice_categorie` — prix saisi manuellement supprimé
- **StoreDevisRequest** : `prestation_id` requis, suppression `lignes[]`
- **DevisResource** : expose `prestation` et `prix_ht` au lieu des lignes
- **Tests** : 7 tests DevisApiTest réécrits (grille tarifaire, TVA, timbre, validation)

#### Frontend
- **`api/modules/devis.ts`** : `DevisPayload` passe à `prestation_id`, suppression `DevisLignePayload`
- **`types/index.ts`** : suppression `DevisLigne`, `Devis` aligné sur le backend
- **`pages/devis/DevisListPage.vue`** : formulaire — section lignes remplacée par `Select` prestation

### Ajouts — Portail client + Dashboard KPI + Refonte design

#### Backend — Portail client (US-29)
- **PortailService** : activation accès portail depuis fiche entreprise — création user `client` avec mot de passe temporaire, toggle actif/inactif
- **EntrepriseController** : méthodes `activerPortail` et `togglePortail`
- **EntrepriseResource** : exposition `portail_user` (id, email, portail_actif) via `whenLoaded`
- Routes : `POST /api/v1/entreprises/{id}/activer-portail`, `POST /api/v1/entreprises/{id}/toggle-portail`

#### Backend — Dashboard KPI (US-33 partiel)
- **DashboardController** : endpoint `GET /api/v1/stats` — compteurs entreprises/missions/factures/devis, CA HT/TTC, impayés, en retard, 5 derniers éléments

#### Frontend — Refonte design
- Nouveau système de layout PrimeVue 4 : `AppLayout`, `AppTopbar`, `AppSidebar`, `AppMenu`, `AppMenuItem`, `AppFooter`, `AppConfigurator`
- `FloatingConfigurator` : switch thème Aura/Lara en live
- Styles globaux refaits : `layout.scss`, `tailwind.css`
- Module API `stats.ts` pour le dashboard
- Pages mises à jour : `LoginPage`, `DashboardPage`, `EntrepriseListPage`

### A faire
- Module Relances (automatiques via queue + manuelles)
- Portail client (lecture seule factures/documents)
- Module KPI / Reporting (CA, missions, performance collaborateurs)
- Module Documents / GED
- PDF facture conforme DGI (US-14) — `PdfService::genererFacture()` + montant en lettres
- Avoirs (FA) — creation depuis facture existante

---

## [0.5.0] — 2026-03-25

### Ajouts — Module Missions + Taches

#### Backend — MissionService + Controllers

- **MissionService** — couche metier centralisee :
  - `creerMission()` : calcul automatique du prix HT (`prestation.tarif_initial x regime_fiscal.indice x categorie.indice`),
    generation de la reference sequentielle (M2026-001), creation mission + rattachement collaborateurs (pivot `mission_user`)
  - `updateMission()` : modification statut/dates/notes/collaborateurs. Prix HT immuable apres creation
  - `deleteMission()` : suppression bloquee si factures associees (HTTP 409)
- **MissionController** — CRUD complet avec filtres (entreprise, statut), eager loading relations
- **TacheController** — CRUD nested sous `/missions/{id}/taches`, changement statut inline
- **Conversion Devis → Mission** — `POST /devis/{id}/convertir-en-mission` : cree une mission depuis un devis accepte/envoye
- **FormRequests** : `StoreMissionRequest`, `UpdateMissionRequest`, `StoreTacheRequest`
- **Resources** : `MissionResource` (avec entreprise, prestation, taches, factures), `TacheResource`

#### Frontend — Pages Missions

- **API modules** : `missions.ts`, `taches.ts` — CRUD complet
- **Composables** : `useMissions.ts`, `useTaches.ts` — logique reactive
- **MissionListPage** — DataTable (reference, entreprise, prestation, prix HT, statut) + Dialog creation (select entreprise/prestation)
- **MissionDetailPage** — detail mission, tranches suggerees (30/30/40), section taches avec statut inline, factures liees
- **Router** : routes `/missions` et `/missions/:id`
- **Sidebar** : ajout item Missions (pi-briefcase) dans la navigation

#### Tests

- **MissionApiTest** (7 tests) : prix HT calcule, bascule prospect→client, CRUD, reference sequentielle, protection suppression
- **TacheApiTest** (3 tests) : creation, liste, mise a jour statut
- **Frontend** (9 tests) : modules API missions + taches

### Corrections

- **fix(auth)** : roles Spatie retournes comme objets `[{name: 'admin', ...}]` — normalisation en strings `['admin']` pour que `isAdmin` fonctionne dans le sidebar
- **fix(routes)** : parametre route taches `{tach}` → `{tache}` (singularisation Laravel incorrecte pour le francais)
- **fix(model)** : ajout `$attributes` defaults sur Tache (statut, priorite) pour coherence modele/BDD

---

## [0.4.0] — 2026-03-25

### Ajouts — Sprint 1 Facturation

#### Backend — Service Layer Facturation

- **FacturationService** — couche metier centralisee :
  - `genererNumero()` : numerotation sequentielle par exercice avec `lockForUpdate()` pour eviter
    les doublons en concurrence (format `{prefixe}{annee}-{sequence}`, ex: FF2026-001)
  - `creerDevis()` : creation devis + lignes, calcul automatique TVA/timbre depuis la date du devis
  - `creerFacture()` : creation facture + lignes avec **snapshots immuables** (taux_tva, montant_tva,
    montant_timbre, montant_ttc copies a la creation et jamais recalcules)
  - `recalculerStatutPaiement()` : mise a jour automatique du statut facture
    (en_attente → partiel → solde) selon la somme des paiements enregistres

- **Controllers REST** (dans `Http/Controllers/Facturation/`) :
  - `DevisController` — CRUD complet. Update/delete limites aux devis en statut `brouillon`
  - `FactureController` — Create/Read/Delete. Suppression bloquee si paiements associes (HTTP 409)
  - `PaiementController` — Create/Read/Delete, nested sous `/factures/{id}/paiements`.
    Enregistrement bloque si facture deja soldee (HTTP 409)

- **FormRequests** — validation stricte des inputs :
  - `StoreDevisRequest` : entreprise_id (exists), dates, lignes[].designation/quantite/prix_unitaire_ht
  - `StoreFactureRequest` : entreprise_id, type (in:FF,FA), dates, lignes
  - `StorePaiementRequest` : montant (integer >0), date_paiement, mode_paiement (in:virement,cheque,espece,autre)

- **API Resources** — serialisation JSON coherente :
  - DevisResource + DevisLigneResource (avec relations lignes incluses)
  - FactureResource + FactureLigneResource (avec snapshots TVA/timbre)
  - PaiementResource

- **Events / Listeners** — decouplage metier via evenements Laravel :
  - `MissionCreated` → `ConvertProspectToClient` : bascule automatique entreprise prospect → client
    a la creation de la premiere mission (via MissionObserver)
  - `InvoicePaid` → `CancelRelancesOnPayment` : annulation automatique des relances en cours
    quand une facture passe au statut solde

- **Routes API** ajoutees dans `routes/api.php` :
  - `GET|POST /api/v1/devis`, `GET|PUT|DELETE /api/v1/devis/{id}`
  - `GET|POST /api/v1/factures`, `GET|DELETE /api/v1/factures/{id}`
  - `GET|POST /api/v1/factures/{facture}/paiements`, `DELETE /api/v1/factures/{facture}/paiements/{paiement}`

- **Protection suppression** renforcee :
  - EntrepriseController : retourne 409 si devis ou missions associes

#### Frontend — Composables + API Modules

- **API Modules** (`api/modules/`) — couche d'abstraction HTTP, jamais d'appel Axios direct
  dans les composants :
  - `entreprises.ts` : getAll (pagine + search), getOne, create, update, delete
  - `exercices.ts` : getAll, getCurrent, create, update, delete
  - `prestations.ts` : getAll, calculerPrix (appel formule backend)
  - `users.ts` : getAll (pagine), getOne, create, update, delete
  - `settings.ts` : getAll, update (batch cle/valeur)
  - `devis.ts` : getAll (pagine), getOne, create, update, delete + DevisPayload/DevisLignePayload types
  - `factures.ts` : getAll (pagine), getOne, create, delete + createPaiement, getPaiements, deletePaiement

- **Composables** (`composables/`) — logique reactive reutilisable, separee des composants Vue :
  - `useEntreprises` : liste reactive, pagination, search, CRUD avec refresh auto
  - `useUsers` : idem pour utilisateurs
  - `useExercices` : liste + CRUD exercices fiscaux
  - `usePrestations` : liste + calcul prix HT
  - `useSettings` : liste + sauvegarde batch
  - `useDevis` : liste paginee, CRUD, updateStatut (brouillon → envoye → accepte/refuse)
  - `useFactures` : liste paginee, CRUD, addPaiement avec refresh statut

- **Types TypeScript** enrichis (`types/index.ts`) :
  - `Mission` : id, entreprise_id, exercice_id, prestation_id, numero, prix_ht, statut, dates
  - `DevisLigne` / `Devis` : lignes avec designation/quantite/prix, statut (brouillon→envoye→accepte→refuse→expire)
  - `FactureLigne` / `Facture` : snapshots taux_tva/montant_tva/montant_timbre/montant_ttc, statut_paiement
  - `Paiement` : montant, date, mode_paiement (virement|cheque|espece|autre), reference

- **Pages refactorisees** avec composables + CRUD complet via PrimeVue Dialog :
  - `EntrepriseListPage` : DataTable + Dialog creation/edition + ConfirmDialog suppression + Select regime/categorie/statut
  - `UserListPage` : DataTable + Dialog avec Password component + Select role
  - `ExerciceListPage` : DataTable + Dialog avec DatePicker
  - `PrestationListPage` : refactorisee avec usePrestations (lecture seule)
  - `SettingsPage` : refactorisee avec useSettings

- **Nouvelles pages** :
  - `DevisListPage` : creation avec lignes dynamiques (ajout/suppression), Select entreprise avec filtre,
    actions statut (envoyer), suppression limitee aux brouillons
  - `FactureListPage` : creation avec lignes + dialog paiement separe (montant, date, mode, reference),
    affichage montant restant du

- **Router** : routes `/devis` et `/factures` ajoutees avec meta backoffice
- **AdminLayout** : menu items Devis (pi-file) et Factures (pi-receipt) ajoutes dans la sidebar

#### Tests

- **Backend PHPUnit** — 28 tests, 59 assertions, tous passent sur SQLite :memory: :
  - `EntrepriseApiTest` (6 tests) : list pagine, create, update, delete protege si missions/devis, delete OK, search
  - `DevisApiTest` (5 tests) : create avec lignes, calcul TVA+timbre, list, delete protege si non-brouillon, numerotation sequentielle
  - `FactureApiTest` (6 tests) : create, snapshot TVA immuable, paiement → statut partiel, paiement complet → solde,
    delete protege si paiements, impossible de payer une facture soldee
  - `TvaRateTest` (3 tests) : taux correct par date, null avant tout taux, accepte string date
  - `TimbreRateTest` (3 tests) : calcul avec plafond, taux correct, null avant tout taux
  - `PrestationTest` (1 test) : prix_ht = tarif × indice_regime × indice_categorie
  - `ConvertProspectToClientTest` (2 tests) : prospect→client a la mission, client reste client
  - Tests Filament V1 supprimes (obsoletes depuis migration N-tier)

- **Frontend Vitest** — 18 tests, tous passent :
  - `types.test.ts` (5 tests) : validation interfaces Entreprise, Facture, Paiement, Devis, PaginatedResponse
  - `api-modules.test.ts` (13 tests) : mock Axios via `vi.hoisted()`, verification des appels HTTP corrects
    pour entreprises, exercices, devis, factures, settings

#### CI/CD

- **GitHub Actions** (`.github/workflows/ci.yml`) — pipeline declenchee sur push/PR vers main et develop :
  - Job `backend` : PHP 8.2, Composer cache, Laravel Pint (lint), PHPUnit sur SQLite :memory:
  - Job `frontend` : Node 20, npm ci, Vitest, vue-tsc (type check), Vite build

---

## [0.3.0] — 2026-03-23

### Correctifs
- fix(frontend): dark mode Aura — correction variables couleurs layout + fix CSRF proxy

---

## [0.2.0] — 2026-03-23

### Ajouts — Frontend Vue 3 + Pages CRUD

#### Frontend Setup
- Vue 3 + TypeScript + Vite + PrimeVue 4 (theme Aura) + Pinia + Vue Router
- Client Axios avec intercepteurs CSRF Sanctum
- Layouts AdminLayout (sidebar desktop / hamburger mobile) + PortailLayout
- Router avec guards auth + role (backoffice vs portail)
- Pinia store auth (login/logout/me)
- CSS mobile-first + skip-link RGAA + focus-visible

#### Pages CRUD
- LoginPage — authentification Sanctum SPA
- DashboardPage — page d'accueil admin (contenu a venir)
- UserListPage — liste + creation/edition utilisateurs avec roles
- EntrepriseListPage — CRUD entreprises (raison sociale, NIF, regime fiscal, categorie)
- ExerciceListPage — CRUD exercices fiscaux
- PrestationListPage — CRUD prestations (catalogue tarifaire)
- SettingsPage — parametres globaux cle/valeur
- PortailDashboard — page portail client (contenu a venir)

---

## [0.1.0] — 2026-03-16

### Ajouts — Backend Laravel API

#### Infrastructure & Configuration
- Laravel 12 (PHP 8.3) + Sanctum v4 (SPA cookie-based) + Spatie Permission v7.2 + DomPDF + Spatie Health
- MySQL 9.1 — fix `ROW_FORMAT=DYNAMIC` pour utf8mb4 (WAMP)
- `Schema::defaultStringLength(191)` dans `AppServiceProvider`
- Middleware `EnsureBackofficeAccess` — bloque les clients sur le back-office
- Middleware `EnsurePortailAccess` — bloque les non-clients, verifie `portail_actif`
- CORS configure pour `localhost:5173`

#### API REST
- AuthController — login/logout/me (Sanctum SPA)
- UserController — CRUD utilisateurs + assignation roles Spatie
- EntrepriseController — CRUD entreprises
- ExerciceController — CRUD exercices fiscaux
- PrestationController — CRUD prestations
- SettingController — CRUD parametres cle/valeur
- FormRequests de validation sur tous les endpoints
- API Resources JSON par domaine

#### Base de donnees — 17 migrations
- `users` + `users.entreprise_id` (nullable FK) + `users.portail_actif`
- `entreprises` — clients & prospects avec regime fiscal et categorie
- `exercices` — exercices fiscaux par annee avec statut `ouvert/cloture`
- `tva_rates` + `timbre_rates` — historique taux avec `date_debut`/`date_fin`
- `settings` — parametres cle/valeur par groupe
- `prestations` + `regimes_fiscaux` + `categories_entreprise` — grille tarifaire
- `missions` + `mission_user` — missions et affectation collaborateurs
- `taches` + `tache_commentaires` — planning et commentaires
- `devis` + `devis_lignes` — gestion des devis
- `factures` + `facture_lignes` — facturation FF/FA avec snapshot TVA
- `paiements` — suivi des encaissements
- `relances` — journal des relances
- `documents` — stockage et partage portail

#### Donnees initiales seedees
- 4 roles : `admin`, `collaborateur`, `secretaire`, `client`
- Compte admin : `admin@ledge.dz` / `password`
- TVA 19% (standard) + 9% (reduit) — LF 2023
- Timbre fiscal 1% plafonne 2 500 DA — LF 2024
- 5 prestations reelles (CAC, ACMPT, AENT, ASSC, A&C)
- Indices regimes fiscaux (Forfait x1.0, Reel x1.5) et categories (TPE x1.0, PME x1.75, GE x2.0)
- Exercice fiscal 2026 ouvert
- 12 parametres `settings` initiaux

#### Modeles Eloquent (18)
- Relations, casts et scopes configures
- `TvaRate::enVigueurLe($date)` + `TimbreRate::enVigueurLe($date)` — resolution historique
- `Prestation::calculerPrixHt($regime, $categorie)` — formule grille tarifaire
- `Exercice::current()` — exercice ouvert de l'annee en cours
- `User::canAccessPanel()` — controle d'acces par panel (backoffice vs portail)

---

## Branches actives

| Branche | Objectif | Statut |
|---|---|---|
| `main` | Production stable | init seulement |
| `develop` | Integration continue | actif |
| `feature/backend-setup` | Laravel API scaffold | merge |
| `feature/auth-api` | Auth Sanctum + Users | merge |
| `feature/core-api` | Controllers CRUD API | merge |
| `feature/frontend-setup` | Vue 3 + PrimeVue + Layout | merge |
| `feature/core-pages` | Pages CRUD frontend | merge |
| `fix/dark-mode-colors` | Fix theme dark mode | merge |
| `feature/facturation` | Sprint 1 — devis, factures, paiements, CI | en cours |

---

## Convention de commits

```
feat(module): description courte
fix(module): description du correctif
chore(deps): mise a jour dependance X
test(module): ajout tests unitaires
docs(changelog): mise a jour journal
refactor(module): refactoring sans changement fonctionnel
```

### Exemples Ledge
```
feat(facturation): calcul automatique TVA + timbre fiscal avec snapshot
feat(portail): activation acces client depuis fiche entreprise
feat(relances): relance automatique J+15 via queue Laravel
fix(factures): snapshot tva_taux_id manquant a la creation d'avoir
fix(exercices): numerotation annuelle ne se reinitialisait pas
chore(deps): mise a jour Laravel 12.x.0
test(facturation): FormuleHTPrixServiceTest — 12 cas couverts
docs(changelog): v0.1.0 — setup initial + migrations + seeders
```
