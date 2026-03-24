# Historique des versions — Ledge

> Ce document retrace les versions precedentes du projet et la logique metier a reprendre.

---

## V0 — Application monolithique originale

**Repo :** https://github.com/amine-cheraitia/gestion-cabinet-cac-et-cmpta

**Stack :** Laravel 8.54, PHP 7.3/8.0, Blade, jQuery, FullCalendar, DataTables, mPDF, SweetAlert, Laravel ID Generator. Pas de framework frontend JS (tout en Blade + jQuery AJAX).

### Modules implementes

| Module | Detail |
|---|---|
| **Entreprises** | CRUD complet (raison_social, NRC, NIF, art_imposition, adresse, tel, email, regime_fiscal, type_activite, categorie) |
| **Devis** | CRUD + PDF mPDF, numerotation DV{yy}-XXX, calcul prix = indiceFiscal x indiceCategorie x tarifInitial |
| **Missions** | CRUD + planning FullCalendar (drag & drop), liees aux devis, numerotation M{yy}-XXX, champs color/textColor/allDay pour le calendrier |
| **Taches** | CRUD + planning calendrier, assignation user, statut (en cours/acheve), commentaires |
| **Mandats** | Generation auto par mission (MDyy-XXX), PDF |
| **Conventions** | Generation auto par mission (CVyy-XXX), PDF |
| **Factures** | CRUD + avoirs (fact_avoir_id self-ref), types FF/FA, PDF avec NumberFormatter pour montant en lettres, numerotation FF{yy}-XXX / FA{yy}-XXX |
| **Paiements** | CRUD, lies aux factures, suivi creances (factures impayees), planning paiement (tranches 30/30/40) |
| **KPI** | Dashboard admin (CA mensuel, missions en cours/achevees, CA annuel, taches, impayees), dashboard secretaire (impayees, retard 15j, retard 30j), dashboard auditeur (taches perso) |
| **Commentaires** | Sur les taches, CRUD |
| **Users** | Gestion utilisateurs, roles manuels (pas Spatie) |

### Roles (table roles manuelle, pas Spatie)

- Admin
- Secretaire
- Auditeur / Comptable
- Sans role

### Modeles (18)

User, Entreprise, Devis, Mission, Tache, Commentaire, Facture, Paiement, Mandat, Convention, Exercice, Prestation, RegimeFiscal, Categorie, TypeActivite, TypeFacture, TypePaiement, Role

### Ce qui n'existait PAS dans la V0

- Portail client
- Relances automatiques (le suivi creances etait manuel)
- TVA historisee (pas de table tva_rates)
- Timbre fiscal
- Documents / GED
- API REST (tout en Blade monolithique)
- Spatie Permission (roles en dur)
- Tests automatises

---

## Logique metier cle a reprendre de la V0

### Calcul prix devis / mission

```
Prix HT = prestation.tarif_initial x regime_fiscal.indice x categorie.indice
```

### Tranches de facturation

```
Tranche 1 = 30% du total mission
Tranche 2 = 30% du total mission
Tranche 3 = 40% du total mission (solde)
```

### Numerotation des documents

| Document | Format | Exemple |
|---|---|---|
| Devis | DV{yy}-XXX | DV26-001 |
| Mission | M{yy}-XXX | M26-001 |
| Facture | FF{yy}-XXX | FF26-001 |
| Avoir | FA{yy}-XXX | FA26-001 |
| Mandat | MD{yy}-XXX | MD26-001 |
| Convention | CV{yy}-XXX | CV26-001 |

### Planning calendrier

Champs utilises pour FullCalendar sur missions et taches :
- `color` — couleur de fond de l'evenement
- `textColor` — couleur du texte
- `allDay` — evenement journee entiere
- `start` / `end` — dates debut/fin
- `title` — titre composite (numero + entreprise + prestation)

### PDF — Montant en lettres

```php
$formatter = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
$montantEnLettres = $formatter->format($montant);
```

### Creances (factures impayees)

- Factures de type 1 (standard) sans avoir associe et sans paiement enregistre
- Classification par retard : 15-30 jours (en attente), >30 jours (en souffrance)

### Protection suppression

| Entite | Condition de blocage |
|---|---|
| Entreprise | Si devis ou missions associes |
| Facture | Si paiements ou avoirs associes |
| Tache | Si commentaires associes |
| Mission | Si factures associees |

### Dashboards par role

**Admin :**
- CA mensuel (graphique barres, 12 mois)
- Missions en cours / achevees (compteurs)
- CA annuel total (factures type 1 hors avoirs)
- Taches en cours (compteur)
- Factures impayees (compteur)

**Secretaire :**
- Total factures impayees
- Factures en attente de paiement (retard 15-30j)
- Factures en souffrance (retard >30j)

**Auditeur / Comptable :**
- Ses taches en cours (compteur)
- Ses taches achevees (compteur)

---

## V1 — Tentative Filament (abandonnee)

**Repo :** https://github.com/amine-cheraitia/ledge

**Stack :** Laravel 12, Filament v3, Spatie Permission v7.2

### Ce qui avait ete fait

- 18 modeles Eloquent (memes que le projet N-tier actuel)
- 14 migrations (schema BDD identique)
- Seeders (admin, roles, TVA, prestations, exercices, settings)
- Middlewares EnsureBackofficeAccess / EnsurePortailAccess
- 2 panels Filament : AdminPanelProvider (`/admin`) + PortailPanelProvider (`/portail`)
- Docker config (nginx + php + mysql)
- Pas de Resources Filament implementees (abandonne avant)

### Specs prevues dans la V1 (a reprendre dans la V2)

Ces specs etaient documentees dans le CHANGELOG V1 mais jamais implementees. Elles restent valables :

**Facturation :**
- Statut facture recalcule auto : `en_attente -> partiel -> solde`
- Snapshots immuables TVA/timbre a la creation
- Logs immuables (piste d'audit) sur transactions financieres
- PDF avec montant en lettres (NumberFormatter locale fr)

**Entreprises :**
- Observer `MissionCreated` — bascule auto prospect -> client
- Contacts multiples par entreprise avec contact principal

**Planning :**
- 4 statuts taches : `a_faire`, `en_cours`, `termine`, `bloque`
- Generation auto mandat + convention a la creation de mission

**Relances :**
- Observer `InvoicePaid` — annulation auto des relances en cours des paiement solde
- Templates mails avec variables : `{{client}}`, `{{montant}}`, `{{echeance}}`
- Regles parametrables J+15, J+30, J+60 via queue cron quotidien

**Portail client :**
- Scope Eloquent automatique — isolation des donnees par `entreprise_id`
- Action "Activer l'acces portail" depuis fiche Entreprise
- Creation auto User avec role `client` + email d'invitation

### Pourquoi abandonnee

Architecture N-tier (Vue 3 + Laravel API) jugee plus valorisante pour le jury RNCP. Permet un frontend sur mesure avec accessibilite RGAA, responsive mobile-first, et demontre la maitrise d'une stack complete.

Le schema BDD et les modeles ont ete repris tels quels dans le projet actuel (V2).

---

## V2 — Projet actuel (N-tier)

**Repo :** https://github.com/amine-cheraitia/LedgeApp

**Stack :** Vue 3 + TypeScript + PrimeVue 4 (frontend) + Laravel 12 API + Sanctum (backend) + MySQL

Voir [CONTEXT.md](CONTEXT.md) pour le detail complet.
