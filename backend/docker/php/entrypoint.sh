#!/bin/sh
# Initialisation automatique du backend Laravel dans le conteneur.
# Idempotent : peut etre relance sans casser une installation existante.
set -e
cd /var/www

log() { echo "[entrypoint] $1"; }

# 1. Fichier .env (depuis le modele Docker, sinon .env.example)
if [ ! -f .env ]; then
  if [ -f .env.docker ]; then
    cp .env.docker .env
    log ".env cree depuis .env.docker"
  else
    cp .env.example .env
    log ".env cree depuis .env.example"
  fi
fi

# 2. Dependances PHP
if [ ! -d vendor ]; then
  log "Installation des dependances (composer install)..."
  composer install --no-interaction --prefer-dist --no-progress
fi

# 3. Cle applicative (uniquement si absente)
if ! grep -q "^APP_KEY=base64:" .env; then
  log "Generation de la cle applicative..."
  php artisan key:generate --force
fi

# 4. Attente de la base de donnees
log "Attente de la base de donnees ($DB_HOST:$DB_PORT)..."
until php -r "exit(@fsockopen(getenv('DB_HOST') ?: 'mysql', (int)(getenv('DB_PORT') ?: 3306)) ? 0 : 1);" 2>/dev/null; do
  sleep 2
done
log "Base de donnees joignable."

# 5. Migrations + donnees de reference (idempotent : n'applique que le manquant)
log "Migrations + seed..."
php artisan migrate --force --seed

# 6. Lien de stockage public + nettoyage des caches de config
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear

log "Backend pret. Demarrage de PHP-FPM."
exec php-fpm
