# Manuel de deploiement — Ledge

Ce document couvre trois scenarios :

1. [Demonstration locale avec Docker](#1-demonstration-locale-avec-docker) — **recommande pour le jury / une evaluation**
2. [Installation manuelle (developpement)](#2-installation-manuelle-developpement)
3. [Deploiement en production](#3-deploiement-en-production)

---

## 1. Demonstration locale avec Docker

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

Depuis l'**archive de livraison (.zip)** : dezipper, puis double-cliquer sur
`start-ledge.bat` (Windows) — ou executer `./start-ledge.sh` (Mac/Linux) — a la
racine du dossier extrait. Aucun clone necessaire.

Depuis le depot Git :

```bash
git clone <url-du-depot> ledge
cd ledge
docker compose up --build
```

La premiere execution telecharge les images et construit le conteneur PHP
(~2 a 5 min). Le conteneur backend s'**auto-initialise** ensuite
(`backend/docker/php/entrypoint.sh`) :

1. copie `backend/.env.docker` -> `.env` ;
2. `composer install` ;
3. generation de la cle applicative ;
4. attente de MySQL ;
5. `php artisan migrate --seed` (schema + referentiel + compte admin).

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
> `ADMIN_PASSWORD='MonMotDePasse' docker compose up --build`.

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

### 1.6 Emails (invitations, reinitialisation)

En demo, `MAIL_MAILER=log` : aucun email n'est reellement envoye. Les liens
d'invitation et de reinitialisation restent :

- **affiches a l'admin** (lien copiable dans l'interface, apres activation d'un
  acces) ;
- **ecrits** dans `backend/storage/logs/laravel.log`.

Pour un envoi reel, renseigner un fournisseur SMTP (ex. Brevo) dans
`backend/.env.docker` puis `docker compose up -d --force-recreate app` :

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login-brevo>
MAIL_PASSWORD=<cle-smtp>
```

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

Voir [docs/SECURITY.md](SECURITY.md) pour les controles de securite en place.

### 3.5 Mise a jour d'une instance existante

Voir [MANUEL-MISE-A-JOUR.md](MANUEL-MISE-A-JOUR.md) (procedure + rollback).
