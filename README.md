# Ledge

Systeme de gestion integre pour cabinet de conseil / comptabilite — marche algerien.

## Stack

| Couche | Technologie |
|---|---|
| Frontend | Vue 3 + TypeScript + PrimeVue 4 + Pinia + Vue Router |
| Backend | Laravel 12 API REST + Sanctum (SPA) + Spatie Permission |
| Base de donnees | MySQL 9.1 |
| Architecture | N-tier 3 couches (Presentation / Metier / Donnees) |

## Installation

### Pre-requis
- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 9.x
- WAMP / LAMP / Docker

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve          # http://localhost:8000
```

### Frontend

```bash
cd frontend
npm install
npm run dev                # http://localhost:5173
```

### Compte admin par defaut

`admin@ledge.dz` / `password`

## Tests

```bash
# Backend (depuis backend/)
php artisan test              # 28 tests PHPUnit sur SQLite :memory:

# Frontend (depuis frontend/)
npm run test                  # 18 tests Vitest
npm run test:watch            # mode watch
```

## Modules

| Module | Statut |
|---|---|
| Auth + Roles (Sanctum + Spatie) | Fait |
| Entreprises (CRUD + composables) | Fait |
| Exercices fiscaux (CRUD + composables) | Fait |
| Prestations (grille tarifaire + composable) | Fait |
| Parametres (Settings + composable) | Fait |
| Utilisateurs (CRUD + composables) | Fait |
| Devis (CRUD + lignes dynamiques + conversion en mission) | Fait |
| Missions (CRUD + calcul prix HT + bascule prospect→client) | Fait |
| Taches (CRUD nested sous missions + statut inline) | Fait |
| Factures (CRUD + snapshots TVA + lien mission) | Fait |
| Paiements (enregistrement + statut auto) | Fait |
| Tests backend (PHPUnit — 38 tests) | Fait |
| Tests frontend (Vitest — 27 tests) | Fait |
| CI/CD GitHub Actions | Fait |
| Generation PDF (DomPDF) | A faire |
| Avoirs (FA) | A faire |
| Relances (auto + manuelles) | A faire |
| Portail client | A faire |
| KPI / Dashboard | A faire |

## Documentation

- [Architecture N-tier](docs/ARCHITECTURE.md)
- [Contexte projet](docs/CONTEXT.md)
- [Historique des versions](docs/HISTORIQUE.md) — V0 monolithique, V1 Filament, V2 N-tier
- [Gitflow RNCP](docs/GITFLOW.md)
- [Changelog](CHANGELOG.md)

## Gitflow

```
main      <- production stable (jamais de push direct)
develop   <- integration
feature/* <- une branche par module
fix/*     <- hotfix
```

Conventional Commits : `feat(module):`, `fix(module):`, `chore(module):`

---

> Projet RNCP 39583 — Expert en Developpement Logiciel — YNOV
