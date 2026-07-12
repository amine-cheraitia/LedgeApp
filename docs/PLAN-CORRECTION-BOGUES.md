# Plan de correction des bogues — Ledge

Processus de detection, qualification et correction des anomalies en production
et en recette (competence RNCP C2.3.2).

## 1. Detection

| Source | Outil | Ce qu'elle remonte |
|---|---|---|
| Erreurs applicatives | **Sentry** (`SENTRY_LARAVEL_DSN`) | Exceptions PHP + contexte (stack, utilisateur, requete) |
| Disponibilite | **UptimeRobot** (`GET /up`) | Indisponibilite, latence, expiration SSL |
| Sante interne | **Laravel Health** (`GET /health`, admin) | BDD, cache, file, disque |
| Logs serveur | `storage/logs/laravel.log` | Traces detaillees, deprecations |
| Utilisateurs | Signalement (email / support) | Comportement inattendu cote metier |

## 2. Qualification (triage)

Chaque anomalie recoit un niveau de severite qui conditionne le delai de prise
en charge :

| Severite | Definition | Cible de correction |
|---|---|---|
| **Critique (P1)** | Service indisponible, perte/corruption de donnees, faille de securite | Immediat — hotfix |
| **Majeure (P2)** | Fonction cle inutilisable, pas de contournement | < 48 h |
| **Mineure (P3)** | Genant mais contournable | Prochaine version planifiee |
| **Cosmetique (P4)** | UI / libelle, sans impact fonctionnel | Backlog |

Une anomalie est tracee (ticket / issue) avec : description, etapes de
reproduction, severite, environnement, capture ou trace Sentry.

## 3. Correction

Le flux suit le Gitflow du projet (voir [GITFLOW.md](GITFLOW.md)) :

1. **Reproduire** l'anomalie localement (idealement via la stack Docker).
2. **Branche** dediee :
   - anomalie en production -> `fix/<slug>` **depuis `main`** ;
   - anomalie detectee en integration -> `fix/<slug>` depuis `develop`.
3. **Test de non-regression** : ecrire d'abord un test qui echoue et reproduit le
   bogue, puis corriger jusqu'a ce qu'il passe.
4. **Verifier** : `php artisan test` (backend) + `npm run test` (frontend) verts,
   `vendor/bin/pint --test` conforme.
5. **Pull Request** avec le template RNCP (description, cause, correctif, tests).
6. **Merge** : un `fix/*` de production est fusionne sur `main` **et** `develop`
   pour eviter la regression a la version suivante.
7. **CHANGELOG** mis a jour (section `Fixed`), nouveau tag si livraison.

## 4. Deploiement du correctif

- Production : voir [MANUEL-MISE-A-JOUR.md](MANUEL-MISE-A-JOUR.md) (sauvegarde +
  application + verification).
- En cas d'echec du correctif : **rollback** (meme manuel, section Rollback).

## 5. Suivi et prevention

- Verifier dans Sentry que l'erreur ne se reproduit plus apres deploiement.
- Post-mortem court pour toute anomalie **critique** : cause racine + mesure
  preventive (test ajoute, garde-fou, documentation).
- Les garde-fous de la CI (tests + seuils de couverture) empechent la
  reintroduction silencieuse d'un bogue corrige.

## 6. Journalisation des actions

Les actions metier sensibles sont tracees via le **journal d'audit**
(spatie/activitylog), utile pour reconstituer le contexte d'une anomalie
(qui a modifie quoi, quand).
