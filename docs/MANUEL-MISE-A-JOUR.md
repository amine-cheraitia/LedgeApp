# Manuel de mise a jour — Ledge

Procedure de montee de version d'une instance existante, avec sauvegarde et
rollback. Chaque version stable est identifiee par un tag `vX.Y.Z` (SemVer).

## Avant toute mise a jour

1. **Lire le [CHANGELOG](../CHANGELOG.md)** : ruptures eventuelles, nouvelles
   variables d'environnement, migrations sensibles.
2. **Sauvegarder la base de donnees** (indispensable) :
   ```bash
   # Docker (-T : pas de pseudo-TTY, evite les CRLF parasites dans le dump)
   docker compose exec -T mysql mysqldump -u ledge -psecret ledge > backup-$(date +%F).sql
   # Serveur
   mysqldump -u <user> -p <base> > backup-$(date +%F).sql
   ```
3. **Sauvegarder le fichier `.env`** et le dossier `storage/` (fichiers generes).
4. Noter la version en cours : `git describe --tags`.

---

## Mise a jour — Docker

```bash
git fetch --tags
git checkout vX.Y.Z            # version cible
docker compose build
docker compose up -d
```

Les migrations s'appliquent automatiquement au demarrage du conteneur `app`
(entrypoint). Verifier ensuite :

```bash
docker compose logs -f app     # doit finir sur "Backend pret"
docker compose exec app php artisan migrate:status
```

---

## Mise a jour — par images de production (GHCR)

Pour une instance deployee a partir des images publiees par le pipeline CD
(voir [MANUEL-DEPLOIEMENT §3.5](MANUEL-DEPLOIEMENT.md)) :

Les tags d'image GHCR n'ont **pas** le prefixe `v` du tag Git (le tag Git
`v1.1.0` publie les images `1.1.0`, `1.1`, `latest`). Exemple avec `1.1.0` :

```bash
docker pull ghcr.io/amine-cheraitia/ledge-backend:1.1.0
docker pull ghcr.io/amine-cheraitia/ledge-frontend:1.1.0

docker stop ledge-api ledge-web && docker rm ledge-api ledge-web
docker run -d --name ledge-api --env-file .env.production -p 8000:8000 \
  ghcr.io/amine-cheraitia/ledge-backend:1.1.0
docker run -d --name ledge-web -e BACKEND_HOST=ledge-api:8000 -p 80:80 \
  ghcr.io/amine-cheraitia/ledge-frontend:1.1.0

docker exec ledge-api php artisan migrate --force
```

Rollback : relancer les conteneurs sur le tag d'image precedent (les images
restent disponibles sur GHCR) + restauration du dump si des migrations sont passees.

---

## Mise a jour — installation manuelle

```bash
git fetch --tags && git checkout vX.Y.Z

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan down                       # mode maintenance
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up                         # sortie de maintenance

# Frontend
cd ../frontend
npm ci
npm run build
```

Relancer le worker de file si present : `php artisan queue:restart`.

---

## Verification post-mise a jour

- [ ] `GET /up` repond `200` (sonde de sante).
- [ ] Connexion admin fonctionnelle.
- [ ] `php artisan migrate:status` : toutes les migrations `Ran`.
- [ ] Un parcours cle testable (creer un devis, emettre une facture).
- [ ] Logs sans erreur : `storage/logs/laravel.log`.

---

## Rollback

En cas d'anomalie bloquante :

### 1. Revenir a la version precedente

```bash
# Revenir au tag de la derniere version stable connue (notee avant la mise a
# jour ; liste complete : git tag --list). Exemple :
git checkout v1.0.0
```

### 2. Restaurer la base (si des migrations ont ete appliquees)

Les migrations peuvent etre irreversibles : privilegier la **restauration du
dump** pris avant la mise a jour.

```bash
# Docker
docker compose exec -T mysql mysql -u ledge -psecret ledge < backup-AAAA-MM-JJ.sql
# Serveur
mysql -u <user> -p <base> < backup-AAAA-MM-JJ.sql
```

Alternative si la migration fournit un `down()` fiable :
`php artisan migrate:rollback --step=N`.

### 3. Reconstruire

```bash
# Docker
docker compose build && docker compose up -d
# Manuel (depuis la racine du projet)
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache && cd ../frontend && npm ci && npm run build
```

### 4. Restaurer `.env` et `storage/` si modifies

---

## Bonnes pratiques

- Toujours tester la mise a jour sur un environnement de recette avant la production.
- Une **fenetre de maintenance** (`php artisan down`) evite les ecritures concurrentes
  pendant les migrations.
- Conserver au moins les **3 derniers dumps** de base.
- La procedure de gestion des anomalies rencontrees est decrite dans
  [PLAN-CORRECTION-BOGUES.md](PLAN-CORRECTION-BOGUES.md).
