# Manuel de mise a jour — Ledge

Procedure de montee de version d'une instance existante, avec sauvegarde et
rollback. Chaque version stable est identifiee par un tag `vX.Y.Z` (SemVer).

## Avant toute mise a jour

1. **Lire le [CHANGELOG](../CHANGELOG.md)** : ruptures eventuelles, nouvelles
   variables d'environnement, migrations sensibles.
2. **Sauvegarder la base de donnees** (indispensable) :
   ```bash
   # Docker
   docker compose exec mysql mysqldump -u ledge -psecret ledge > backup-$(date +%F).sql
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
git checkout vX.Y.(Z-1)        # version stable precedente
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
# Manuel
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
