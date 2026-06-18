# Cahier de recettes — Ledge

> RNCP 39583 · Expert en Développement Logiciel · YNOV — compétence **C2.3.1** (élaboration du cahier de recettes).
> Document **niveau application** : les scénarios fonctionnels traversent la chaîne complète
> **front (Vue 3) → API REST (Laravel) → MySQL**. Couvre les tests **fonctionnels**, **structurels** et **de sécurité**.

## Légende

| Symbole | Signification |
|---|---|
| ✅ | Scénario conforme (validé) |
| 🔁 | Couvert par un test automatisé (PHPUnit back / Vitest front) |
| ⏳ | À exécuter / re-valider |

> **Environnements** — Back : `php artisan serve` (MySQL via WAMP). Front : `npm run dev` (`localhost:5173`).
> Jeu de données : `php artisan migrate --seed`. Tests automatisés : `php artisan test` (back) · `npm test` (front).

---

## 1. Authentification & contrôle d'accès

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| AUTH-01 | Compte admin existant | `GET /sanctum/csrf-cookie` puis `POST /login` (email + mot de passe valides) | 200, cookie de session posé, `GET /me` renvoie l'utilisateur + rôle | 🔁 |
| AUTH-02 | — | `POST /login` avec mot de passe erroné | 422/401, aucune session ouverte | 🔁 |
| AUTH-03 | — | 6 tentatives de login échouées en < 1 min | 6ᵉ requête bloquée par `throttle:5,1` (429) | 🔁 |
| AUTH-04 | Session admin | `POST /logout` | 204, session détruite, token CSRF réinitialisé côté front | 🔁 |
| AUTH-05 | Client connecté | Accès à une route `meta.zone = 'backoffice'` | Redirection / accès refusé (garde Vue Router) | ✅ |
| AUTH-06 | Staff connecté | Accès à une route `meta.zone = 'portail'` | Accès refusé | ✅ |
| AUTH-07 | Secrétaire connectée | Accès Missions / Planning | Redirection `/acces-refuse` (hors périmètre) | 🔁 |

## 2. Référentiel (prestations, exercices, paramètres)

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| REF-01 | Admin | Créer une prestation (code, désignation, tarif) | 201, prestation listée | 🔁 |
| REF-02 | Prestation liée à des missions | Supprimer cette prestation | 409 (protection suppression) | ✅ |
| REF-03 | Admin | `POST /prestations/{id}/calculer-prix` avec régime + catégorie | `prix_ht = tarif × indice_régime × indice_catégorie` (ex. ACMPT 120000 × Réel 1.5 × PME 1.75 = 315000) | 🔁 |
| REF-04 | Admin | Ouvrir un exercice, en ouvrir un second | Un seul exercice « ouvert » à la fois (`Exercice::current()`) | 🔁 |
| REF-05 | Admin | Modifier les paramètres cabinet (NIF, NIS, RIB, logo) | Valeurs persistées, reprises sur les PDF générés ensuite | ✅ |

## 3. Entreprises

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| ENT-01 | Admin/Secrétaire | Créer une fiche (statut **Prospect**) avec NIF/NIS/régime/catégorie | 201, statut = Prospect | 🔁 |
| ENT-02 | — | Créer une fiche avec NIF déjà utilisé | 422 (unicité NIF) | 🔁 |
| ENT-03 | Liste fournie | Rechercher (full-text) + filtrer (statut, wilaya) | Résultats filtrés ; export CSV disponible | ✅ |
| ENT-04 | Entreprise avec devis/missions | Supprimer l'entreprise | 409 (protection suppression) | ✅ |
| ENT-05 | Entreprise + contacts | Marquer un contact comme principal | Un seul contact principal ; il reçoit les relances | 🔁 |
| ENT-06 | Secrétaire | Supprimer une entreprise | Interdit (admin uniquement) | 🔁 |

## 4. Devis

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| DEV-01 | Entreprise + prestation | Créer un devis (1 prestation) | 201, `prix_ht` calculé via la grille, numéro `DV{annee}-{seq}` | 🔁 |
| DEV-02 | Devis brouillon | Tenter d'ajouter une 2ᵉ prestation | Refusé — règle « un devis = une prestation » | ✅ |
| DEV-03 | Devis | Générer le PDF | PDF conforme (en-tête cabinet, NIF/NIS client, montant en lettres) | ✅ |
| DEV-04 | Devis envoyé | Accepter → convertir en mission | Mission créée (`devis_id` renseigné), entreprise bascule Prospect→Client | 🔁 |
| DEV-05 | Secrétaire | Créer/supprimer un devis | Interdit (écriture facturation = admin) ; **envoi** d'un devis autorisé | 🔁 |

## 5. Missions & Tâches

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| MIS-01 | Client + prestation | Créer une mission | 201, `prix_ht` figé à la création, numéro `M{annee}-{seq}` | 🔁 |
| MIS-02 | Première mission d'un prospect | Créer la mission | `MissionObserver` → bascule auto Prospect→Client | 🔁 |
| MIS-03 | Mission avec factures | Supprimer la mission | 409 (protection suppression) | ✅ |
| TAC-01 | Mission | Créer une tâche assignée à un collaborateur | Statut initial `a_faire` | 🔁 |
| TAC-02 | Collaborateur | Lister ses tâches | Ne voit que `assigned_to = lui` | 🔁 |
| TAC-03 | Collaborateur | Changer le statut d'une tâche d'un autre | Interdit (`TachePolicy`) | 🔁 |
| TAC-04 | Tâche avec commentaires | Supprimer la tâche | 409 (protection suppression) | 🔁 |

## 6. Factures & tranches

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| FAC-01 | Mission | Créer une facture | 201, numéro `FF{annee}-{seq}`, statut `en_attente` | 🔁 |
| FAC-02 | Facture créée | Vérifier les tranches | T1 = 30 %, T2 = 30 %, **T3 = solde exact** (`prix_ht − T1 − T2`) ; invariant `T1+T2+T3 == prix_ht` | 🔁 |
| FAC-03 | Facture 2026 | Lire `taux_tva_snapshot` | Taux **en vigueur à la date de facture** (19 %), **immuable** même appelé en 2030 (`TvaTaux::enVigueurLe`) | 🔁 |
| FAC-04 | 1er janvier nouvel exercice | Créer une facture | Séquence réinitialisée à 001 ; pas de doublon en concurrence (`lockForUpdate`) | 🔁 |
| FAC-05 | Facture | Générer le PDF DGI | NIF/NIS/RC, TVA 19/9 %, montant en lettres | ✅ |

## 7. Paiements

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| PAI-01 | Facture `en_attente` | Enregistrer un paiement partiel | Statut → `partiel` (recalcul auto) | 🔁 |
| PAI-02 | Facture `partiel` | Solder | Statut → `solde` | 🔁 |
| PAI-03 | Facture avec relances en cours | Enregistrer le paiement soldant | Event `InvoicePaid` → relances en cours annulées | 🔁 |
| PAI-04 | Facture avec paiements | Supprimer la facture | 409 (protection suppression) | ✅ |

## 8. Avoirs

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| AVO-01 | Facture | Émettre un avoir | Numéro `FA{annee}-{seq}`, `facture_origine_id` renseigné, montants négatifs | 🔁 |
| AVO-02 | Avoirs existants | Onglet « Avoirs » de la page Factures | Liste globale (tous avoirs), badge de comptage | ✅ |
| AVO-03 | Avoir | Télécharger le PDF | PDF de l'avoir généré | ✅ |
| AVO-04 | Secrétaire | Émettre un avoir | Interdit (admin uniquement) | 🔁 |

## 9. Créances & Relances

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| REL-01 | Factures impayées | Page Créances | Liste `en_attente`/`partiel`, total restant dû en évidence, tri ancienneté, export CSV | ✅ |
| REL-02 | Facture impayée | Envoyer une relance manuelle (niveau 1) | Mail au contact principal, entrée au journal des relances | 🔁 |
| REL-03 | Sans relance niveau 1 | Envoyer une relance niveau 2 | Bloqué (séquence des niveaux) | 🔁 |
| REL-04 | Facture soldée | Envoyer une relance | Bloqué | 🔁 |
| REL-05 | Secrétaire | Envoyer une relance | Autorisé | 🔁 |

## 10. Portail client (isolation `entreprise_id`)

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| POR-01 | Client A connecté | `GET /portail/factures` | Ne voit **que** les factures de l'entreprise A (scope strict) | 🔁 |
| POR-02 | Client A | Tenter d'accéder à une facture de l'entreprise B (id direct) | 404 / refusé | 🔁 |
| POR-03 | Client | Télécharger un PDF de facture | PDF de **sa** facture uniquement | ✅ |
| POR-04 | Client | Consulter ses missions + documents `visible_portail` | Lecture seule, uniquement documents partagés | ✅ |

## 11. Dashboard & KPI

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| DSH-01 | Admin | Ouvrir le dashboard | KPI (CA mois, TVA collectée, taux recouvrement), filtre par exercice | ✅ |
| DSH-02 | Données présentes | Graphiques | CA mensuel (barres 12 mois) + répartition missions par statut (camembert) ; table `.sr-only` alternative | ✅ |
| DSH-03 | Secrétaire | Dashboard dédié | Worklist « À faire » orientée recouvrement, aucun volet production | 🔁 |
| DSH-04 | Collaborateur | Dashboard dédié | Ses missions + taux de complétion de ses tâches | 🔁 |

## 12. Journal d'audit

| ID | Préconditions | Étapes | Résultat attendu | Statut |
|---|---|---|---|---|
| AUD-01 | Admin modifie une facture | Consulter `GET /audit-logs` | Entrée tracée (causer, sujet, diff avant/après) | 🔁 |
| AUD-02 | — | Vérifier le contenu loggé | `password` / `remember_token` **jamais** journalisés (`logExcept`) | ✅ |
| AUD-03 | Non-admin | Accéder au journal d'audit | Interdit (admin uniquement) | 🔁 |

---

## 13. Tests structurels

| ID | Vérification | Commande | Résultat attendu | Statut |
|---|---|---|---|---|
| STR-01 | Style backend (PSR) | `vendor/bin/pint --test` | Aucune violation | 🔁 |
| STR-02 | Tests unitaires/fonctionnels back | `php artisan test` | Tous verts | 🔁 |
| STR-03 | Tests logique front | `npm test` (Vitest) | Tous verts (modules API, store auth, composables) | 🔁 |
| STR-04 | Build front | `npm run build` | Compilation sans erreur TypeScript | 🔁 |
| STR-05 | Intégration continue | Pipeline GitHub Actions sur la PR | Jobs Backend + Frontend + garde Gitflow au vert | 🔁 |

## 14. Tests de sécurité (OWASP Top 10)

| ID | Faille visée | Vérification | Résultat attendu | Statut |
|---|---|---|---|---|
| SEC-01 | A01 / A03 — CSRF & injection | Sanctum cookie sur tous les POST ; Eloquent uniquement (jamais `DB::raw()` avec input) | Requête sans token CSRF rejetée ; aucune requête SQL brute | 🔁 |
| SEC-02 | A01 — Contrôle d'accès | Policies Laravel sur chaque ressource (Facture, Devis, Mission, Avoir, User…) | Action hors périmètre → 403 | 🔁 |
| SEC-03 | A07 — Brute force | `throttle:5,1` sur `POST /login` | 429 au-delà du seuil | 🔁 |
| SEC-04 | A05 — Mauvaise configuration | Réponses d'erreur API en `APP_DEBUG=true` | Aucune fuite SQL/host/stack/chemin | 🔁 |
| SEC-05 | En-têtes HTTP | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy | Présents sur les réponses | ✅ |
| SEC-06 | A09 — Journalisation | Exceptions API loguées (URL, méthode, IP, user_id) + Sentry | Contexte structuré présent | ✅ |
| SEC-07 | CORS | `FRONTEND_URL` uniquement, headers restrictifs | Origine non autorisée rejetée | ✅ |
| SEC-08 | XSS | Pas de `v-html` avec donnée utilisateur | Aucune injection rendue | ✅ |

## 15. Accessibilité (RGAA — bonus C2.2.3)

| ID | Vérification | Résultat attendu | Statut |
|---|---|---|---|
| RGA-01 | `aria-label` sur boutons sans texte ; `<label>`/`aria-label` sur inputs | Présents | ✅ |
| RGA-02 | Messages d'erreur `role="alert"` / `aria-live` | Présents | ✅ |
| RGA-03 | Skip link « Aller au contenu principal » + `:focus-visible` | Navigation clavier complète | ✅ |
| RGA-04 | Graphiques `role="img"` + `aria-label` + table `.sr-only` | Alternative lisible par lecteur d'écran | ✅ |
| RGA-05 | Score Lighthouse accessibilité | ≥ 85 | ⏳ |

---

## Synthèse

- **Tests fonctionnels** : 12 domaines couverts, des règles métier critiques (calcul HT immuable, TVA historisée,
  tranches 30/30/40, statut paiement auto, isolation portail) sont **adossées à des tests automatisés** (🔁).
- **Tests structurels** : Pint + PHPUnit (back) + Vitest + build (front), exécutés en **intégration continue**.
- **Tests de sécurité** : couverture OWASP Top 10 (CSRF, accès, throttle, fuite d'erreur, en-têtes, CORS, XSS).
- **Plan de correction des bogues** (C2.3.2) : chaque scénario en échec est consigné (étapes de reproduction,
  cause, correctif) et traité via une branche `fix/*` → PR → CI, conformément au gitflow du projet.
