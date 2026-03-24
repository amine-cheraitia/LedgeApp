# Architecture N-tier — Ledge

> Decision prise le 23 Mars 2026
> Remplace la V0 monolithique (Laravel 8 + Blade) et la V1 Filament (abandonnee)

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
│   Tier 3 — Données          │  MySQL 9.1 — 20+ tables
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

Architecture **classique Laravel avec sous-dossiers par domaine** :

```
backend/app/Http/Controllers/
├── Auth/              # AuthController, UserController
├── Entreprises/       # EntrepriseController
├── Exercices/         # ExerciceController
├── Prestations/       # PrestationController
├── Settings/          # SettingController
├── Facturation/       # FactureController, DevisController, AvoirController
├── Planning/          # MissionController, TacheController
├── Relances/          # RelanceController
└── Documents/         # DocumentController
```

Pas de modules avec ServiceProviders séparés — trop de config pour 18 modèles, même clarté avec des sous-dossiers.

## Organisation du frontend

```
frontend/src/
├── api/client.ts      # Axios configuré (CSRF, intercepteurs)
├── assets/styles/     # CSS mobile-first, skip-link RGAA
├── layouts/           # AdminLayout (sidebar), PortailLayout
├── pages/             # Un dossier par domaine
├── router/            # Guards auth + rôle
├── stores/            # Pinia (auth, etc.)
└── types/             # Interfaces TypeScript
```

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
