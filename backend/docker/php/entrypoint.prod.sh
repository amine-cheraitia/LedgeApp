#!/bin/sh
# Demarrage du conteneur de production : caches Laravel (l'environnement
# d'execution n'est connu qu'ici — jamais au build), puis php-fpm + nginx.
# Les migrations restent une etape de deploiement explicite :
#   docker exec <conteneur> php artisan migrate --force
set -e
cd /var/www

mkdir -p storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

php artisan storage:link 2>/dev/null || true

# config/route/view caches — tolerant : un .env incomplet ne doit pas empecher
# le conteneur de demarrer (diagnostic possible via artisan dans le conteneur).
php artisan optimize 2>/dev/null || echo "[entrypoint] optimize ignore (env incomplet ?)"

php-fpm -D
exec nginx -g 'daemon off;'
