# Sécurité — Ledge

> Document de suivi sécurité. Couvre la gestion des dépendances vulnérables
> (OWASP A06) et les contrôles transversaux (voir aussi US-36 du BACKLOG).

---

## Composants tiers — advisories connues (OWASP A06)

### État

`composer audit` est exécuté sur le projet et **ne masque aucune advisory** (hormis une
entrée historique non liée à la sécurité applicative). Les vulnérabilités ci-dessous
concernent des composants **Symfony 7.x tirés transitivement par Laravel 12** : aucune
version corrigée n'est disponible dans la plage `symfony/* ^7.2` imposée par le framework
au moment de la rédaction.

> Note importante : ces versions sont présentes depuis l'installation initiale de Laravel 12.
> Elles ne résultent d'aucun choix applicatif de Ledge. Le but de ce document est de les
> **tracer et évaluer**, pas de les faire taire.

### Advisories actives et évaluation d'impact pour Ledge

| CVE | Composant | Nature | Applicabilité à Ledge |
|---|---|---|---|
| CVE-2026-45068 | `symfony/mailer` | Argument injection dans `SendmailTransport` (destinataire préfixé par `-`) | **Non applicable** — aucun transport sendmail. Mail via `log` en dev et **Resend (API HTTP)** en prod (`resend/resend-laravel`). |
| CVE-2026-45067 | `symfony/mime` | Injection d'en-tête / commande SMTP via CRLF dans `Mime\Address` | **Faible** — envoi via l'API Resend (pas de dialogue SMTP direct). Adresses validées par les FormRequests, jamais issues d'input libre non contrôlé. |
| CVE-2026-45070 | `symfony/mime` | Injection d'en-tête e-mail via caractères non-token dans les noms de paramètres MIME | **Faible** — même contexte que ci-dessus ; aucun nom de paramètre MIME construit à partir d'input utilisateur. |
| CVE-2026-45075 | `symfony/http-kernel` | Contournement du filtre `methods: ['GET']` par requête HEAD sur `#[IsGranted]` / `#[IsSignatureValid]` / `#[IsCsrfTokenValid]` | **Non applicable** — l'autorisation repose sur les **Policies Laravel + middleware Sanctum/Spatie**, pas sur les attributs du composant Symfony Security. |
| CVE-2026-45065 | `symfony/routing` | Contournement de contrainte de route via alternance regex non ancrée → injection d'URL `//host` off-site | **Faible** — routing géré par Laravel ; aucune génération d'URL via `UrlGenerator` Symfony avec contraintes de route issues d'input utilisateur. |
| CVE-2026-45304 / 45305 / 45133 | `symfony/yaml` | DoS du parser YAML (« Billion Laughs », ReDoS, épuisement de pile sur YAML imbriqué) | **Non applicable** — `symfony/yaml` est une dépendance **dev** (`laravel/sail`) ; Ledge ne parse aucun YAML fourni par l'utilisateur à l'exécution. |

**Synthèse** : **8 advisories sur 5 paquets** Symfony 7.x. Impact réel sur Ledge **faible à nul**
compte tenu de la configuration (`MAIL_MAILER=log` / Resend, autorisation Laravel native,
routing Laravel, `symfony/yaml` en dépendance dev sans parsing d'input utilisateur). Aucun
vecteur d'exploitation identifié dans les usages actuels de l'application.

### Pourquoi ces advisories ne bloquent ni l'installation ni la CI

- `composer install` (utilisé en local et en CI — `.github/workflows/ci.yml`) installe
  **depuis `composer.lock`** sans refaire de résolution : il ne déclenche pas l'exclusion
  des versions vulnérables.
- Le blocage n'apparaît que lors d'un `composer require` / `composer update` (résolution du
  graphe), comportement attendu tant qu'aucun correctif Symfony 7.x n'est publié.

### Plan de remédiation

1. **Surveiller** les releases de sécurité Symfony 7.x (canal `symfony/symfony` security advisories).
2. Dès qu'une version corrigée entre dans la plage `^7.2` :
   ```bash
   cd backend
   composer update "symfony/*" -W
   composer audit        # doit revenir vierge des CVE ci-dessus
   php artisan test
   ```
3. Mettre à jour ce document (retirer les lignes corrigées) et le `composer.lock`.

---

## Contrôles transversaux

Voir **US-36 (OWASP Top 10)** du `docs/BACKLOG.md` pour le détail des contrôles en place :
CSRF (Sanctum), Eloquent uniquement, FormRequests, throttling login, en-têtes HTTP de
sécurité (CSP…), Policies par ressource, `ApiExceptionRenderer` (A05 — aucune fuite
d'infos serveur), logging structuré + Sentry (A09), et **journal d'audit métier**
(`spatie/laravel-activitylog`, US-47) traçant les actions sur les entités sensibles.
