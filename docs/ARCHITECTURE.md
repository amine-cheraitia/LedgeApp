# Architecture N-tier — Ledge

> Decision d'architecture prise le 23 Mars 2026 au cadrage du projet

## Vue d'ensemble

```
┌─────────────────────────────┐
│   Tier 1 — Présentation    │  Vue 3 + TypeScript + PrimeVue
│   (frontend/)              │  SPA accessible RGAA, responsive mobile-first
└──────────────┬──────────────┘
               │ HTTP / JSON (API REST)
               │ Auth : Sanctum (cookie SPA)
┌──────────────▼──────────────┐
│   Tier 2 — Logique Métier   │  Laravel 12 API + Sanctum + Spatie Permission
│   (backend/)               │  Controllers / FormRequests / Resources par domaine
└──────────────┬──────────────┘
               │ Eloquent ORM
┌──────────────▼──────────────┐
│   Tier 3 — Données          │  MySQL 8 — 25+ tables
│                             │  Historisation TVA, exercices fiscaux
└─────────────────────────────┘
```

## Stack retenue

| Couche | Technologie | Justification |
|---|---|---|
| Frontend | Vue 3 + TypeScript + PrimeVue | Composants riches (DataTable, Calendar), accessibilité native, thème pro |
| State management | Pinia | Léger, TypeScript natif, stores modulaires |
| Routing | Vue Router | Guards par rôle (backoffice vs portail) |
| HTTP Client | Axios | Intercepteurs CSRF, gestion erreurs globale |
| Backend | Laravel 12 API | Écosystème mature, Eloquent, validation, DomPDF |
| Auth | Sanctum (SPA mode) | Cookie-based, CSRF automatique, même domaine |
| Permissions | Spatie Laravel Permission | Rôles granulaires, middleware intégré |
| BDD | MySQL | Données relationnelles, FK, historisation |

## Organisation du backend

Architecture **Controller → Service → Model** avec sous-dossiers par domaine :

```
backend/app/
├── Http/
│   ├── Controllers/
│   │   ├── Audit/             # AuditController (journal d'audit)
│   │   ├── Auth/              # AuthController, PasswordController, UserController
│   │   ├── Dashboard/         # DashboardController, KpiController, StatistiqueController
│   │   ├── Entreprises/       # EntrepriseController, ContactController
│   │   ├── Exercices/         # ExerciceController
│   │   ├── Facturation/       # DevisController, FactureController, AvoirController, PaiementController, CreanceController, RelanceController
│   │   ├── Planning/          # MissionController, TacheController, TacheCommentaireController, CalendarController
│   │   ├── Portail/           # PortailController, PortailFactureController, PortailMissionController, PortailDocumentController
│   │   ├── Prestations/       # PrestationController
│   │   ├── Referentiel/       # ReferentielTvaController
│   │   └── Settings/          # SettingController
│   ├── Requests/              # FormRequests par domaine (Auth/, Entreprises/, Facturation/, Planning/, ...)
│   └── Resources/             # API Resources JSON par domaine (Facturation/, Planning/, ...)
├── Services/                  # 20 services metier — une responsabilite chacun (SRP)
│   ├── FacturationService.php # Transitions devis, creation factures/avoirs, paiements
│   ├── MissionService.php     # Calcul prix HT, CRUD missions, documents generes
│   ├── NumerotationService.php# Numerotation annuelle par exercice (FF/FA/DV/MD/CV)
│   ├── PdfService.php         # PDF DomPDF : devis, facture, avoir, mandat, convention, rapports
│   └── ...                    # Entreprise, Contact, Exercice, Prestation, TvaTaux, Setting, Relance,
│                              # Portail, Dashboard, Kpi, Statistique, Calendar, Tache, User, Invitation, Audit
├── Events/                    # MissionCreated, InvoicePaid
├── Listeners/                 # ConvertProspectToClient, CancelRelancesOnPayment
├── Observers/                 # MissionObserver
├── Models/                    # 19 modeles Eloquent
└── Providers/                 # AppServiceProvider (observers, events)
```

**Principe SOLID** : le Controller valide (FormRequest), delegue au Service, retourne la Resource.
Le Service contient toute la logique metier (transitions d'etat, calculs, regles de gestion) et leve `DomainException` si une regle est violee.
Le Controller catch `DomainException` et retourne le code HTTP approprie (409, etc.).
Le Model gere les relations, casts et scopes Eloquent.

Pas de modules avec ServiceProviders separes — trop de config pour 19 modeles, meme clarte avec des sous-dossiers.

## Organisation du frontend

Architecture **Page → Composable → API Module → Axios** :

```
frontend/src/
├── api/
│   ├── client.ts          # Axios configure (CSRF, intercepteurs)
│   └── modules/           # Un module par domaine (entreprises, devis, factures, missions, taches, ...)
├── composables/           # Logique reactive reutilisable (useEntreprises, useFactures, ...)
├── components/            # Composants partages (logo, facturation)
├── assets/styles/         # CSS mobile-first, skip-link RGAA
├── layout/                # AppLayout (sidebar back-office), PortailLayout
├── pages/                 # Un dossier par domaine
├── router/                # Guards auth + role
├── stores/                # Pinia (auth uniquement — le reste via composables)
├── types/                 # Interfaces TypeScript
├── utils/                 # Formatage devise/date, palette graphique
└── __tests__/             # Tests Vitest (pages, composables, api, layout, ...)
```

**Principe** : la Page utilise le Composable pour la logique reactive.
Le Composable appelle le Module API. Le Module API fait les requetes HTTP via Axios.
Jamais d'appel Axios direct dans les composants Vue.

## Tests

```
backend/tests/             # 497 tests — 45 fichiers
├── Feature/               # Integration API par domaine (facturation, planning, portail,
│                          #   auth, audit, jobs, PDF, securite) — 38 fichiers
└── Unit/
    ├── Models/            # Regles des modeles (TvaTaux, Prestation...)
    ├── Listeners/         # Listeners d'events (ConvertProspectToClient...)
    └── Services/          # Logique metier isolee (FacturationService...)

frontend/src/__tests__/    # 604 tests — 52 fichiers (pages, composables, stores, utils)
frontend/e2e/              # 4 specs Playwright — parcours complets en navigateur
```

- Backend : PHPUnit + SQLite :memory: (RefreshDatabase)
- Frontend : Vitest + happy-dom + @vue/test-utils
- E2E : Playwright (Chromium) sur la stack complete — job CI dedie

Detail complet : [STRATEGIE-TESTS.md](STRATEGIE-TESTS.md).

## CI/CD

Pipeline GitHub Actions (`.github/workflows/ci.yml`) declenchee sur push/PR vers `main` et `develop` :

| Job | Etapes |
|---|---|
| `gitflow-guard` | Bloque toute PR `feature/*` ciblant `main` |
| `backend` | PHP 8.3 → Composer install → Pint lint → PHPUnit (gate couverture ≥ 80 %) → `composer audit` (bloquant) |
| `frontend` | Node 20 → npm ci → ESLint → Vitest (gates couverture) → `npm audit` (bloquant des high) → vue-tsc → Vite build |
| `e2e` | Playwright (Chromium) — parcours bout en bout sur la stack complete |

**CD** (`.github/workflows/cd.yml`) — declenche par un tag `vX.Y.Z` : re-execute
toutes les portes de qualite de la CI (workflow reutilise), construit les deux
images de production (backend php-fpm+nginx, frontend nginx statique), les scanne
(Trivy, bloquant HIGH/CRITICAL corrigeable), les smoke-teste puis les publie sur
GitHub Container Registry (`ghcr.io`). Le livrable de release est une image
versionnee prete au deploiement (voir MANUEL-DEPLOIEMENT §3.5).

## Sécurité (OWASP)

- **A01 Broken Access Control** : middleware `backoffice` / `portail` sur les groupes de routes
- **A03 Injection** : FormRequests sur tous les endpoints, jamais de `DB::raw()` avec input
- **A07 XSS** : pas de `v-html` avec données utilisateur
- **CSRF** : Sanctum cookie + token automatique via intercepteur Axios

## Accessibilité (RGAA)

- Skip link "Aller au contenu principal"
- `aria-label` sur tous les boutons icônes
- `role="navigation"`, `role="main"`, `role="banner"` sur le layout
- `:focus-visible` outline CSS
- Labels sur tous les champs de formulaire
- `.sr-only` pour le contenu uniquement pour lecteurs d'écran
