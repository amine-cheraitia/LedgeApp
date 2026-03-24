# CHANGELOG — Ledge

> Format : [Semantic Versioning](https://semver.org) — `MAJOR.MINOR.PATCH`
> - **MAJOR** : rupture de compatibilite (migration BDD obligatoire)
> - **MINOR** : nouvelle fonctionnalite retro-compatible
> - **PATCH** : correctif sans impact sur le schema

---

## [Unreleased]

### A faire
- Module Facturation (devis, factures, avoirs, PDF, TVA historisee)
- Module Missions / Planning (FullCalendar, taches, assignation collaborateurs)
- Module Paiements (suivi creances, planning paiement)
- Module Relances (automatiques via queue + manuelles)
- Portail client (lecture seule factures/documents)
- Module KPI / Reporting (CA, missions, performance collaborateurs)
- Module Documents / GED
- Tests unitaires (PHPUnit backend + composants Vue frontend)
- CI/CD GitHub Actions

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
