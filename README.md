# Ledge

Systeme de gestion integre pour cabinet de conseil / comptabilite — marche algerien.
Montants en **Dinars Algeriens (DA)**.

## Stack

| Couche | Technologie |
|---|---|
| Frontend | Vue 3 + TypeScript + PrimeVue 4 + Pinia + Vue Router |
| Backend | Laravel 12 API REST + Sanctum (SPA) + Spatie Permission |
| Base de donnees | MySQL 8 |
| Cache / files | Redis 7 |
| Architecture | N-tier 3 couches (Presentation / Metier / Donnees) |

---

## Demarrage rapide avec Docker (recommande pour tester en local)

Un seul prerequis : **Docker Desktop** (ou Docker Engine + Compose v2).

```bash
git clone <url-du-depot> ledge && cd ledge
docker compose up --build
```

Le backend s'auto-initialise (dependances, cle applicative, migrations + jeu de
donnees de demonstration). Au bout de ~2 min, tout est pret :

| Service | URL |
|---|---|
| Application (SPA) | http://localhost:5173 |
| API (Laravel) | http://localhost:8000 |

**Connexion de demonstration** : `admin@ledge.dz` / mot de passe defini par la
variable `ADMIN_PASSWORD` de [`docker-compose.yml`](docker-compose.yml)
(defaut : `Ledge@Demo2026`). Identifiants de **demo uniquement**, a changer en
usage reel. Aucun mot de passe de production n'est versionne dans ce depot.

> Guide detaille (arret, reset, envoi d'emails, production) :
> [docs/MANUEL-DEPLOIEMENT.md](docs/MANUEL-DEPLOIEMENT.md).

---

## Installation manuelle (sans Docker)

### Pre-requis
- PHP 8.2+ (extensions : `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `intl`, `zip`)
- Composer 2
- Node.js 20+
- MySQL 8 (ou SQLite pour un essai rapide)

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Definir un mot de passe admin (obligatoire, jamais commite) :
#   ADMIN_PASSWORD=... dans .env
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

### Frontend

```bash
cd frontend
npm install
npm run dev                # http://localhost:5173
```

---

## Tests

```bash
# Backend (depuis backend/)
php artisan test              # 444 tests PHPUnit sur SQLite :memory:

# Frontend (depuis frontend/)
npm run test                  # 551 tests Vitest
npm run test:coverage         # rapport de couverture (seuils garde-fous)
```

---

## Modules

| Module | Statut |
|---|---|
| Auth + Roles (Sanctum + Spatie, invitations par email) | Fait |
| Entreprises + Contacts (CRUD, bascule prospect->client) | Fait |
| Exercices fiscaux (CRUD, numerotation par exercice) | Fait |
| Prestations (grille tarifaire) | Fait |
| Parametres (Settings) | Fait |
| Utilisateurs (CRUD + invitations) | Fait |
| Devis (CRUD + lignes dynamiques + conversion en mission) | Fait |
| Missions (CRUD + calcul prix HT + bascule prospect->client) | Fait |
| Taches (CRUD nested + commentaires + statut inline) | Fait |
| Factures (CRUD + snapshots TVA historisee + lien mission) | Fait |
| Avoirs (FA) | Fait |
| Paiements + statut auto (en_attente/partiel/solde) | Fait |
| Creances + Relances (recouvrement) | Fait |
| Generation PDF (DomPDF — devis, factures, rapport de mission) | Fait |
| Portail client (lecture seule, isolation par entreprise) | Fait |
| Calendrier / Planning | Fait |
| KPI / Dashboard | Fait |
| Journal d'audit (activitylog) | Fait |
| Tests backend (PHPUnit — 444 tests) | Fait |
| Tests frontend (Vitest — 551 tests) | Fait |
| CI/CD GitHub Actions (Pint, tests, couverture) | Fait |

---

## Documentation

**Manuels**
- [Manuel de deploiement](docs/MANUEL-DEPLOIEMENT.md) — Docker + installation serveur
- [Manuel d'utilisation](docs/MANUEL-UTILISATION.md) — par role (admin, collaborateur, secretaire, client)
- [Manuel de mise a jour](docs/MANUEL-MISE-A-JOUR.md) — montee de version + rollback

**Reference**
- [Architecture N-tier](docs/ARCHITECTURE.md)
- [Contexte projet](docs/CONTEXT.md)
- [Securite (OWASP)](docs/SECURITY.md)
- [Accessibilite (RGAA)](docs/ACCESSIBILITE-RGAA.md)
- [Strategie de tests](docs/STRATEGIE-TESTS.md)
- [Plan de correction des bogues](docs/PLAN-CORRECTION-BOGUES.md)
- [Gitflow RNCP](docs/GITFLOW.md)
- [Historique des versions](docs/HISTORIQUE.md)
- [Changelog](CHANGELOG.md)

---

## Gitflow

```
main      <- production stable (jamais de push direct)
develop   <- integration
feature/* <- une branche par fonctionnalite
fix/*     <- hotfix
```

Conventional Commits : `feat(module):`, `fix(module):`, `chore(module):`.
Chaque version stable est taggee `vX.Y.Z` (SemVer).

---

> Projet RNCP 39583 — Expert en Developpement Logiciel — YNOV
