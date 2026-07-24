# Manuel de deploiement — Ledge

Ce document couvre trois scenarios :

1. [Demonstration locale avec Docker](#1-demonstration-locale-avec-docker) — **recommande pour le jury / une evaluation**
2. [Installation manuelle (developpement)](#2-installation-manuelle-developpement)
3. [Deploiement en production](#3-deploiement-en-production)

---

## 1. Demonstration locale avec Docker

> **>> C'EST LE PARCOURS A SUIVRE POUR LE JURY / L'EVALUATION <<**
>
> L'archive de livraison se lance en un double-clic (`start-ledge.bat`), sans rien
> installer ni configurer d'autre que Docker Desktop, et avec l'envoi d'emails
> deja actif.

### 1.1 Prerequis

- **Docker Desktop** (Windows/macOS) ou **Docker Engine + Docker Compose v2** (Linux).
  Telechargement : https://www.docker.com/products/docker-desktop/
- Sous Windows, Docker Desktop peut demander d'installer **WSL 2** au premier
  lancement : suivre l'assistant (un redemarrage peut etre necessaire).
- **Docker Desktop doit etre demarre** (icone baleine dans la barre des taches)
  avant de lancer la stack.
- 4 Go de RAM libre, ports **5173**, **8000** et **3307** disponibles.

Verifier l'installation :

```bash
docker --version
docker compose version
```

### 1.2 Lancement

**Parcours jury — depuis l'archive de livraison (.zip)** :

1. Dezipper l'archive.
2. Docker Desktop demarre, **double-cliquer sur `start-ledge.bat`** (Windows) —
   ou executer `./start-ledge.sh` (Mac/Linux) — a la racine du dossier extrait.

C'est tout : aucun clone, aucune commande, aucune configuration. L'archive
embarque deja son `backend/.env` (base de demonstration + **envoi d'emails reel
pre-configure**).

> **Alternative (developpeurs) — depuis le depot Git** : `git clone <url> ledge`,
> `cd ledge`, puis `docker compose up --build`. Un clone ne contient pas de
> `backend/.env` : le conteneur en cree un automatiquement depuis le modele
> `backend/.env.docker` (emails alors en mode `log`, cf. §1.6).

La premiere execution telecharge les images et construit le conteneur PHP
(~2 a 5 min), puis le backend s'**auto-initialise** sans aucune manipulation
(`backend/docker/php/entrypoint.sh`) : `composer install`, generation de la cle
applicative, attente de MySQL, puis `php artisan migrate --seed` (schema +
referentiel + compte admin). Un `backend/.env` deja present — le cas de
l'archive — est **conserve tel quel**.

Quand le log affiche `Backend pret. Demarrage de PHP-FPM.` et que le frontend
affiche `Local: http://localhost:5173/`, tout est operationnel.

### 1.3 Acces

| Service | URL | Notes |
|---|---|---|
| Application (SPA) | http://localhost:5173 | Interface d'evaluation |
| API (Laravel) | http://localhost:8000 | REST `/api/v1/*` |
| Base MySQL | `localhost:3307` | user `ledge` / `secret`, base `ledge` |

**Connexion administrateur** :

| Champ | Valeur |
|---|---|
| Email | `admin@ledge.dz` |
| Mot de passe | valeur de `ADMIN_PASSWORD` dans `docker-compose.yml` (defaut `Ledge@Demo2026`) |

> Identifiants de **demonstration**. Aucun mot de passe de production n'est
> versionne. Pour en definir un autre avant le premier lancement :
> - Linux/macOS : `ADMIN_PASSWORD='MonMotDePasse' docker compose up --build`
> - Windows (PowerShell) : `$env:ADMIN_PASSWORD='MonMotDePasse'; docker compose up --build`

### 1.4 Ce que contient la base au demarrage

Le seed cree une base **propre et prete a l'emploi**, sans fausses ecritures :

- les 4 roles (`admin`, `collaborateur`, `secretaire`, `client`) ;
- le compte administrateur ;
- les parametres (`settings`) et prefixes de numerotation ;
- l'exercice fiscal courant ;
- les taux de TVA historises ;
- la grille des prestations.

Vous creez ensuite vos entreprises, devis, missions et factures depuis l'interface.

### 1.5 Commandes utiles

```bash
docker compose up -d              # demarrer en arriere-plan
docker compose logs -f app        # suivre les logs backend
docker compose logs -f frontend   # suivre les logs frontend
docker compose exec app php artisan test   # lancer les tests dans le conteneur
docker compose down               # arreter (conserve la base)
docker compose down -v            # arreter + SUPPRIMER la base (repartir de zero)
```

### 1.6 Emails (invitations, devis, factures, reinitialisation)

Deux configurations selon la provenance du projet :

- **Archive de livraison (evaluation)** : l'envoi SMTP est **pre-configure et
  actif**. C'est un choix volontaire : un **compte d'envoi de demonstration**
  (Brevo), dedie a l'evaluation, est fourni pour permettre de **tester les envois
  reels** — devis, factures, invitations portail — sans aucune configuration.
  Ce compte de test ne donne acces a aucune donnee, n'est **pas versionne dans
  le depot git**, et sera **revoque apres l'evaluation**. Aucun identifiant de
  production n'est distribue.
- **Depot Git (clone)** : le compte SMTP de demonstration **n'est pas inclus**
  dans le depot (aucun secret versionne). Par defaut `MAIL_MAILER=log` — aucun
  email n'est reellement envoye ; les liens d'invitation et de reinitialisation
  restent :
  - **affiches a l'admin** (lien copiable dans l'interface, apres activation
    d'un acces) ;
  - **ecrits** dans `backend/storage/logs/laravel.log`.

  Pour obtenir un **envoi reel** depuis un clone du depot, il faut donc
  **fournir ses propres identifiants SMTP** (voir ci-dessous).

Pour configurer un fournisseur SMTP (ex. un compte Brevo personnel), editer le
fichier **`backend/.env`** — et non `backend/.env.docker` : ce dernier n'est
qu'un modele, copie une seule fois vers `.env` au tout premier demarrage ; une
fois `.env` cree, seul `.env` est lu. Renseigner :

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login-brevo>
MAIL_PASSWORD=<cle-smtp>
MAIL_FROM_ADDRESS=<expediteur-verifie-brevo>
```

puis appliquer sans reconstruire : `docker compose restart app`.
(Si `backend/.env` n'existe pas encore — clone jamais lance — on peut a la
place editer `backend/.env.docker` avant le premier `docker compose up`.)

> **Conseil pour tester l'envoi reel (jury / evaluation)** — lorsque l'envoi SMTP
> est actif (cas de l'archive de livraison, pre-configuree) : a la creation d'une
> entreprise ou d'un contact, **utilisez votre propre adresse email** comme email
> de l'entreprise/du contact. Vous recevrez ainsi reellement les devis, factures
> et invitations portail envoyes depuis l'application. **Pensez a verifier le
> dossier spam / courrier indesirable** : l'expediteur etant un compte SMTP de
> demonstration, les messages peuvent y etre classes au premier envoi.

### 1.7 Depannage

| Symptome | Cause probable | Solution |
|---|---|---|
| `docker n'est pas reconnu` / `cannot connect to the Docker daemon` | Docker Desktop n'est pas installe ou pas demarre | Lancer Docker Desktop et attendre qu'il indique « running », puis relancer |
| `port is already allocated` | 5173/8000/3307 deja utilise | Liberer le port ou modifier le mapping dans `docker-compose.yml` |
| Le frontend charge mais l'API repond 502 | Backend encore en cours d'init | Attendre la fin de l'init (`docker compose logs -f app`) |
| Erreur 419 (CSRF) au login | Cookies non partages | Verifier l'acces via `http://localhost:5173` (pas `127.0.0.1`) |
| Base incoherente apres essais | Migrations partielles | `docker compose down -v` puis `docker compose up` |
| `failed to solve: invalid file request public/storage` au build | Lien symbolique `backend/public/storage` cree par un lancement precedent (normalement exclu via `.dockerignore`) | Supprimer le lien puis relancer : `Remove-Item backend\public\storage -Force` (PowerShell) |

---

## 2. Installation manuelle (developpement)

### 2.1 Prerequis
- PHP 8.2+ (`pdo_mysql`, `mbstring`, `bcmath`, `gd`, `intl`, `zip`)
- Composer 2, Node.js 20+
- MySQL 8 (ou SQLite pour un essai rapide)

### 2.2 Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Editer .env : connexion BDD + ADMIN_PASSWORD (obligatoire, jamais commite)
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

Pour un essai immediat sans MySQL, laisser `DB_CONNECTION=sqlite` et
`touch database/database.sqlite` avant `migrate`.

### 2.3 Frontend

```bash
cd frontend
npm install
npm run dev                  # http://localhost:5173
```

Le serveur Vite proxifie `/api` et `/sanctum` vers `http://localhost:8000`
(configurable via `VITE_PROXY_TARGET`).

---

## 3. Deploiement en production

### 3.1 Principes

- `APP_ENV=production`, `APP_DEBUG=false`.
- `APP_KEY` genere et **secret** ; `ADMIN_PASSWORD` fort, jamais versionne.
- HTTPS obligatoire -> `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=strict`.
- `QUEUE_CONNECTION=redis` **avec** un worker (`php artisan queue:work`, gere par
  Supervisor ou un service `queue` Docker).
- `CACHE_STORE=redis`, `SESSION_DRIVER=redis` ou `database`.
- `TELESCOPE_ENABLED=false`.

### 3.2 Build frontend

```bash
cd frontend
npm ci
npm run build                # genere dist/ (a servir par nginx/CDN)
```

### 3.3 Optimisations backend

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 3.4 Supervision

- Sonde publique : `GET /up` (UptimeRobot) — ne divulgue aucun detail.
- Diagnostics detailles : `GET /health` (reserve `role:admin`).
- Error tracking : renseigner `SENTRY_LARAVEL_DSN`.

Voir [SECURITY.md](SECURITY.md) pour les controles de securite en place.

### 3.5 Images Docker de release (GHCR) — livraison continue

A chaque tag Git `vX.Y.Z`, le pipeline CD (`.github/workflows/cd.yml`) construit,
scanne (Trivy — bloquant sur vulnerabilite HIGH/CRITICAL corrigeable) et publie
deux images de production sur GitHub Container Registry, apres avoir re-execute
**toutes les portes de qualite de la CI** (lint, 1101 tests, gates de
couverture, audits de dependances, E2E).

> **Note sur les tags** : le tag **Git** est `vX.Y.Z` (avec `v`), mais les tags
> d'**image** publies sur GHCR n'ont **pas** le prefixe : `X.Y.Z`, `X.Y` et
> `latest` (ex. pour le tag Git `v1.1.0` -> images `1.1.0`, `1.1`, `latest`).

| Image | Contenu | Port |
|---|---|---|
| `ghcr.io/amine-cheraitia/ledge-backend:X.Y.Z` | API Laravel (php-fpm + nginx embarque, vendor sans dev, opcache production) | 8000 |
| `ghcr.io/amine-cheraitia/ledge-frontend:X.Y.Z` | SPA Vue buildee servie par nginx, proxy `/api`, `/sanctum`, `/storage` vers `BACKEND_HOST` | 80 |

Deploiement type (serveur avec Docker, MySQL et Redis accessibles) — exemple
avec la version `1.1.0` :

```bash
docker pull ghcr.io/amine-cheraitia/ledge-backend:1.1.0
docker pull ghcr.io/amine-cheraitia/ledge-frontend:1.1.0

docker run -d --name ledge-api  --env-file .env.production -p 8000:8000 \
  ghcr.io/amine-cheraitia/ledge-backend:1.1.0
docker run -d --name ledge-web  -e BACKEND_HOST=ledge-api:8000 -p 80:80 \
  ghcr.io/amine-cheraitia/ledge-frontend:1.1.0

# Migrations : etape de deploiement explicite (jamais automatique)
docker exec ledge-api php artisan migrate --force
```

Le conteneur backend applique lui-meme `storage:link` et les caches Laravel
(`php artisan optimize`) au demarrage — l'environnement (`.env.production`)
n'est connu qu'a l'execution, jamais fige dans l'image.

### 3.6 Mise a jour d'une instance existante

Voir [MANUEL-MISE-A-JOUR.md](MANUEL-MISE-A-JOUR.md) (procedure + rollback).
