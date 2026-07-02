# CHANGELOG — Ledge

> Format : [Semantic Versioning](https://semver.org) — `MAJOR.MINOR.PATCH`
> - **MAJOR** : rupture de compatibilite (migration BDD obligatoire)
> - **MINOR** : nouvelle fonctionnalite retro-compatible
> - **PATCH** : correctif sans impact sur le schema

---

## [Unreleased]

### Refonte UI Dashboard + Login (identité navy/slate, light + dark) — feature/refonte-ui-dashboard-login

Refonte visuelle d'après maquettes, appliquée globalement via les tokens partagés (cohérente sur toute l'app), sur les deux modes clair/sombre :
- **Identité navy/slate monochrome** — suppression de l'accent orange. Boutons/CTA/avatar en **slate**, bleu réservé aux focus/liens, couleurs **sémantiques** conservées (bleu = données, vert = succès, **ambre** = alerte/recouvrement, rouge = danger).
- **Preset PrimeVue figé** (`main.ts`, `definePreset`) : `primary` = surface (slate/navy) ; **surface = slate pour les deux modes** (le dark passait par le gris `zinc` d'Aura → désormais **navy**). Le **configurateur de thème** runtime (bouton palette) est retiré ; seul le toggle clair/sombre (icône ambre) demeure.
- **Cartes** (`.card`, KPI, hero) : coins plus **arrondis** (14px) + **ombre douce au repos** (tokens `--ledge-shadow-card`), fond canvas gris clair / **navy** en dark, cartes surélevées (blanc / slate-800).
- **Sidebar / topbar** : navy en dark, item actif en **pastille slate** (fini l'orange), avatar slate, focus **bleu** visible (RGAA) en clair et sombre.
- **Login** : carte du formulaire **blanche + ombre** en clair (n'existait qu'en dark), coins 14px, **CTA slate** (au lieu d'orange), FAB/focus sans orange, dégradé navy du panneau de marque conservé.
- **Logo** (`LedgeLogo`) unifié sur le **damier 2×2** (cohérent login + PDF) au lieu du sceau circulaire.
- Contrôles : `vue-tsc` ✓, `vite build` ✓, 122 tests unitaires ✓.

### Refonte des PDF (facture, devis, avoir) — feature/facture-date-exercice-et-retrait-notes

Nouveau design des documents PDF, cohérent sur les trois types, avec une couleur d'en-tête distincte par document :
- **En-tête** : bande à **dégradé** (police « Helvetica Neue »/Helvetica), logo damier 2×2 « Ledge » + sous-titre = nom du cabinet (paramètre `cabinet_nom`), titre du document + N° + **pilule de statut à liseré**. Couleurs : **facture = bleu nuit**, **devis = teal**, **avoir = gris ardoise**.
- **Cartes** « Informations » / « Destinataire » de **même largeur et même hauteur** (cellules `<td>` stylisées), valeurs alignées à droite.
- **Tableau** à en-tête coloré, **TOTAL TTC** dans un bloc arrondi, mention en lettres, **pied de page centré** (cabinet + adresse + NIF/NIS/agrément, depuis les paramètres). Sections « Signatures » et « Observations » retirées.
- **Spécifique devis** : ligne « **Validité du devis : jusqu'au JJ/MM/AAAA** », statut (brouillon/envoyé/accepté/refusé/expiré), code + désignation + description de la prestation.
- **Spécifique avoir** : **facture d'origine**, mission, **motif** (section dédiée), « TOTAL AVOIR TTC », destinataire repris de la facture d'origine.
- Rendu compatible **dompdf v3.1.5** (mise en page par tables, `border-radius` et `linear-gradient` supportés). Aucune donnée/contrôleur modifié (mêmes variables passées par `PdfService`).

### Facturation : date bornée à l'exercice + retrait du champ Notes — feature/facture-date-exercice-et-retrait-notes

#### Date de facturation bornée à l'exercice + messages explicites
- **Backend** (`StoreFactureRequest`) : `date_facture` doit désormais tomber **dans l'exercice choisi** (`exercice_id` fourni, sinon `Exercice::current()`) entre `date_ouverture` et `date_cloture`, sinon **422** avec un message explicite « La date de facturation doit être comprise dans l'exercice {annee} (du JJ/MM/AAAA au JJ/MM/AAAA). ». Ajout de `messages()`/`attributes()` FR pour tous les champs (fini les « The date_facture field… »).
- **Frontend** (`FactureListPage.vue`) : le `DatePicker` de la facture est borné (`:minDate`/`:maxDate`) à l'exercice sélectionné — impossible de choisir une date hors exercice ; un `watch` ramène la date dans la plage au changement d'exercice.
- **Tests** : `date_facture` hors exercice → 422 ; le test « sans taux TVA en vigueur » utilise désormais un exercice 2022 dédié (cohérent avec la nouvelle borne).

#### Suppression du champ « Notes » des factures et devis
Le champ `notes` (qui n'apparaissait que sur les PDF en « Observations ») est **retiré partout** pour les **factures** et **devis** : migration `dropColumn('notes')` (factures + devis), `$fillable` des modèles, FormRequests, `FacturationService` (`creerFacture`/`creerDevis`), Resources, **PDF Blade** (section Observations + CSS), factories, et côté frontend (types `Facture`/`Devis`, modules API, `useDevis`, formulaires `FactureListPage`/`DevisListPage`, tests). Les champs `notes` distincts de **paiement**, **entreprise** et **mission** sont **conservés**.

#### PDF facture
- Retrait de la **section « Signatures »** (et de son CSS) sur le PDF de facture.

#### Correctif annexe (dashboard secrétaire)
- `DashboardService::compterEncaissements` : les encaissements du mois affichaient **0 le dernier jour du mois** (un `whereBetween` de dates excluait un `date_paiement` comparé comme datetime). Remplacé par `whereYear`+`whereMonth` (pattern déjà utilisé pour les factures du mois). Bug préexistant.

### Numérotation des factures sans trou & suppression conforme — feature/facture-suppression-conforme-numerotation

Aligne la suppression de factures sur les règles de conformité (numérotation séquentielle **continue**, annulation par **avoir**).

- **Réutilisation du numéro de la dernière facture** (`FacturationService::supprimerFacture`) : la suppression d'une facture autorisée passe d'un **soft delete** à un **hard delete** (`forceDelete`). Le `numero` est physiquement libéré, donc réutilisé par le générateur `MAX(numero)+1` — supprimer FF{annee}-003 puis recréer redonne **FF{annee}-003** (avant : un trou → 004, car la ligne soft-deletée restait comptée dans le `MAX` via `DB::table()`).
- **Blocage de la suppression d'une facture non-dernière** : seule la dernière facture de la séquence (préfixe+exercice) peut être supprimée. Sinon → **409** avec un message invitant à **créer un avoir (FA)** pour annuler la facture sans casser la numérotation. Blocage également si la facture a des **paiements** (message enrichi) ou un **avoir** rattaché (nouveau).
- **Frontend** (`FactureListPage.vue`) : un refus de suppression (auparavant silencieux) s'affiche désormais dans une **popup/modal** dédiée (« Suppression impossible ») qui reste ouverte jusqu'à fermeture, laissant le temps de lire le message et sa suggestion d'avoir.
- **Tests** (`FactureApiTest`) : suppression de la dernière facture → numéro réutilisé ; suppression d'une facture non-dernière → 409 + suggestion d'avoir ; facture avec avoir → 409.

### Correctifs gestion des taux de TVA — fix/tva-validation-dates-libelle-categorie

- **Libellé de catégorie figé corrigé** (`TvaTauxPage.vue`) : dans la modale, les options du sélecteur « Categorie » affichaient un pourcentage **hardcodé** (« Standard (19%) ») déconnecté du champ « Taux (%) » — en éditant un taux à 35 %, la catégorie restait « Standard (19%) ». `typeOptions` devient un `computed` qui suit le taux saisi → **« Standard (35%) »** (Exonéré reste 0 %), mis à jour en direct.
- **Date de fin antérieure à la date de début empêchée côté UI** (`TvaTauxPage.vue`) : le `DatePicker` de fin reçoit `:minDate="form.date_debut"` (jours antérieurs non sélectionnables) ; un `watch` remet la date de fin à `null` si l'on recule la date de début après une fin déjà choisie. Le backend rejetait déjà ce cas (`after_or_equal:date_debut`) — la contrainte UI évite désormais la saisie invalide en amont.
- **Tests** : `TvaTauxApiTest` couvre désormais aussi le **chemin update** (PUT avec `date_fin` < `date_debut` → 422), en complément du test de création existant.

### Sécurité — Invitation par lien & définition de mot de passe en libre-service — feature/invitation-definition-mot-de-passe

L'administrateur ne manipule, ne voit ni ne transmet plus **aucun mot de passe** — ni à l'activation d'un accès client, ni à la création d'un collaborateur/secrétaire. Chaque utilisateur **définit lui-même** son mot de passe via un lien d'invitation sécurisé reçu par email. Implémente enfin le « email set-password » décrit de longue date (US-29, CLAUDE.md) mais jamais codé.

#### Avant / Après
- **Avant** : l'activation portail générait `Str::random(12)` **renvoyé en clair** dans la réponse JSON et affiché à l'admin ; la création d'un staff exigeait que l'admin **saisisse** le mot de passe. L'admin connaissait donc le secret et devait le transmettre manuellement.
- **Après** : le compte est créé avec un mot de passe placeholder **aléatoire (40 car.) inutilisable** ; un email d'invitation contenant un **lien à usage unique et expirable** est envoyé. Aucun mot de passe ne transite jamais.

#### Backend
- **`InvitationService`** (service transverse) : `inviter(User)` génère un jeton via le broker natif (`Password::createToken`), envoie l'email et retourne l'URL `FRONTEND_URL/definir-mot-de-passe?token=…&email=…` (repli copiable, **sans mot de passe**) ; `envoyerReinitialisation(User)` pour le libre-service.
- **`PasswordController`** (routes publiques) : `POST /api/v1/forgot-password` (réponse **générique** anti-énumération d'emails) et `POST /api/v1/reset-password` (`Password::reset` + `Password::defaults()` + `confirmed`). Les deux **throttlées** (`throttle:6,1`).
- **Mailables** `InvitationCompteMail` / `ReinitialisationMotDePasseMail` + templates Blade `mail/invitation`, `mail/reset-password` (bouton + lien de repli, mention d'expiration).
- **`PortailService::activerPortail`** : ne renvoie plus `temporary_password` mais `invitation_url` ; `renvoyerInvitation()` ajouté. **`UserController::store`** passe par `StoreUserRequest` (plus de champ `password`), crée le compte et déclenche l'invitation ; `update` ne permet plus de fixer un mot de passe ; `renvoyerInvitation()` ajouté.
- Routes admin de renvoi : `POST /api/v1/users/{user}/renvoyer-invitation`, `POST /api/v1/entreprises/{entreprise}/renvoyer-invitation`.
- **Config** : `auth.passwords.users.expire` piloté par `AUTH_PASSWORD_RESET_EXPIRE` (défaut **1440 min / 24 h**, confortable pour une invitation) ; `app.frontend_url` (`FRONTEND_URL`) pour construire les liens.

#### Frontend
- Nouvelles pages publiques `DefinirMotDePassePage.vue` (`/definir-mot-de-passe`, sert invitation **et** réinitialisation) et `MotDePasseOubliePage.vue` (`/mot-de-passe-oublie`). RGAA : `role="alert"`/`aria-live`, labels, focus visible.
- `LoginPage.vue` : le lien « Mot de passe oublié ? » pointe désormais vers le vrai flux libre-service (fin de l'impasse « contactez l'administrateur »).
- `EntrepriseListPage.vue` / `UserListPage.vue` : suppression de l'affichage du mot de passe en clair ; après activation/création, dialog **« Invitation envoyée »** avec **lien copiable** (repli) ; action **« Renvoyer l'invitation »** par ligne. Champ mot de passe retiré du formulaire utilisateur.
- Module API `auth.ts` (`forgotPassword`, `resetPassword`) ; `users.ts` / `entreprises.ts` adaptés (`invitation_url`, `renvoyerInvitation`, retrait de `password`).

#### Tests
- `UserInvitationTest` : création staff → invitation envoyée, **aucun mot de passe** exposé, rôle assigné, champ `password` ignoré, rôle obligatoire (422), renvoi d'invitation, non-admin interdit (403).
- `PasswordResetTest` : forgot envoie un lien si le compte existe, **réponse générique** sinon, **throttling** (429) ; reset définit le mot de passe, rejette un token invalide (422), exige robustesse + confirmation.
- `PortailAccessTest` : activation **n'expose plus** de mot de passe et **envoie une invitation** ; renvoi d'invitation au client existant. **273 tests** au vert.

#### Note exploitation
- Le choix du mot de passe par l'utilisateur transite par `POST /reset-password` : **HTTPS obligatoire en production** (le dev local reste en HTTP). Lien d'invitation/réinitialisation valable 24 h, à usage unique.

### Robustesse suppression de mission — fix/robustesse-suppression-mission

- **Échec silencieux corrigé** (`useMissions.ts`) : la suppression d'une mission bloquée par le backend (HTTP **409** « mission avec factures associées ») n'affichait **aucun retour** à l'utilisateur — `deleteMission` n'avait pas de `try/catch`, la promesse rejetée était avalée par le callback de confirmation et la mission restait dans la liste sans explication. Désormais le **message métier du backend** est remonté dans un toast d'erreur (même pattern que `deleteTache`), avec message de repli si le backend n'en fournit pas. Aucun toast de succès ni rafraîchissement de liste en cas d'échec.
- **Confirmation explicite** (`MissionListPage.vue`) : la boîte de confirmation de suppression prévient désormais que **toutes les tâches et leurs commentaires seront également supprimés** et que les **documents associés seront conservés** (détachés de la mission), pour que l'utilisateur mesure la portée de l'action avant de valider.
- **Commentaires de tâches orphelins corrigés** (`MissionService::supprimerMission`) : la mission étant **soft-deletée**, ni les events Eloquent ni le `cascadeOnDelete` SQL ne se déclenchaient ; les tâches étaient soft-deletées mais leurs **commentaires restaient actifs** (orphelins pointant vers une tâche supprimée). Ils sont désormais soft-deletés explicitement dans la même transaction.
- **Documents rattachés à une mission supprimée corrigés** (`MissionService::supprimerMission`) : le soft-delete n'activait pas le `nullOnDelete` de la FK `documents.mission_id`, laissant des documents pointer vers une mission disparue. Ils sont désormais **détachés** (`mission_id = null`) — sans suppression, puisqu'ils restent rattachés à l'entreprise — conformément à l'intention du schéma.
- **Tests** : front (`useMissions.test.ts`) succès + échec **409** + message de repli ; back (`MissionApiTest`) la suppression soft-delete les tâches **et** leurs commentaires, et **détache** les documents sans les supprimer.

### Correctifs page Planning & accès aux tâches par rôle — fix/correctifs-planning

Correctifs issus des tests utilisateur sur la page Planning : affichage, vues, comportement **différencié par rôle**, et règles métier d'affectation / isolation des tâches désormais **appliquées par le backend** (défense en profondeur).

#### Frontend — Planning par rôle
- **Collaborateur** (`PlanningCalendarPage.vue` / `usePlanning.ts`) : la page n'affiche plus qu'**un seul calendrier = ses tâches** (pas d'onglets), **colorées par priorité** (Faible → Urgente, 4 niveaux — voir correctif ci-dessous), avec une **légende priorité**. Calendrier **non éditable** (ni drag ni resize). Il ne voit jamais les tâches des autres.
- **Admin** : conserve les onglets **Missions** (calendrier des missions) et **Équipe**. Dans l'onglet Équipe, **cliquer sur la tâche d'un collaborateur ouvre le même modal** que sur le calendrier des missions (chip rendu en `<button>` accessible).
- **Onglet Missions** (`usePlanning.ts`) : le calendrier n'affiche que les **missions** (les tâches restent dans l'onglet Équipe et les fiches mission).
- **Quatre vues** (`PlanningCalendarPage.vue`) : **Année / Mois / Semaine / Liste**, libellés FR — partout (Missions admin et planning collaborateur).
- **Décalage d'un jour corrigé** (`usePlanning.ts`) : la fin des barres (`allDay` exclusif côté FullCalendar) est ajustée (+1 jour à l'affichage, −1 à la persistance d'un redimensionnement) ; une mission 24 → 26 couvre bien 24/25/26.
- **Détail mission / tâche** : un collaborateur ne voit que **ses** tâches dans la fiche mission ; le **formulaire de commentaire est masqué** sur une tâche qui ne lui est pas affectée ; il ne peut **ni modifier ni supprimer** un commentaire qui n'est pas le sien.
- **Sélecteur d'affectation** (`MissionDetailPage` / `TacheDetailPage`) : ne liste que les **administrateurs et collaborateurs** (`useUsers.fetchUsers({ role: ['admin','collaborateur'] })`).
- **Toasts d'erreur** : le message du backend (422) est remonté lors d'un drag/resize hors bornes au lieu d'un message générique.

#### Backend — isolation des tâches par rôle
- **`Tache::scopeVisiblePour(User)`** : scope réutilisable — l'admin voit tout, le collaborateur uniquement les tâches qui lui sont affectées (`assigned_to`).
- **`TachePolicy::view`** : admin, ou collaborateur affecté à la tâche. Appliquée sur :
  - `TacheController@index` (liste scopée via `visiblePour`) et `@show` (**403** si tâche non affectée) ;
  - `MissionController@show` (chargement des tâches scopé) ;
  - `TacheCommentaireController@index` / `@store` (**403** : un collaborateur non affecté ne peut ni lister ni poster de commentaire).
- **Immutabilité des commentaires** : `update` / `destroy` restent limités à l'**auteur ou à un admin** (`TacheCommentairePolicy`, inchangé).
- **`ValidatesTacheDates`** (trait mutualisé `StoreTacheRequest` / `UpdateTacheRequest`) :
  - `date_debut` ≥ **début de la mission** ; `date_echeance` ≤ **fin de la mission** — **sauf si la mission est en retard**, auquel cas l'échéance peut la dépasser. Rejet **422** + messages FR.
  - `assigned_to` : une tâche ne peut être affectée qu'à un **collaborateur ou un administrateur** (jamais à la secrétaire). Rejet **422**.

#### Frontend (API)
- **`UserFilters.role`** : accepte `string | string[]` (`useUsers.fetchUsers` accepte un override de filtres sans changer le comportement par défaut de `UserListPage`).

#### Tests
- `TacheApiTest` : le collaborateur **ne voit que ses propres tâches** (l'admin les voit toutes) ; **403** à la consultation et au commentaire d'une tâche non affectée ; **201** au commentaire de sa propre tâche ; **403** à la modification/suppression du commentaire d'un admin (immutabilité) ; affectation secrétaire → 422 ; collaborateur → 201 ; bornes de dates `date_debut`/`date_echeance` → 422 ; échéance au-delà de la fin **autorisée** si mission en retard.

#### Correctifs complémentaires (priorités, décalage de date, conflit d'affectation)
- **Priorités cohérentes — 4 niveaux** : la légende du planning et le Dashboard affichaient des niveaux inventés (5 niveaux « Très faible → Critique » côté planning, mapping 3 niveaux cassé au Dashboard). Le système n'a que **4 priorités : Faible / Normale / Haute / Urgente**. Centralisation dans une source de vérité unique `frontend/src/utils/priorite.ts` (libellés, severities, couleurs alignées sur les badges PrimeVue — gris/bleu/ambre/rouge) réutilisée par `usePlanning.ts`, `PlanningCalendarPage.vue`, `TacheDetailPage.vue`, `MissionDetailPage.vue` et `DashboardPage.vue`. Backend : `StoreTacheRequest` / `UpdateTacheRequest` bornent désormais `priorite` à **`max:4`** (au lieu de `max:5`).
- **Décalage d'un jour à la saisie corrigé** : une échéance saisie au 7 juin était enregistrée au 6 juin. Cause : `toIsoDate()` passait par `Date.toISOString()` (UTC), repassant à la veille en UTC+1. Nouveau helper `frontend/src/utils/date.ts` (`toIsoDate` / `parseIsoDate`) formatant à partir des composants **locaux** ; appliqué à la saisie et au pré-remplissage des formulaires (`TacheDetailPage.vue`, `MissionDetailPage.vue`).
- **Alerte de conflit d'affectation réactive (non bloquante)** : à la création/édition d'une tâche, si le collaborateur choisi a déjà une tâche qui **chevauche** la période saisie (toutes missions confondues), un avertissement s'affiche en temps réel (au changement de dates **ou** de collaborateur) ; l'enregistrement reste autorisé. Backend : `Tache::scopeChevauche()` (chevauchement `COALESCE`, bindings), `MissionService::detecterConflitsTache()`, `TacheController@conflits` (admin only via `MissionPolicy::create`), `CheckTacheConflitsRequest`, `ConflitTacheResource`, route `GET /api/v1/taches/conflits`. Frontend : `tachesApi.conflits`, composable `useTacheConflits` (debounce + anti-course), encart d'avertissement `role="status"` / `aria-live="polite"` dans les 3 dialogues (création + édition mission, édition tâche).

#### Tests (correctifs complémentaires)
- `TacheApiTest` : `priorite = 5` → **422**, `priorite = 4` → **201** ; conflit détecté sur chevauchement, **0** hors période, exclusion de la tâche courante (`exclude_tache_id`), détection **inter-missions** ; `collaborateur_id` manquant / aucune date → **422** ; endpoint **interdit au collaborateur** → 403.

### Tâches — date de début & affichage en plage sur le planning — feature/tache-date-debut

Une tâche peut désormais porter une **date de début** en plus de son échéance ; sur le planning elle s'affiche en **plage** (barre début → échéance) au lieu d'un simple point.

#### Backend
- **Migration** `add_date_debut_to_taches_table` : colonne `date_debut` (nullable) sur `taches`.
- **`Tache`** : `date_debut` ajouté au `$fillable` et casté en `date`.
- **`StoreTacheRequest` / `UpdateTacheRequest`** : `date_debut` `nullable | date | before_or_equal:date_echeance`.
- **`TacheResource`** : expose `date_debut`.
- **`CalendarService::fetchTaches()`** : filtre par **chevauchement de plage** (`COALESCE(date_debut, date_echeance)` vs fenêtre, en **bindings** — pas de `DB::raw` avec input utilisateur) au lieu de la seule échéance ; une tâche dont la plage croise la fenêtre apparaît même si ni début ni échéance n'y tombent.

#### Frontend
- **Planning** (`usePlanning.ts`) : une tâche avec début **et** échéance s'affiche en **barre** ; un seul jour → **point**. Le glisser-déposer décale **les deux dates** ; le redimensionnement ajuste l'**échéance**.
- **Popup tâche** (`PlanningCalendarPage.vue`) : ligne **« Début »** ajoutée ; échéance null-safe.
- **`MissionDetailPage` / `TacheDetailPage`** : sélecteur **Date de début** (création + édition) et affichage.

#### Tests
- `TacheApiTest` : création avec `date_debut`, rejet si `date_debut > date_echeance`.
- `CalendarApiTest` : tâche dont la **plage chevauche** la fenêtre (début avant / échéance après).

### Refonte page Planning — feature/refonte-planning

Refonte complète de la page Planning : navigation par onglets, vue annuelle, et nouvelle vue **Équipe** (charge / disponibilité par collaborateur).

#### Frontend
- **`PlanningCalendarPage.vue`** : refonte en deux onglets — **Calendrier** (missions & tâches) et **Équipe**.
  - **Vue annuelle par défaut** (12 mois) via le plugin `@fullcalendar/multimonth`, + vues Mois / Semaine / Jour / Liste.
  - **Loader overlay** pendant le chargement ; bouton **« Nouvelle mission »**.
  - **Légende dynamique** par prestation (palette de couleurs) ; bordures de couleur par statut de mission.
- **Onglet Équipe** (`usePlanning.ts`) : **grille de disponibilité** collaborateur × jour de la semaine, charge colorée (**Disponible / Modéré / Chargé**), navigation semaine précédente / suivante.
- Filtre collaborateur **retiré de l'onglet Missions** (remplacé par la vue Équipe).

#### Backend
- **`CalendarService`** : expose `prestation_id` / `prestation_code` (légende par prestation) et `assigned_to` (grille Équipe) ; `planning.ts` typé en conséquence.

#### Dépendances
- Ajout de **`@fullcalendar/multimonth`** (vue annuelle 12 mois).

### Missions — visibilité collaborateur, priorisation & refonte table — feature/fix-mission-list

Affinements du module Missions : un collaborateur assigné à une tâche voit la mission parente, ses missions en cours sont priorisées, et la table missions est repensée.

#### Backend
- **`MissionService::listerMissions()` + `MissionPolicy::view()`** : un collaborateur **assigné à une tâche** d'une mission (mais absent de `mission_user`) voit désormais la mission, ses tâches et ses commentaires (`whereHas('taches', assigned_to)` en OR de `whereHas('collaborateurs')`).
- **`MissionService::listerMissions()`** : pour le collaborateur, les missions **en cours** remontent en tête de liste (tri statut puis date).
- **`TacheCommentaireController::store()`** : le **1er commentaire** sur une tâche `a_faire` la fait passer en `en_cours` ; une tâche `terminee`/`annulee` n'est pas réactivée.

#### Frontend
- **`MissionListPage.vue`** : refonte de la table missions — colonnes #, N° de mission, Raison sociale, Prestation, Date de début, **Date de fin** (triable), Statut (libellés lisibles), Actions sur une ligne ; colonne Prix HT retirée ; icône « voir » → `pi-eye` ; responsive (Date de fin masquée < 900px, toolbar empilée < 640px).
- **`TacheDetailPage.vue`** : reflète le passage automatique en `en_cours` au 1er commentaire.
- **`fix(ci)`** : `filters.page` potentiellement `undefined` (TS18048) corrigé.

#### Tests
- `MissionApiTest` : priorisation des missions en cours pour le collaborateur.
- `TacheApiTest` : 1er commentaire sur tâche `a_faire` → `en_cours` ; commentaire sur tâche `terminee` ne change pas le statut.

### Refonte UX encaissements — feature/ux-encaissements-drawer

Remplace le Dialog paiement minimaliste par un **Drawer latéral** complet avec historique, validation, dark mode et mise à jour temps réel.

#### Backend
- **`PaiementController`** : `destroy()` supporte désormais la suppression par la secrétaire sur ses propres saisies (`admin OR recorded_by === user.id`) ; garde anti-dépassement dans `store()` (422 si montant > restant dû).
- **`PaiementResource`** : expose `recorded_by_name` (via eager-load `recordedBy`) pour l'affichage « par X ».
- **Routes** : `DELETE /factures/{id}/paiements/{p}` déplacée du groupe `admin` vers `admin|secretaire`.

#### Frontend
- **`FactureDetailDrawer.vue`** (nouveau) : panneau latéral droit — historique des paiements, étape de confirmation, validation temps réel, dark mode.
  - Validation formulaire : montant ≤ 0, négatif ou > restant dû → message `role="alert"` (RGAA C4.1.3).
  - Mise à jour instantanée du badge et des montants (ENCAISSÉ / RESTE DÛ) sans fermer le Drawer via `updateLocalTotals()`.
  - Dark mode : tokens CSS adaptatifs (`--p-surface-ground` pour les lignes, nuances `300` pour les couleurs sémantiques).
- **`FactureListPage.vue`** : bouton `pi-wallet` ouvre le Drawer ; Dialog paiement supprimé.

#### Tests
- `PaiementApiTest` (13 cas) : droits (admin / secrétaire / collaborateur), validations métier (0 / négatif / dépassement / déjà soldé), `InvoicePaid`, suppression avec droits fins.
- `FactureDetailDrawer.test.ts` (17 cas) : affichage, dark mode classes, navigation, validation RGAA, permissions delete.
- `PaiementFactory` créée.

### Durcissement anti-abus (throttle envoi mail / PDF) — chore/durcissement-throttle-mail

Suite à l'audit SOLID / RGAA / OWASP : le code est conforme ; on ajoute une limitation de débit (OWASP A04) sur les actions
sortantes coûteuses, plus un nettoyage DRY.

- **Throttle** : `throttle:6,1` sur les envois de mail (`POST /devis/{id}/envoyer`, `/factures/{id}/transmettre`,
  `/factures/{id}/relances`) et `throttle:30,1` sur les routes **PDF** (back-office + portail) → empêche le spam
  (quota Brevo) et la surcharge CPU ; `ApiExceptionRenderer` renvoie déjà un **429** propre.
- **DRY** : messages d'envoi mail centralisés dans `App\Mail\MailMessages` (constantes partagées par
  `FacturationService` et `RelanceService`).
- **Tests** : `RelanceApiTest` — 429 au-delà de 6 envois/min.

### Envoi des devis & factures par mail (US-44) — feature/envoi-devis-mail

Permet à l'admin/secrétaire d'**envoyer un devis** ou de **transmettre une facture** au client par mail, avec le **PDF en pièce jointe**.

#### Backend
- **Mailables** `DevisMail` / `FactureMail` (calqués sur `RelanceClientMail`) : sujet, vue partagée `mail.document`,
  **PDF généré à la volée** via `PdfService` (`Attachment::fromData(... ->output())`, sans stockage disque).
- **`Entreprise::emailDestinataire()`** : destinataire = email du **contact principal**, à défaut l'email de l'entreprise —
  **source unique** utilisée par les devis, les factures **et les relances** (`RelanceService` aligné, manuelle + automatique).
  Si **ni l'un ni l'autre** n'est renseigné, message clair standardisé « Cette entreprise n'a pas d'adresse mail… » (popin) ;
  le toast de succès des créances affiche le **vrai destinataire** (`email_destinataire`).
- **`FacturationService`** : `envoyerDevis()` envoie désormais le mail **puis** passe le statut à `envoye` (refus `409` si l'entreprise
  n'a pas d'email — statut inchangé) ; `transmettreFacture()` (nouveau) transmet la facture sans changer de statut.
- **API** : `POST /factures/{id}/transmettre` (`FactureController::transmettre` + `FacturePolicy::transmettre`, **admin + secrétaire**) ;
  `POST /devis/{id}/envoyer` (existant) envoie maintenant le mail. `DomainException → 409`.
- **Config** : `MAIL_*` piloté par `.env` (Mailpit en dev, **Brevo** en démo) — aucun changement de code pour switcher.

#### Frontend
- Bouton **« Envoyer par mail »** (devis brouillon) et **« Transmettre par mail »** (facture, admin+secrétaire) avec **confirmation**,
  `aria-label`, toasts de succès/erreur (`useDevis.envoyerDevis` / `useFactures.transmettreFacture`, module `factures.transmettre`).

#### Tests
- `DevisApiTest` : envoi → mail `DevisMail` au bon destinataire **avec pièce jointe PDF** + statut `envoye` ; sans email → `409` (statut inchangé).
- `FactureApiTest` : transmission → mail `FactureMail` + pièce jointe ; sans email → `409` ; **secrétaire autorisée, collaborateur `403`**.

### Gestion des taux de TVA (US-51) — feature/gestion-taux-tva

Donne à l'admin la main sur les taux de TVA (sans accès BDD) et permet de facturer en **exonéré**.

#### Backend
- **CRUD des taux** (admin) : `GET|POST /api/v1/referentiels/tva-taux`, `PUT|DELETE /referentiels/tva-taux/{id}` —
  `ReferentielTvaController` (mince) + `TvaTauxService` (logique métier, SRP) + `TvaTauxPolicy` (admin) +
  `Store/UpdateTvaTauxRequest` (messages FR, borne `taux` 0–100) + `TvaTauxResource`.
  Suppression **bloquée (409)** si des factures référencent le taux.
- **Règle « toujours un taux actif par type »** : il doit rester en permanence **≥ 1 taux Standard actif en vigueur**
  **et** **≥ 1 taux Exonéré actif en vigueur**. Désactiver, changer le type ou supprimer le **dernier** taux utilisable
  d'un type est **bloqué (409)** — sinon la facturation se retrouverait sans taux applicable.
- **`actif` devient significatif** : `TvaTaux::enVigueurLe($date, $type)` ne résout plus que les taux **actifs**
  (un taux désactivé n'est jamais appliqué). Helpers `estActifEnVigueur()` / `existeAutreActifEnVigueur()`.
- **Résolution déterministe (priorité au plus récent)** : `enVigueurLe` applique le taux dont la `date_debut` est la plus
  proche (≤) de la date du document, **départage stable par `id`** si deux taux partagent la même date (plus de choix
  arbitraire de MySQL → fin du conflit affiché/appliqué sur devis et factures). Le composable client `useTvaTaux` suit la même règle.
- **Interdiction de deux taux actifs du même type le même jour** : à la création **et** à l'édition, le service refuse
  (`409`) si un autre taux actif du type commence déjà ce jour-là. La **clôture automatique** du taux précédent est désormais
  appliquée aussi à l'**édition** d'une `date_debut`/type (re-versionnement — avant, éditer une date « ne changeait rien »).
- **Choix de catégorie à la création** : `type_tva` (`standard` | `exonere`) dans `StoreFacture`/`StoreDevisRequest` ;
  `FacturationService::creerFacture()`/`creerDevis()` résolvent le taux via `TvaTaux::enVigueurLe($date, $type)`
  (exonéré → TVA 0, TTC = HT). La **valeur reste fixée par la date** (historisation, snapshots immuables).
- **Seeder** : taux **Exonéré 0%** ajouté, **Réduit 9%** retiré (hors activité).

#### Frontend
- Page **`/tva-taux`** (admin) : DataTable + dialog (catégorie Standard/Exonéré, taux, désignation, dates, actif),
  garde 409 affichée en toast ; entrée menu sous **Administration**.
- Sélecteur **« Catégorie TVA »** sur les formulaires facture/devis : affiche **« Standard (X %) »** où X est le
  **taux actif en vigueur** à la date du document (miroir client `useTvaTaux.tauxEnVigueur`), recalculé quand la date change ;
  **« Exonéré (0 %) »**. Cohérent car la règle ci-dessus garantit un taux courant unique et défini par type.
- Module `referentiels.ts` + composable `useTvaTaux` + type `TvaTaux`.

#### Fiabilisation TVA des devis + cas limites
- **Snapshot du taux sur le devis** : nouvelles colonnes `devis.taux_tva` + `devis.tva_taux_id` (parité avec les factures),
  renseignées à la création et exposées par `DevisResource`. Le **PDF devis** affiche désormais le **taux réel**
  (`TVA (X%)`) au lieu de « 19% » codé en dur — un devis exonéré imprime « TVA (0%) ».
- **Plus de 0% silencieux** : `FacturationService` centralise la résolution dans `resoudreTvaTaux()` ; si aucun taux
  **standard** n'est en vigueur à la date, la création **échoue (409)** avec un message clair au lieu d'appliquer 0% en douce
  (l'exonéré reste à 0 sans erreur). `DevisController::store` capture désormais `DomainException` (aligné sur facture).
- **Édition de devis cohérente** : modifier la prestation recalcule `montant_ht`/`montant_tva`/`montant_ttc`
  (le taux figé du devis est conservé).
- **Modal & fuseau horaire** : la date du devis est pré-remplie à aujourd'hui (le taux courant s'affiche d'emblée, comme la facture) ;
  `toIsoDate` envoie la **date locale** (et non `toISOString()`/UTC) — fini le décalage d'un jour en UTC+1 qui pouvait
  désaccorder le taux affiché et le taux appliqué.
- **Migration** additive `add_taux_tva_to_devis_table` + backfill des devis existants non exonérés à 19%.

#### Tests
- **`TvaTauxApiTest`** (CRUD, admin-only 403, garde 409, validations, clôture auto) + cas de la règle « ≥1 actif par type »
  (désactiver/supprimer le dernier actif → 409 ; désactivation autorisée s'il reste un autre actif ;
  `enVigueurLe` ignore un taux inactif) + **2 taux le même jour → 409** (création et édition), **édition d'une date re-clôture
  le précédent** ; `TvaTauxTest` (départage déterministe par `id` à `date_debut` égale).
- **`DevisApiTest`** : taux snapshot persisté (standard 19% / exonéré 0%), **devis hors taux en vigueur → 409**,
  édition prestation → totaux recalculés ; **`FactureApiTest`** : facture standard hors taux → 409 (plus de 0% muet) ;
  `api-modules.test.ts` étendu (`referentielsApi`).

### Suppression du timbre fiscal (refactor/suppression-timbre)

Le timbre fiscal était présent en infrastructure mais **toujours nul en pratique** (`creerFacture()`/`creerDevis()`
forçaient `montant_timbre = 0` et `timbre_taux_id = null`, le calcul `TimbreTaux` n'était jamais invoqué). Il est
**entièrement retiré** du périmètre.

#### Backend
- **Modèle** `TimbreTaux` supprimé ; `Facture`/`Devis` nettoyés (`montant_timbre`, `timbre_taux_id`, relation `timbreTaux()`)
- **Migrations** : colonnes `montant_timbre` (factures, devis), FK `timbre_taux_id` et table `timbre_taux` retirées
  (édition des migrations d'origine, schéma propre — nécessite `migrate:fresh`)
- **`FacturationService`** / **`PdfService`** : timbre retiré de la création facture/devis et de l'agrégation du rapport de clôture
- **Resources** (`FactureResource`, `DevisResource`), **factories**, **seeder** (`TvaTauxSeeder`) et **PDF de clôture** nettoyés
- **Tests** : `TimbreTauxTest` supprimé ; références `TimbreTaux`/`montant_timbre` retirées des tests facturation/dashboard

#### Frontend
- **`types/index.ts`** : `montant_timbre` (Facture, Devis) et `timbre_rate_id` (Facture) retirés ; test `types.test.ts` aligné (TTC = HT + TVA)

#### Docs
- `CLAUDE.md`, `README.md`, `docs/ARCHITECTURE.md`, `docs/BACKLOG.md` (US-04 → « TVA historisée », US-51 → « Gestion des taux TVA ») mis à jour

#### Fix (repéré pendant la recette)
- **`PdfService::genererRapportCloture()`** : le filtre des factures utilisait `where('type', 'facture')` alors que
  le type réel est `'FF'` — le rapport de clôture (US-35) ressortait **toujours vide**. Corrigé en `where('type', 'FF')`
  (cohérent avec `DashboardService` / `PortailService`). Vérifié : le rapport liste désormais bien les factures et les impayés.

### Tests front (couche logique) + cahier de recettes (feature/tests-frontend)

#### Tests
- **Vitest** : harnais étendu à **95 tests** — couverture des **18 modules API**, du **store `auth`**
  (login/logout/fetchUser, normalisation des rôles, getters de rôle, `hasAnyRole`), et des composables
  (`useApiError`, `useCountUp`, patron CRUD via `useEntreprises`)
- Couche **logique** uniquement (insensible à la refonte design à venir) ; tests de composants reportés post-refonte

#### Documentation
- **`docs/CAHIER-RECETTES.md`** (nouveau) : cahier de recettes **niveau application** (RNCP C2.3.1) — scénarios
  **fonctionnels** (12 domaines), **structurels** (Pint / PHPUnit / Vitest / build / CI) et **sécurité** (OWASP)
  avec préconditions, étapes et résultats attendus

### Refonte page Créances + relances (feature/relances)

#### Frontend
- **`CreancesPage.vue`** : refonte **mobile-first** — KPI **« total restant »** dû mis en avant, dialog d'envoi de relance plus ergonomique, présentation des créances revue ; accessibilité (`aria-*`) renforcée

#### Backend
- **`RelanceClientMail.php` / `relance.blade.php`** : renommage de la variable `$message` en `$corps` dans la vue mail (évite le conflit avec la variable réservée `$message` de Blade)

### Refonte sidebar v2 — accordéons + restyle (sidebar-refonte-v2)

#### Frontend
- **`AppMenu.vue`** : tous les groupes racine (Accueil, Gestion, Facturation, Administration) sont désormais des **accordéons à en-tête unique encadré** — suppression du libellé de section affiché en double. Le paramétrage reste regroupé sous « Administration » (Prestations, Paramètres, Exercices, Utilisateurs, KPI Objectifs, Journal d'audit) ; `KPI Objectifs` retiré de la section Accueil ; icônes de groupe ajoutées ; icône Paramètres en `pi-sliders-h`. Nouveau flag `defaultOpen` : **Accueil / Gestion / Facturation ouverts par défaut**, **Administration replié** (auto-ouvert sur une de ses pages filles)
- **`AppMenuItem.vue`** : flag `accordion` sur un groupe racine (en-tête repliable à état local) ; ouverture initiale = `defaultOpen` si présent, sinon dépliée seulement si la page courante appartient au groupe ; menu portail inchangé
- **`layout.scss` + `tokens.css`** : restyle du menu — item actif en **pastille arrondie** (liseré gauche retiré, nouveau rayon `--ledge-radius-pill: 8px`), en-tête d'accordéon **encadré** (fond `surface-100` + bordure) ; accent **orange encre conservé**, **dark mode** préservé
- **RGAA** : en-tête `role="button"` + `aria-expanded`, navigation clavier (Entrée/Espace), focus visible, chevron + libellé (pas de couleur seule), `prefers-reduced-motion` neutralise la transition

### Graphiques dashboard admin — (feature/dashboard-graphiques)

#### Backend
- **`DashboardService::getStats()`** : nouvelle clé `ca_mensuel` (`{ annee, data[12] }`) — série du CA TTC facturé mois par mois pour l'année de l'exercice filtré (ou année courante), agrégée en PHP (portable SQLite/MySQL, pas de SQL brut)
- **`missions`** enrichi de `suspendues` et `annulees` (en plus de `en_cours` / `terminees`) pour la répartition par statut

#### Frontend
- **`DashboardPage.vue`** (section admin) : 2 graphiques **Chart.js** via le composant `<Chart>` de PrimeVue — **CA mensuel en barres** (12 mois, tooltip en DA) et **répartition des missions par statut en camembert**
- **Dark mode** : couleurs des axes / texte / barres lues sur les tokens PrimeVue (`--p-*`) et rafraîchies au toggle via `useLayout().isDarkTheme`
- **RGAA** : chaque graphe `role="img"` + `aria-label` résumant les valeurs, table alternative `.sr-only` (canvas non lisible par lecteur d'écran), `animation: false` si `prefers-reduced-motion`
- **`stats.ts`** : type `DashboardStats` étendu (`ca_mensuel`, `missions.suspendues/annulees`)
- Dépendance ajoutée : `chart.js`

### Secrétaire hors Missions & Planning — (feature/secretaire-hors-missions-planning)

#### Backend
- **Routes API** (`routes/api.php`) : missions, tâches, commentaires, calendrier et dashboard collaborateur déplacés dans un groupe **`role:admin|collaborateur`** — la secrétaire n'y a plus accès (les utilitaires `users`/`settings` en lecture restent partagés)
- **Policies** : `MissionPolicy` et `TachePolicy` ne référencent plus le rôle `secretaire` (`viewAny`/`view`/`create`/`update`/`delete` → admin, ou collaborateur sur ses propres missions/tâches)

#### Frontend
- **Menu** (`AppMenu.vue`) : les entrées **Missions** et **Planning** ne sont visibles que pour admin et collaborateur
- **Routeur** : nouveau set `ROLES.adminCollaborateur` appliqué aux routes `missions`, `mission-detail`, `tache-detail`, `planning` — accès secrétaire bloqué (redirection accès refusé)
- **Fiche entreprise** (`EntrepriseDetailPage.vue`) : pour la secrétaire, l'onglet **Missions** et le KPI **« Missions actives »** sont masqués, l'appel API missions n'est pas déclenché, et l'onglet par défaut bascule sur **Devis**

#### Tests
- **`SecretairePermissionsTest`** : nouveaux cas — la secrétaire reçoit `403` sur missions (liste/détail/création/tâches), calendrier et dashboard collaborateur

### Recadrage du périmètre du rôle secrétaire — (feature/perimetre-secretaire)

#### Backend
- **Routes API** (`routes/api.php`) : les écritures de facturation passent en **`role:admin`** — création/suppression de devis et factures, création/suppression d'avoirs, suppression de paiements, cycle de vie devis (`accepter`/`refuser`/`convertir-en-mission`) et calcul de prix. La secrétaire conserve : lecture devis/factures/avoirs + PDF, **envoi d'un devis** au client, **enregistrement de paiements**, **envoi de relances**, consultation des créances, et le **CRUD des entreprises** (création/modification, sans suppression) + contacts
- **Policies** : `DevisPolicy` / `FacturePolicy` — `create`/`update`/`delete` réservés à l'admin ; nouvelle ability **`DevisPolicy::envoyer`** (admin + secrétaire) pour dissocier l'envoi de la modification ; `AvoirPolicy::create` réservé à l'admin ; `EntreprisePolicy` inchangée (création/modification admin + secrétaire, suppression admin)
- **`DevisController::envoyer()`** autorise désormais l'ability `envoyer` (et non plus `update`)
- **`DashboardService::getSecretaireStats()`** : retrait du volet facturation/production (devis en attente / à convertir / expirant, émission de factures N vs N-1) ; dashboard recentré sur le **recouvrement** (créances, aging, relances dues, top débiteurs, encaissements du mois)

#### Frontend
- **Devis / Factures** : les actions de production (nouveau devis/facture, modifier, supprimer, accepter/refuser/convertir, émettre/supprimer un avoir) sont masquées pour la secrétaire (`v-if="auth.isAdmin"`) ; elle conserve **Envoyer un devis**, **téléchargement PDF** et **enregistrement de paiement**
- **Dashboard secrétaire** (`SecretaireDashboardSection.vue`) : suppression de la carte « Devis en attente », du graphe « Émission de factures » et du bouton « Gérer les factures » ; grille rééquilibrée
- **`stats.ts`** : type `SecretaireStats` aligné (suppression `facturation` / `factures_emises`, ajout `encaissements_mois` à la racine)

#### Tests
- **`SecretairePermissionsTest`** (nouveau) : couverture du périmètre autorisé (entreprises CRU, lecture facturation, envoi devis, paiement, relance) et interdit (création/suppression devis/factures/avoirs, cycle de vie devis, suppression entreprise/paiement)
- **`DashboardSecretaireTest`** : structure mise à jour (plus de volet facturation, `encaissements_mois` à la racine)

### Fix arrondi des tranches de facturation — (fix/arrondi-tranches-facturation)

#### Backend
- **`FacturationService::creerFacture()`** : la 3ᵉ tranche est désormais calculée comme **solde exact** (`prix_ht − T1 − T2`) au lieu d'un `round(prix_ht × 0.40)` indépendant — garantit l'invariant `T1 + T2 + T3 == prix_ht` même lorsque le prix porte des centimes (corrige une perte possible de 1 centime sur la répartition 30/30/40)

#### Frontend
- **`MissionDetailPage.vue`** : l'aperçu des tranches arrondit aux **centimes** (2 décimales) et applique le même solde exact sur la 3ᵉ tranche — aligné sur les montants réellement facturés par le backend (avant : `Math.round` aux dinars pleins, divergence possible avec la facture)

#### Tests
- **`FacturationServiceTest`** : nouveau test d'invariant `T1 + T2 + T3 == prix_ht` sur un prix à centimes (`100.01`) — cas limite d'arrondi

### Dashboard secrétaire + autorisations front — (feature/dashboard-secretaire)

#### Backend — dashboard secrétaire
- **`DashboardService::getSecretaireStats()`** (nouveau) : KPI orientés recouvrement — créances totales (avec déduction avoirs via `montantRestant()`), aging 15–29 / 30–59 / 60+ j, relances dues (logique alignée sur `EnvoyerRelancesJob`), top 5 débiteurs, factures émises mois N vs N-1, créances urgentes
- **Volet facturation** (`compterFacturation()`) : devis en attente (count + montant), devis acceptés à convertir en mission, devis expirant sous 7 j, encaissements du mois
- **Worklist « À faire »** (`construireWorklist()`) : liste d'actions priorisées par sévérité (factures en retard, relances à envoyer, devis expirant / en attente / à convertir) avec route de destination — dashboard orienté action
- **`GET /api/v1/stats/secretaire`** : route réservée au rôle `secretaire` (middleware Spatie dédié)
- **`GET /api/v1/stats`** : déplacé dans le groupe `role:admin` — séparation stricte admin / secrétaire

#### Backend — droits entreprises secrétaire
- **`EntreprisePolicy`** : `create()` et `update()` ouverts à admin + secrétaire ; `delete()` reste admin uniquement
- Routes `POST/PUT entreprises` déplacées dans le groupe `admin|secretaire` ; suppression et portail restent admin

#### Frontend — dashboard secrétaire
- **`SecretaireDashboardSection.vue`** : refonte graphique « Ledger Edition » orientée action — bandeau éditorial, **panneau « À faire »** (worklist cliquable), 4 KPI animés (count-up) recouvrement + facturation, graphiques SVG/CSS (aging, donut relances, comparatif factures N vs N-1), classement débiteurs en barres, table créances urgentes
- **Dark mode** géré sur tous les nouveaux éléments (tokens `--p-*` / `--ledge-*`, sélecteurs `.app-dark` directs) ; correction d'un bug où `:global(.app-dark)` était mal compilé par lightningcss (perte du descendant) — appliqué aussi au dashboard collaborateur ; **RGAA** (charts `role="img"` + libellés, worklist en liste de liens, `prefers-reduced-motion`, focus visibles)
- **Zéro dépendance ajoutée** : graphiques en SVG/CSS pur (cohérent avec le dashboard collaborateur)
- **`useDashboardStats.ts`** (nouveau composable) : pattern Page → Composable → API pour les 3 dashboards
- **`DashboardPage.vue`** : branchement à 3 voies (collaborateur / secrétaire / admin)

#### Frontend — autorisations router
- **`meta.roles`** sur toutes les routes back-office + guard `beforeEach` avec redirection vers `/acces-refuse`
- **`AccesRefusePage.vue`** (nouveau) : page 403 accessible avec message clair et bouton retour (RGAA)
- **`authStore.hasAnyRole()`** : helper pour le guard
- **`AppMenu.vue`** : config relances (admin only) retirée du menu secrétaire
- **`EntrepriseListPage.vue`** : colonne portail, suppression et dialogs réservés à l'admin

#### Documentation
- **`docs/WORKFLOW-FEATURE.md`** (nouveau) : checklist réutilisable pour chaque feature

#### Tests
- **`DashboardSecretaireTest.php`** : 9 tests — structure (incl. `facturation` + `actions`), avoirs, aging, devis en attente, encaissements du mois, worklist factures en retard, séparation rôles
- **`DashboardKpiTest`** : secrétaire bloqué sur `/stats`
- **`EntrepriseApiTest`** : secrétaire create/update OK, delete 403
- **169 tests / 433 assertions** — aucune régression

---

### Refonte sidebar & qualité backend — (feature/refonte-sidebar)

#### Backend — SOLID / SRP
- **`FacturationService::supprimerFacture()`** (nouveau) : invariant "pas de paiements" levé via `DomainException`, cascade `lignes()->delete()` + `delete()` en transaction atomique
- **`MissionService::supprimerMission()`** (nouveau) : invariant "pas de factures associées" levé via `DomainException`, cascade `taches()->delete()` + `collaborateurs()->detach()` + `delete()` en transaction (les pivots n'étaient pas nettoyés avant)
- **`FactureController::destroy` et `MissionController::destroy`** : logique métier sortie des controllers, délégation pure aux services — alignement sur `DevisController::destroy` déjà conforme

#### Backend — autorisations harmonisées
- **`DevisPolicy` et `FacturePolicy`** : ajout de `viewAny()` et `view()` (admin/secrétaire/collaborateur en lecture) — auparavant aucune Policy ne couvrait `index/show/pdf`
- **`DevisController`** : `authorize()` ajouté sur `index`, `show`, `pdf`, et toutes les transitions de statut (`envoyer`, `accepter`, `refuser`, `convertirEnMission`) — mappées sur `update`
- **`FactureController`** : `authorize()` ajouté sur `index`, `show`, `pdf`
- **`MissionController`** : `authorize('view', ...)` ajouté sur `conventionPdf` et `mandatPdf`

#### Backend — conventions
- Les dépendances injectées des 3 controllers (`DevisController`, `FactureController`, `MissionController`) sont désormais `private readonly`
- Ajout du filtre `entreprise_id` sur les listes devis / factures / missions (gestion déjà présente côté services)

#### Frontend — fix calculs KPIs fiche entreprise
- **`EntrepriseDetailPage.vue`** : CA recalculé sur `montant_ht` au lieu de `montant_ttc` (le chiffre d'affaires est par définition hors taxes)
- Nouveau `fetchFacturesKpi()` qui charge les factures **tous exercices confondus** indépendamment du filtre exercice de la page — les KPIs CA total et impayés reflètent désormais la réalité globale du client
- Filtrage `entreprise_id` côté API plutôt que côté front (réduction de la charge réseau)
- `formatMontant()` sécurisé contre les valeurs `null` / `NaN`

#### Frontend — refonte UI page de connexion
- **`LoginPage.vue`** : nouveau layout en deux zones — panneau de branding (logo SVG inline, tagline, pills modules, mention version/RNCP) + zone formulaire principale mobile-first
- Deux dialogs informatifs ajoutés (aide à la connexion + mot de passe oublié) — pas de dépendance sur des pages externes
- A11y renforcée : skip link vers le formulaire, `aria-label` sur la zone branding, `role="alert"` + `aria-live` sur les messages d'erreur
- Suppression du composant `LedgeLogo` au profit d'un visuel SVG embarqué (simplification, moins de dépendances sur une page critique)

#### Tests
- **156 tests / 374 assertions** — aucune régression sur le refacto backend

#### Audit de conformité — fixes qualité
- **`LoginPage.vue`** : retrait du composant `<LedgeLogo>` orphelin ligne 107 (référencé sans import depuis la refonte UI — produisait un warning `Failed to resolve component` et un logo manquant dans la zone formulaire). Wrapper `<div class="login-form-logo-row">` et CSS associés également nettoyés
- **`EntrepriseDetailPage.vue`** : introduction d'un type local `TagSeverity` (`'info' | 'success' | 'warn' | 'danger' | 'secondary' | 'contrast'`) — les 4 fonctions `statut*Color()` retournent désormais ce type au lieu de `as any` (correction d'une dette TypeScript)
- **`EntrepriseDetailPage.vue`** : KPIs `Impayé / CA total / Missions actives` désormais visibles sur mobile en version compacte (cartes en `flex nowrap`, paddings et tailles de police réduits) — auparavant `display: none` masquait totalement ces indicateurs sous 900 px, contrairement à la règle mobile-first
- **`EntrepriseDetailPage.vue`** : correction du débordement de texte dans le panneau Coordonnées — `dd` en `flex: 1; min-width: 0; overflow-wrap: anywhere` pour casser proprement les chaînes non sécables (emails, identifiants) + `align-items: flex-start` sur `.info-row` pour aligner le label en haut quand la valeur wrappe sur plusieurs lignes

---

### Journal d'audit — piste d'audit des actions utilisateurs (feature/journal-audit)

#### Backend
- **`spatie/laravel-activitylog` (^4)** : nouvelle table `activity_log` (causer, sujet polymorphe, événement, diff des propriétés) — migrations publiées
- **Trait `LogsActivity`** sur 7 modèles sensibles : `Facture`, `Avoir`, `Paiement`, `Devis`, `Entreprise`, `User`, `Setting` (`logFillable` + `logOnlyDirty` + `dontSubmitEmptyLogs`)
- **Sécurité** : `User` journalise tout sauf `password` et `remember_token` (`logExcept`) — aucun hash de mot de passe en clair dans l'audit
- **`AuditService`** : liste paginée du journal, filtrable par entité / action / période ; mapping label court ↔ classe Eloquent
- **`AuditController` + `ActivityResource`** : `GET /api/v1/audit-logs` (controller mince → service → resource), exposant causer, diff `old`/`attributes`, entité et date
- **Route admin uniquement** : `/audit-logs` placée dans le groupe `role:admin` (secrétaire/collaborateur → 403)
- **Tests** : `AuditLogTest` (6 tests) — journalisation avec causer, diff des champs modifiés, exclusion du `password`, accès admin/403, filtre par événement

#### Frontend
- **`pages/audit/AuditLogPage.vue`** (nouveau) : DataTable paginée + filtres (entité, action, dates) + dialog détail du diff avant/après · RGAA (`<main>`, `aria-labelledby`, `aria-label`, `role="search"`)
- **`api/modules/audit.ts`** (nouveau) + type `Activity` : appel `GET /audit-logs` via le module dédié
- **Router** : route `audit-logs` ; **`AppMenu`** : entrée « Journal d'audit » sous Administration (admin)

#### Sécurité — dépendances (OWASP A06)
- **`docs/SECURITY.md`** (nouveau) : 8 advisories sur 5 paquets Symfony 7.x (`http-kernel`, `mailer`, `mime`, `routing`, `yaml`) tirés transitivement par Laravel 12 — **documentées et évaluées** (impact réel faible à nul : `MAIL_MAILER=log`/Resend, autorisation Laravel native, routing Laravel, `yaml` en dépendance dev), **non silencées** dans `composer audit`
- Aucune version corrigée n'étant disponible dans la plage `symfony/* ^7.2`, l'install (local + CI) reste fonctionnelle car `composer install` lit depuis le lock sans re-résolution ; plan de remédiation suivi (`composer update symfony/*` dès patch publié)

### Sécurité — mot de passe admin hors du code (chore/admin-seeder-env-password)

#### Backend
- **`AdminUserSeeder`** : le mot de passe de l'administrateur initial est lu depuis `ADMIN_PASSWORD` (et l'email depuis `ADMIN_EMAIL`) au lieu d'être codé en dur — plus aucun credential dans le code source (OWASP A07)
- **Garde-fou production** : si `ADMIN_PASSWORD` est absent en environnement `production`, le seeder lève une `RuntimeException` au lieu de créer un admin avec un mot de passe par défaut
- **Local / test** : comportement inchangé — fallback sur `password` si `ADMIN_PASSWORD` est vide
- **`.env.example`** : documentation des variables `ADMIN_EMAIL` / `ADMIN_PASSWORD`

### Sécurité — gestion des erreurs API (fix/api-error-handling)

#### Backend
- **`app/Exceptions/ApiExceptionRenderer.php`** (nouveau) : renderer JSON unifié pour toutes les routes `api/*` — mappe chaque type d'exception (`ValidationException`, `AuthenticationException`, `AuthorizationException`, `ModelNotFoundException`, `NotFoundHttpException`, `MethodNotAllowedHttpException`, `TokenMismatchException`, `TooManyRequestsHttpException`, `QueryException`, `PDOException`, `HttpExceptionInterface`, catch-all `Throwable`) vers un statut HTTP correct + un message client générique en français
- **`bootstrap/app.php`** : `shouldRenderJsonWhen` + `render` branchés sur le renderer — toute exception sur une route API renvoie désormais du JSON propre, **même quand `APP_DEBUG=true`** (plus aucune fuite de SQL, host, port, nom de DB, stack trace, chemin fichier)
- Logging serveur complet (`Log::error` avec contexte URL/méthode/IP/user_id) — exploité par Sentry, jamais exposé au client
- **6 tests** dans `ApiExceptionRendererTest` qui prouvent l'absence de fuite (SQL, SQLSTATE, host, port, stack, chemins)

#### Frontend
- **`types/api-error.ts`** (nouveau) : type `ApiError` discriminé (`network` / `timeout` / `validation` / `auth` / `forbidden` / `notfound` / `csrf` / `throttle` / `server` / `unavailable` / `unknown`)
- **`api/client.ts`** : intercepteur réponse refait — détecte les erreurs réseau (WAMP éteint, DNS, CORS), les timeouts (15 s), les réponses non-JSON (page d'erreur HTML), produit un `ApiError` typé avec message FR adapté, et sanitize le `error.response.data.message` exposé au code existant
- **`composables/useApiError.ts`** (nouveau) : helpers `getApiError()` / `getApiErrorMessage()` pour extraire un message safe sans accéder aux détails techniques
- **`pages/auth/LoginPage.vue`** : utilise `getApiError()`, distingue 422 (identifiants) des autres erreurs, ajoute `aria-live="assertive"` + `aria-invalid` sur les champs en erreur
- **CSS LoginPage** : surcharge `:-webkit-autofill` (plus de fond olive Chrome illisible en thème sombre), `word-break: break-word` sur le `Message` d'erreur (plus de cassure de layout sur message long), `:focus-visible` outline RGAA, `max-w-xl` sur le conteneur

#### Sécurité (OWASP)
- **A05 Security Misconfiguration** corrigé : `APP_DEBUG=true` ne fuite plus rien sur les routes API
- **A09 Logging & Monitoring** : toutes les exceptions API sont loguées avec contexte structuré

---

### Dashboard collaborateur — (feature/dashboard-collaborateur)

#### Backend
- **`DashboardService::getCollaborateurStats()`** : stats personnalisées par collaborateur — missions assignées (total / en cours / terminées), tâches (total / à faire / en cours / terminées / bloquées / taux de complétion), 5 missions les plus récentes avec progression, 5 tâches urgentes avec indicateur retard
- **`DashboardController::collaborateurStats()`** : endpoint `GET /collaborateur/stats` — accessible à tous les rôles backoffice
- **`routes/api.php`** : route `collaborateur/stats` déplacée dans le groupe tous-backoffice (admin + secrétaire + collaborateur)

#### Frontend
- **`api/modules/stats.ts`** : interface `CollaborateurStats` + méthode `getCollaborateurDashboard()`
- **`DashboardPage.vue`** : dashboard collaborateur dédié — 4 cartes KPI (missions assignées, mes tâches, taux de complétion, tâches bloquées), tableau `mes_missions` avec ProgressBar, liste `mes_taches_urgentes` avec Tag statut et indicateur retard rouge

#### Correctifs
- **`DashboardService`** : colonne `date_fin` corrigée en `date_echeance` (nom réel dans `taches`) — le tri et le calcul retard fonctionnent désormais correctement
- **`DashboardPage.vue`** : import `ProgressSpinner` manquant ajouté — le spinner de chargement s'affiche correctement
- **`api/modules/stats.ts`** : champ `priorite` retiré du type `CollaborateurStats` (non utilisé en vue)

---

### Rapport PDF fin de mission — (feature/rapport-fin-mission)

#### Backend
- **`PdfService::genererRapportMission()`** : génération du rapport PDF enrichi — eager loading `factures.paiements`, filtre `visible_portail=true` en mode portail
- **`rapport-mission.blade.php`** (nouveau) : template DomPDF complet avec 6 sections : résumé exécutif (durée / avancement / financier), informations mission, chronologie jalons, statistiques tâches par statut, tâches + commentaires filtrés, facturation avec paiements par facture et solde global
- **`MissionController::rapportPdf()`** : endpoint `GET /missions/{mission}/rapport/pdf` — admin/secrétaire uniquement, mode back-office (commentaires internes inclus)
- **`PortailMissionController::rapportPdf()`** : endpoint `GET /portail/missions/{mission}/rapport/pdf` — client uniquement, mode portail (commentaires `visible_portail=true` uniquement, montants HT et bloc total masqués)
- **`routes/api.php`** : 2 routes rapport PDF ajoutées (backoffice + portail)
- **150 tests / 351 assertions** — aucune régression

#### Frontend
- **`api/modules/missions.ts`** : ajout de `rapportPdfUrl(id)` pour l'URL de génération back-office
- **`api/modules/portail.ts`** : ajout de `rapportMissionPdfUrl(missionId)` pour l'URL portail
- **`MissionDetailPage.vue`** : bouton "Télécharger le rapport PDF" dans la section Documents (masqué pour les collaborateurs)
- **`PortailMissionsPage.vue`** : bouton "Télécharger le rapport PDF" dans le dialog détail mission

---

### Page dédiée tâche + corrections commentaires — (develop)

#### Backend
- **`TacheController::show()`** (nouveau) : endpoint `GET /missions/{mission}/taches/{tache}` — chargement d'une tâche individuelle avec son assigné
- **`routes/api.php`** : route `missions.taches` désormais complète (plus d'exclusion de `show`)
- **`TacheCommentaireResource`** : ajout de `user_id` en top-level — nécessaire pour la comparaison auteur côté frontend
- **Fix route commentaires** : paramètre `{tach}` (Laravel tronquait `taches`) corrigé en `{tache}` via `->parameters(['taches' => 'tache'])` — le model binding fonctionnait pas

#### Frontend
- **`TacheDetailPage.vue`** (nouveau) : page dédiée `/missions/:id/taches/:tacheId` — carte infos tâche (assigné, échéance, priorité, statut), section commentaires complète avec CRUD, dialog modification tâche
- **`MissionDetailPage.vue`** : tableau tâches simplifié — 3 boutons par ligne (voir ▶ page dédiée, modifier, supprimer), plus de chevrons expandables
- **`api/modules/taches.ts`** : ajout de `getOne(missionId, tacheId)` pour charger une tâche individuelle
- **`router/index.ts`** : route `tache-detail` ajoutée (`missions/:id/taches/:tacheId`)
- **Commentaires** : boutons modifier/supprimer toujours visibles (plus de opacity:0 au hover) — auteur en gras + heure sur la même ligne en header du commentaire, boutons à droite
- **Fix droits commentaires** : `peutModifierCommentaire()` utilisait `c.user_id` absent de la resource → corrigé avec fallback `c.user?.id` ; un collaborateur peut désormais modifier/supprimer ses propres commentaires
- **CI gitflow guard** : job bloquant les PR `feature/* → main` ajouté dans `ci.yml`

---

### Droits collaborateur — (feature/droits-collaborateur)

#### Backend
- **`bootstrap/app.php`** : enregistrement des middleware Spatie (`role`, `permission`, `role_or_permission`) — prérequis pour les groupes de routes par rôle
- **`routes/api.php`** : restructuration complète en 3 groupes de middleware :
  - `role:admin` → écriture utilisateurs, paramètres, entreprises, exercices, prestations, KPI
  - `role:admin|secretaire` → stats dashboard, lecture référentiels, toute la facturation (devis, factures, paiements, avoirs, relances, créances)
  - tous rôles backoffice → lecture users/settings, calendar, missions, tâches, commentaires
- **`EntreprisePolicy`** (nouveau) : `viewAny/view` réservés à admin/secrétaire ; `create/update/delete` admin uniquement
- **`EntrepriseController`** : `$this->authorize()` ajouté sur toutes les méthodes
- **`MissionPolicy`** : ajout de `viewAny` et `view` — collaborateurs restreints à leurs missions assignées (`mission_user` pivot) ; `update` désormais admin/secrétaire uniquement
- **`MissionService::listerMissions()`** : ajout du paramètre `User $user` — filtre automatique `whereHas('collaborateurs')` pour les collaborateurs
- **`MissionController`** : `index()` et `show()` branchés sur les gates `viewAny` et `view`
- **`TachePolicy`** (nouveau) : `update` — collaborateur uniquement si `assigned_to === user->id` ; `delete` — admin/secrétaire uniquement
- **`TacheController`** : `index()` protégé par `authorize('view', $mission)` ; `store`, `update`, `destroy` protégés par les policies ; payload `update` restreint à `statut` pour les collaborateurs
- **`TacheCommentaireController`** : `index()` protégé par `authorize('view', $tache->mission)` ; `store()` vérifie l'accès à la mission via `MissionPolicy::view`
- **`CalendarController`** : injection du filtre `collaborateur_id` automatiquement pour les collaborateurs — chaque collaborateur ne voit que les événements de ses missions assignées
- **`TacheApiTest`** : correction du test `collaborateur_ne_voit_que_ses_taches` — attach du collaborateur à `mission_user` avant la requête
- **Migration `add_visible_portail_to_tache_commentaires`** : colonne `visible_portail` (boolean, default false) sur `tache_commentaires` — prépare le rapport de clôture (US-35) et le partage client
- **`TacheCommentaire`** : `visible_portail` ajouté au `$fillable`
- **150 tests / 351 assertions** — aucune régression

#### Frontend
- **`stores/auth.ts`** : ajout des computed `isCollaborateur` et `isSecretaire` exportés
- **`types/index.ts`** : ajout de l'interface `TacheCommentaire` (`id`, `tache_id`, `user_id`, `contenu`, `visible_portail`, `user`, `created_at`, `updated_at`)
- **`api/modules/commentaires.ts`** (nouveau) : module API CRUD commentaires — `getAll`, `create`, `update`, `delete` sur `/taches/{tacheId}/commentaires`
- **`composables/useCommentaires.ts`** (nouveau) : composable réactif — `fetchCommentaires`, `createCommentaire`, `updateCommentaire`, `deleteCommentaire` avec gestion toast succès/erreur
- **`MissionDetailPage.vue`** : section commentaires par tâche (ligne expandable DataTable) — liste commentaires avec auteur/date, saisie inline, guards auteur/admin sur edit/delete, badge `visible_portail` admin uniquement ; guards rôle sur boutons créer/supprimer tâche, statut mission, statut tâche (désactivé si non-assigné), sections documents/tranches/factures ; RGAA : `aria-labelledby`, `role="status"`, `aria-expanded`, `aria-live`, `sr-only`
- **`layout/AppMenu.vue`** : menu Entreprises désormais masqué pour les collaborateurs (`visible: isAdmin || isSecretaire`)
- **`pages/dashboard/DashboardPage.vue`** : panel de bienvenue collaborateur avec liens vers Missions et Planning ; les stats financières (`GET /stats`) ne sont pas appelées pour les collaborateurs (évite le 403)
- **`pages/missions/MissionListPage.vue`** : guards `!auth.isCollaborateur` sur les appels référentiels au montage, sur le bouton "Nouvelle mission", le filtre exercice, et les boutons modifier/supprimer du tableau

---

### Supervision — (feature/supervision-mco)

#### Backend
- **Laravel Health** : endpoint `GET /health` — 4 checks configurés : base de données, cache, espace disque (warn >70%, fail >90%), mode debug
- **`config/health.php`** : publication de la config Spatie Health — résultats stockés en base via `EloquentHealthResultStore`, historique conservé 5 jours
- **Migration `create_health_tables`** : table `health_check_result_history_items` pour l'historique des checks
- **Sentry** : SDK `sentry/sentry-laravel ^4.25` installé — auto-découverte via Laravel, config dans `config/sentry.php` — activé via `SENTRY_LARAVEL_DSN` en `.env`
- **Logs rotatifs** : `LOG_CHANNEL=daily` par défaut — fichiers `storage/logs/laravel-YYYY-MM-DD.log`, rotation sur 14 jours
- **`.env.example`** : ajout des variables `SENTRY_LARAVEL_DSN`, `SENTRY_TRACES_SAMPLE_RATE`, `HEALTH_SECRET_TOKEN`
- **150 tests / 351 assertions** — aucune régression

---

### Accessibilité — (feature/accessibilite-rgaa)

#### Frontend
- **`AppTopbar.vue`** : remplacement du `<div class="layout-topbar">` par `<header role="banner">` — sémantique HTML correcte pour la barre de navigation principale
- **`AppLayout.vue`** : ajout de `aria-hidden="true"` sur le masque overlay mobile — élément décoratif retiré de l'arbre d'accessibilité
- **`DashboardPage.vue`** : ajout d'un `<section aria-labelledby="dashboard-title">` autour du contenu principal et `id="dashboard-title"` sur le `<h2>` — navigation par titres opérationnelle pour les lecteurs d'écran

---

### Sécurité — (feature/owasp-securite)

#### Backend
- **`SetSecurityHeaders` middleware** : ajout des headers HTTP sécurisés sur toutes les réponses — `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`, `Content-Security-Policy: default-src 'self'`
- **Throttle brute-force login** : `throttle:5,1` sur `POST /api/v1/login` (5 tentatives max par minute par IP)
- **Authorization Policies** : 7 Policies créées — `UserPolicy`, `PrestationPolicy`, `SettingPolicy`, `FacturePolicy`, `DevisPolicy`, `MissionPolicy`, `AvoirPolicy` — avec `$this->authorize()` dans chaque controller concerné
- **`Controller` base** : ajout du trait `AuthorizesRequests` (manquant en Laravel 12)
- **CORS** : `allowed_headers` restreint à `['Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN']` (suppression du wildcard `'*'`)
- **Déjà conformes** : FormRequests sur tous les `store()`/`update()` (23 FormRequests), Eloquent uniquement (0 `DB::raw()` avec input user), 0 `v-html` avec données utilisateur (XSS), CSRF Sanctum cookie-based, session `http_only=true` + `same_site=lax`
- **150 tests / 351 assertions** — tous verts après ajout des policies

---

### Ajouts — US-38 : Tests unitaires (feature/tests-unitaires)

- **`tests/Unit/Services/FacturationServiceTest`** (14 tests) : numérotation séquentielle + reset par exercice, tranches 30%/30%/40%, 4ème tranche impossible, TVA historisée snapshot à la date de facturation, statut paiement auto (en_attente → partiel → solde), snapshots immuables
- **`tests/Unit/Models/ExerciceTest`** (6 tests) : `current()` retourne l'exercice ouvert de l'année, null si absent ou clôturé, `isOuvert()`, relations HasMany
- **`tests/Feature/Api/TacheApiTest`** : +5 tests — suppression sans commentaires, protection suppression avec commentaires (409), scope collaborateur, statut initial, validation titre
- **`phpunit.xml`** : ajout section `<coverage>` + exclusions Middleware/Providers/Console
- **150 tests / 351 assertions** — modules critiques couverts : FacturationService 84%, KpiService 100%, MissionService 93%, PdfService 88%, PortailService 98%, Exercice 100%

---

### Ajouts — US-34 : KPI objectifs collaborateurs (feature/kpi-objectifs-collaborateurs)

#### Backend
- **Migration** `create_kpi_objectifs_table` : table `kpi_objectifs` — `user_id`, `exercice_id`, `type` (enum ca_ht / missions_cloturees / taches_terminees), `valeur`, contrainte unique `(user_id, exercice_id, type)`
- **Migration** `alter_kpi_objectifs_type_enum` : remplacement de `delai_moyen_facturation` par `taches_terminees` dans l'enum (MySQL uniquement, SQLite skippé)
- **`KpiObjectif`** : modèle Eloquent avec relations `user` et `exercice`
- **`User::kpiObjectifs()`** : relation `HasMany` vers `KpiObjectif`
- **`KpiService::getCollaborateurs(?exerciceId)`** : retourne collaborateurs et admins avec objectifs et réalisé — 5 indicateurs : CA HT, missions clôturées, tâches terminées (avec objectif), tâches en retard, délai moyen traitement tâche (réalisé seulement)
- **`KpiService::upsertObjectif()`** : création ou mise à jour d'un objectif (`updateOrCreate`)
- **`KpiController`** : `GET /kpi/objectifs`, `POST /kpi/objectifs`, `DELETE /kpi/objectifs/{id}` — backoffice uniquement
- **Fix** : `JULIANDAY()` remplacé par `Carbon::diffInDays()` (compatibilité MySQL + SQLite)
- **Tests** : 11 tests `KpiObjectifsTest` — 125 tests / 315 assertions

#### Frontend
- **`api/modules/stats.ts`** : type `KpiObjectifType` exporté, `KpiCollaborateur` mis à jour (5 champs réalisé)
- **`KpiObjectifsPage.vue`** : section "Objectifs annuels" (3 KPIs avec cible + barre de progression) + section "Indicateurs de suivi" (tâches terminées/en retard, délai moyen tâche) — 1 bouton "Sauvegarder les objectifs" par collaborateur, refresh silencieux post-save
- **`router/index.ts`** : route `/kpi/objectifs`
- **`AppMenu.vue`** : lien "KPI Objectifs" dans la section Accueil (admin uniquement)

---

### Ajouts — US-32 : Portail mes documents (feature/portail-documents)

#### Backend
- **Migration** : colonne `visible_portail` (boolean, default false) ajoutée à la table `missions`
- **`Mission::$fillable` / `$casts`** : exposition de `visible_portail` en booléen
- **`MissionResource`** : champ `visible_portail` exposé
- **`UpdateMissionRequest`** : règle `sometimes|boolean` pour `visible_portail`
- **`PortailService::listerDocuments()`** : retourne les missions de l'entreprise avec `visible_portail = true` ET au moins un numéro (convention ou mandat) généré
- **`PortailDocumentController`** : 3 actions — `index()`, `conventionPdf()`, `mandatPdf()` — scope `entreprise_id` + contrôle `visible_portail` + 403 si non autorisé
- **Routes** : `GET /portail/documents`, `GET /portail/documents/{id}/convention/pdf`, `GET /portail/documents/{id}/mandat/pdf`
- **Tests** : 9 tests `PortailDocumentTest` (scope isolation, visibilité, PDF, 403) — 114 tests / 286 assertions

#### Frontend
- **`api/modules/portail.ts`** : `getDocuments()`, `telechargerConventionPdf()`, `telechargerMandatPdf()`
- **`api/modules/missions.ts`** : champ `visible_portail` ajouté à `MissionUpdatePayload`
- **`types/index.ts`** : `Mission.visible_portail: boolean`
- **`PortailDocumentsPage.vue`** : liste des documents partagés par le cabinet, téléchargement PDF direct, catégorisé par mission (convention + mandat)
- **`PortailLayout.vue`** : lien "Mes documents" ajouté dans la nav portail
- **`router/index.ts`** : route `/portail/documents` (portail-documents)
- **`MissionDetailPage.vue`** : bouton "Visible portail / Masqué portail" dans la section Documents — toggle `visible_portail` via `PUT /missions/{id}`, désactivé si aucun document généré

---

### Correctifs — Settings : sauvegarde et clés manquantes (fix/settings-save-agrement)

#### Backend
- **`Setting::set()`** : remplacé `update()` par `updateOrCreate()` — les clés inexistantes sont désormais créées automatiquement (fix silencieux)
- **`SettingController::update()`** : validation `value` passée de `required` à `nullable` — permet de sauvegarder des champs vides (NIF, NIS, RIB…)
- **`SettingsSeeder`** : ajout des clés manquantes `cabinet_agrement` (N° d'agrément), `cabinet_soustitre`, `cabinet_ville`, `convention_prefixe` (CV), `mandat_prefixe` (MD)

---

### Ajouts — US-24 : PDF convention et mandat de mission

#### Backend
- **Migration** : colonnes `convention_numero` (unique, nullable) et `mandat_numero` (unique, nullable) ajoutées à la table `missions`
- **`Mission::$fillable`** : exposition de `convention_numero` et `mandat_numero`
- **`MissionService::obtenirNumeroConvention()`** : génère et stocke le numéro CV{annee}-{seq} à la première demande (lazy, immuable ensuite) — réutilise `FacturationService::genererNumero()`
- **`MissionService::obtenirNumeroMandat()`** : idem pour MD{annee}-{seq}
- **`PdfService::genererConvention(Mission)`** : render Blade `pdf.convention` — 9 articles, honoraires 30/30/40, signatures double
- **`PdfService::genererMandat(Mission)`** : render Blade `pdf.mandat` — acceptation de mandat 1 page, signature cabinet seul
- **`PdfService::getCabinetInfo()`** : ajout clés `agrement` et `soustitre` (depuis settings)
- **`MissionController::conventionPdf()`** / **`mandatPdf()`** : endpoints `GET /api/v1/missions/{id}/convention/pdf` et `/mandat/pdf`
- **Routes** : 2 nouvelles routes GET dans le groupe backoffice
- **`MissionResource`** : exposition de `convention_numero` et `mandat_numero`
- **Tests** : 2 nouveaux tests `MissionApiTest` — génération + stockage numéro + header Content-Type PDF (105 tests / 269 assertions)

#### Frontend
- **`api/modules/missions.ts`** : ajout `conventionPdfUrl(id)` et `mandatPdfUrl(id)`
- **`types/index.ts`** : `Mission.convention_numero` et `Mission.mandat_numero` ajoutés
- **`MissionDetailPage.vue`** : section "Documents" entre les infos et les tranches — 2 cartes (Convention / Mandat) avec bouton "Générer" (1er appel) ou "Imprimer" (numéro déjà attribué), rechargement automatique après génération

---

### Correctifs — Reset CSRF token après logout (fix/csrf-token-reconnexion)

#### Frontend
- **`api/client.ts`** : export `resetCsrf()` — remet `csrfInitialized = false`
- **`stores/auth.ts`** : `logout()` appelle `resetCsrf()` après déconnexion — évite le 419 intermittent à la reconnexion (Laravel régénère le token CSRF à chaque `session()->regenerateToken()`)

---

### Refactoring — SOLID/SRP : thin controllers (refactor/solid-controllers)

#### Backend
- **`EntrepriseService`** (nouveau) : `lister()`, `wilayas()`, `creer()`, `exportCsv()` extraits de `EntrepriseController`
- **`EntrepriseController`** : réécrit thin — valide, délègue à `EntrepriseService`, retourne Resource
- **`ContactService::supprimer()`** : ajouté — `ContactController::destroy()` délègue
- **`FacturationService::listerCreances()`** : ajouté — `CreanceController` réécrit thin
- **`PortailService::listerFactures()` / `listerMissions()`** : ajoutés — `PortailFactureController` et `PortailMissionController` réécrits thin

---

### Ajouts — Vue 360° client (US-10)

#### Backend
- **`MissionService::listerMissions()`** : filtre `entreprise_id` ajouté
- **`FacturationService::listerDevis()` / `listerFactures()`** : filtre `entreprise_id` ajouté
- **`EntrepriseController::show()`** : eager load `contacts` + `users` inclus dans la réponse
- **`EntrepriseResource`** : exposition de `contacts` via `ContactResource::collection(whenLoaded)`

#### Frontend
- **`MissionFilters` / `DevisFilters` / `FactureFilters`** : champ `entreprise_id` ajouté
- **`EntrepriseDetailPage.vue`** (`/entreprises/:id`) : page dossier complet avec
  - KPIs en-tête : impayé TTC (badge orange si > 0), CA total facturé, missions actives
  - Filtre exercice partagé (missions + devis + factures rechargés simultanément)
  - Colonne gauche : coordonnées, contacts avec badge Principal, notes
  - Onglets Missions | Devis | Factures avec compteur par onglet
  - Lien cliquable vers le détail mission
- **Router** : route `entreprises/:id` → `EntrepriseDetailPage`
- **`EntrepriseListPage`** : bouton "Dossier 360°" (icône pi-eye) ajouté dans la colonne Actions

---

### Ajouts — Recherche et filtres entreprises (US-09)

#### Backend
- **`EntrepriseController::index()`** : recherche full-text élargie à raison_sociale, NIF, NIS, email, téléphone, ville + filtre combinable `wilaya`
- **`EntrepriseController::wilayas()`** : `GET /entreprises/wilayas` — liste distincte des wilayas enregistrées (ordered alphabetically)
- **`EntrepriseController::exportCsv()`** : `GET /entreprises/export-csv` — export CSV streamé avec BOM UTF-8 (compatible Excel), respecte les filtres actifs, chunk 500 lignes

#### Frontend
- **`EntrepriseFilters`** : champ `wilaya` ajouté
- **`entreprisesApi`** : méthodes `wilayas()` et `exportCsv()` ajoutées
- **`useEntreprises`** : `setStatut()`, `setWilaya()`, `resetFilters()` ajoutés
- **`EntrepriseListPage.vue`** : toolbar enrichie — champ recherche réactif (`watch`), Select statut, Select wilaya (options chargées dynamiquement), bouton "Réinitialiser" conditionnel, bouton "Export CSV"

---

### Ajouts — Contacts entreprise (US-08)

#### Backend
- **Migration `create_contacts_table`** : table `contacts` (entreprise_id FK cascade, nom, prenom, email, telephone, poste, est_principal)
- **`Contact`** : modèle Eloquent avec `belongsTo(Entreprise)`, cast boolean `est_principal`
- **`Entreprise::contacts()`** : relation `hasMany(Contact)` ajoutée
- **`ContactService::creer()`** : création avec dévalidation automatique du contact principal précédent si `est_principal = true`
- **`ContactService::mettreAJour()`** : mise à jour avec même logique de dévalidation principale
- **`ContactController`** : CRUD imbriqué — `index`, `store`, `update`, `destroy` (thin controller, délègue au service)
- **`StoreContactRequest` / `UpdateContactRequest`** : validation FormRequest avec `sometimes` pour la mise à jour partielle
- **`ContactResource`** : sérialisation JSON complète du contact
- **Routes** : `GET/POST /entreprises/{entreprise}/contacts`, `PUT/DELETE /entreprises/{entreprise}/contacts/{contact}`

#### Frontend
- **`types/index.ts`** : interface `Contact` ajoutée
- **`api/modules/contacts.ts`** : module API avec `getAll()`, `create()`, `update()`, `delete()` + interface `ContactPayload`
- **`composables/useContacts.ts`** : composable réactif avec `fetchContacts`, `createContact`, `updateContact`, `deleteContact`
- **`EntrepriseListPage.vue`** : bouton "Contacts" (icône pi-users) par ligne → dialog liste des contacts avec ajout/modification/suppression ; badge contact principal ; formulaire avec nom, prénom, poste, email, téléphone, toggle principal

---

### Ajouts — CRUD prestations (US-43)

#### Backend
- **`PrestationController::store()`** : création avec `StorePrestationRequest` (code unique, tarif, durée, actif)
- **`PrestationController::update()`** : modification partielle avec `UpdatePrestationRequest` (unique ignore id courant)
- **`PrestationController::destroy()`** : suppression protégée — HTTP 409 si missions associées
- **`Prestation::missions()`** : relation `hasMany` ajoutée pour la protection suppression
- **Routes** : `POST /prestations`, `PUT /prestations/{prestation}`, `DELETE /prestations/{prestation}`

#### Frontend
- **`api/modules/prestations.ts`** : ajout `create()`, `update()`, `delete()` + interface `PrestationPayload`
- **`composables/usePrestations.ts`** : ajout `createPrestation()`, `updatePrestation()`, `deletePrestation()`
- **`pages/prestations/PrestationListPage.vue`** : refonte — bouton Nouvelle prestation, dialog création, dialog modification pré-rempli, confirmation suppression avec message d'erreur 409

---

### Ajouts — Tri, recherche réactive, filtre exercice et onglet Avoirs

#### Backend — SRP / Services
- **`MissionService::listerMissions()`** : filtre `exercice_id`, recherche `reference` + `raison_sociale` (orWhereHas), tri avec whitelist sécurisée
- **`MissionService::mettreAJourMission()`** : extraction depuis `MissionController::update()` — SRP
- **`FacturationService::listerDevis()`** : même pattern — filtre exercice, recherche numero + raison_sociale, tri
- **`FacturationService::listerFactures()`** : idem
- **`MissionController::index()` / `update()`** délèguent entièrement au service (thin controllers)
- **`DevisController::index()` / `FactureController::index()`** idem
- **`AvoirController::indexAll()`** : `GET /api/v1/avoirs` — liste paginée avec filtre `exercice_id` + recherche numero / raison_sociale
- **`AvoirController::destroy()`** : `DELETE /api/v1/avoirs/{avoir}` — suppression d'un avoir
- **`AvoirResource`** : `facture_origine` expose un sous-ensemble `{ id, numero, entreprise.raison_sociale }` (eager load `factureOrigine.entreprise`)
- **Routes** : `GET avoirs`, `DELETE avoirs/{avoir}` dans le groupe `backoffice`

#### Frontend
- **`api/modules/avoirs.ts`** : ajout `getAll(params?)` + `delete(avoirId)` — retourne `PaginatedResponse<Avoir>`
- **`types/index.ts`** : `Avoir.facture_origine` typé en sous-ensemble `{ id, numero, entreprise? }` (aligné avec AvoirResource)
- **`composables/useMissions.ts`** : `onSort()`, `setExercice()`, debounce 300ms sur `onSearch()`
- **`composables/useDevis.ts`** : idem — `updateDevis()` étendu (`entreprise_id`, `prestation_id`, `date_devis`)
- **`composables/useFactures.ts`** : `onSort()`, `setExercice()` ajoutés
- **`pages/missions/MissionListPage.vue`** : recherche réactive (`watch`), filtre exercice pré-sélectionné, DataTable tri serveur (reference, prix_ht, date_debut, statut)
- **`pages/devis/DevisListPage.vue`** : idem + colonne date_validite + dialog Modifier étendu (tous les champs brouillon) + auto-fill date_validite = date_devis + 2 mois avec `minDate`
- **`pages/factures/FactureListPage.vue`** : refonte avec onglets **Factures | Avoirs**, filtre exercice partagé, recherche réactive par onglet, DataTable tri serveur (numero, date_facture, date_echeance, montant_ttc, statut_paiement), onglet Avoirs avec PDF + suppression

---

### Correctifs — Devis brouillon édition étendue + avoir pré-rempli

- **Devis brouillon** : dialog "Modifier" ouvre désormais tous les champs éditables (entreprise, prestation, date_devis, date_validite, notes) — auparavant limité à date_validite + notes uniquement
- **date_validite auto** : pré-remplie à `date_devis + 2 mois` à l'ouverture, contrainte `minDate = date_devis`, modifiable par l'utilisateur
- **Avoir pré-rempli** : `openAvoir()` utilise `facture.montant_ht` directement — l'ancien calcul proportionnel renvoyait 0 sur les factures soldées

---

### Correctifs — Bugs UI missions / devis / avoir (issues #20, #21, #22)

#### Frontend
- **[#20] Double modale de confirmation** : suppression du `<ConfirmDialog />` en double dans `MissionListPage.vue`, `DevisListPage.vue` et `FactureListPage.vue` — le composant global dans `AppLayout.vue` est suffisant (PrimeVue ConfirmationService est global)
- **[#21] Bouton Modifier manquant** :
  - **Missions** : ajout bouton `pi pi-pencil` + dialog pré-rempli (date_debut, date_fin, collaborateurs, notes) — réutilise `updateMission()` de `useMissions.ts`
  - **Devis** : ajout bouton `pi pi-pencil` visible uniquement sur statut `brouillon` + dialog (date_validite, notes) — ajout `updateDevis()` dans `useDevis.ts` (appelle `devisApi.update()` existant)
- **[#22] Avoir non pré-rempli** : `openAvoir()` dans `FactureListPage.vue` calcule désormais `montant_ht = montant_restant × (montant_ht / montant_ttc)` pour restituer le HT restant proportionnel

---

### Ajouts — Calendrier interactif FullCalendar (US-23)

#### Backend
- **`CalendarController::index()`** : endpoint `GET /api/v1/calendar?from=&to=&collaborateur_id=` — protégé par middleware `backoffice`
- **`CalendarService::getEvents()`** : logique métier centralisée — overlap missions (date_debut/date_fin/englobant), tâches par date_echeance, filtre collaborateur optionnel
- **`CalendarRequest`** : validation `from` (required, date), `to` (required, date, after_or_equal), `collaborateur_id` (nullable, exists:users)
- **Tests** : 8 tests `CalendarApiTest` — mission dans range, hors range, englobante, tâche dans range, hors range, filtre collaborateur, 401 non auth, 403 client

#### Frontend
- **6 packages FullCalendar** installés : `@fullcalendar/vue3`, `core`, `daygrid`, `timegrid`, `interaction`, `list`
- **`api/modules/planning.ts`** : `planningApi.getCalendar()` + interfaces `CalendarMission`, `CalendarTache`, `CalendarData`
- **`composables/usePlanning.ts`** : `fetchEvents()` (source FullCalendar), `onEventDrop()` (drag & drop missions + tâches), `onEventResize()` (missions), `fetchCollaborateurs()` — couleurs par statut
- **`pages/planning/PlanningCalendarPage.vue`** : vues mois/semaine/jour/liste, locale française, drag & drop, filtre collaborateur, dialog détail au clic, légende des couleurs, RGAA (aria-label, role="region", focus-visible, sr-only)
- **`router/index.ts`** : route `/planning` ajoutée dans le layout backoffice
- **`layout/AppMenu.vue`** : item "Planning" ajouté dans le groupe "Gestion" (visible isStaff)

### Ajouts — Dashboard KPI (US-33)

#### Backend
- **`DashboardController::stats()`** : enrichi avec filtre `exercice_id`, CA du mois, TVA collectée, taux de recouvrement, alertes dynamiques (factures en retard, taux faible)
- **`routes/api.php`** : route `/stats` déplacée dans le groupe `backoffice` (clients bloqués — 403)
- **`SettingsSeeder`** : ajout clé `seuil_alerte_recouvrement` (défaut 70%)
- **Tests** : 9 tests `DashboardKpiTest` — structure KPI, CA mois, TVA, taux recouvrement, filtre exercice, alertes retard/faible, no-alert si soldé, accès client interdit, 401 non authentifié

#### Frontend
- **`api/modules/stats.ts`** : `getDashboard(exerciceId?)` + type `DashboardStats` enrichi (`kpi`, `alertes`, `exercices`)
- **`pages/dashboard/DashboardPage.vue`** : filtre exercice, 3 widgets KPI (CA mois, TVA collectée, taux recouvrement avec barre de progression colorée), bannière d'alertes (`Message` PrimeVue)

---

### Ajouts — Portail : mes factures + mes missions (US-30, US-31)

#### Backend
- **`PortailFactureController`** : `GET /api/v1/portail/factures` (liste scoped `entreprise_id`, filtres exercice + statut), `GET /api/v1/portail/factures/{id}/pdf` (PDF sécurisé — 403 si hors scope)
- **`PortailMissionController`** : `GET /api/v1/portail/missions` (liste scoped, filtre statut), `GET /api/v1/portail/missions/{id}` (détail avec tâches, sans commentaires internes)
- **Routes** : 4 routes dans le groupe `middleware('portail')`
- **Tests** : 6 tests `PortailFactureTest` + 7 tests `PortailMissionTest` — scope isolation, filtres, PDF 403 hors scope, tâches sans commentaires, accès staff interdit

#### Frontend
- **`api/modules/portail.ts`** : `portailApi.getFactures()`, `telechargerFacturePdf()`, `getMissions()`, `getMission()`
- **`pages/portail/PortailFacturesPage.vue`** : tableau factures (numéro, date, échéance, TTC, restant dû en rouge, statut) + filtre statut + téléchargement PDF
- **`pages/portail/PortailMissionsPage.vue`** : tableau missions (référence, prestation, statut, barre d'avancement %) + dialog détail avec tâches (sans commentaires internes) — lecture seule stricte
- **`router/index.ts`** : routes `/portail/factures` et `/portail/missions`

---

### Ajouts — Portail client accès (US-29)

#### Backend
- **`PortailController::me()`** : endpoint `GET /api/v1/portail/me` — retourne le profil du client avec son entreprise (scope isolation garantie par le middleware)
- **Route** `GET /api/v1/portail/me` dans le groupe `middleware('portail')` — protégé par `EnsurePortailAccess`
- **Tests** : 11 tests `PortailAccessTest` — activation portail (création user client + mot de passe temporaire), 422 si prospect, 409 si portail déjà activé, toggle activate/désactivation, middleware bloque staff (403), middleware bloque client inactif (403), middleware autorise client actif, retour entreprise dans `/portail/me`, accès non authentifié (401)

#### Frontend
- **`layout/PortailLayout.vue`** : layout dédié portail — topbar avec logo, raison sociale entreprise, nav (Accueil / Mes factures / Mes missions), nom utilisateur, bouton déconnexion ; pas de sidebar backoffice ; footer cabinet ; responsive mobile-first ; RGAA (skip-link, aria-label, focus-visible)
- **`router/index.ts`** : route `/portail` branchée sur `PortailLayout` au lieu de `AppLayout`
- **`pages/portail/PortailDashboard.vue`** : page d'accueil portail — message de bienvenue avec nom client + raison sociale, cards accès rapide (Mes factures / Mes missions), bloc informations entreprise (régime fiscal, catégorie, NIF, ville)

---

### Ajouts — Avoir sur facture (US-16)

#### Backend
- **Migration** `create_avoirs_table` : table `avoirs` — `facture_origine_id`, `exercice_id`, `created_by`, `numero` (FA{ANNEE}-{NNN}), `date_avoir`, `montant_ht`, `taux_tva_snapshot`, `montant_tva`, `montant_ttc`, `motif`
- **`Avoir`** : modèle Eloquent avec relations `factureOrigine`, `exercice`, `createdBy`
- **`Facture::montantRestant()`** : mise à jour — soustrait désormais le total des avoirs TTC (`max(0, ttc - paye - avoirs)`)
- **`FacturationService::creerAvoir()`** : création d'avoir avec reprise du taux TVA snapshot de la facture d'origine, validation montant ≤ restant dû, numérotation séquentielle FA{ANNEE}-{NNN}
- **`PdfService::genererAvoir()`** : génération PDF DomPDF — design violet distinct de la facture, header "Avoir / Note de crédit", référence facture origine, récapitulatif financier, montant en lettres, motif
- **`AvoirController`** : `GET /factures/{id}/avoirs` (liste), `POST /factures/{id}/avoirs` (création), `GET /factures/{id}/avoirs/{avoir}/pdf` (PDF)
- **`StoreAvoirRequest`** : validation `montant_ht`, `date_avoir`, `motif`
- **`AvoirResource`** : resource JSON avec tous les champs
- **`FactureResource`** : ajout de la relation `avoirs` (chargée conditionnellement)
- **`routes/api.php`** : 3 routes avoirs nestées sous `/factures/{id}/avoirs`
- **`resources/views/pdf/avoir.blade.php`** : vue PDF avoir — couleur violette, récap financier, motif, signatures
- **Tests** : 9 tests `AvoirApiTest` — liste, création, TVA snapshot, numérotation séquentielle, montant > restant (409), réduction montant restant, validation montant/motif, accès non authentifié (62 tests en tout, 157 assertions)

#### Frontend
- **`types/index.ts`** : interface `Avoir` ajoutée, `Facture` enrichi avec `avoirs?`
- **`api/modules/avoirs.ts`** : `avoirsApi.index()`, `avoirsApi.store()`, `avoirsApi.telechargerPdf()`
- **`pages/factures/FactureListPage.vue`** : bouton "Émettre un avoir" dans les actions, dialog avec récap restant dû, saisie montant HT + date + motif, toast succès/erreur

---

### Ajouts — Relances et créances impayées (US-25, US-26, US-27, US-28)

#### Backend
- **Migration** `add_annulee_to_relances_statut` : ajout du statut `annulee` à l'enum `relances.statut` (nécessaire pour `CancelRelancesOnPayment`)
- **`RelanceService`** : logique métier centralisée — `envoyerManuelle()` (niveau choisi, mail immédiat), `envoyerAutomatique()` (vérification doublon + séquentialité des niveaux), résolution des templates depuis `settings` avec remplacement des variables `{{client}}`, `{{montant}}`, `{{numero_facture}}`, `{{echeance}}`
- **`RelanceController`** : `GET /api/v1/factures/{id}/relances` (historique), `POST /api/v1/factures/{id}/relances` (relance manuelle)
- **`CreanceController`** : `GET /api/v1/creances` — liste des factures `en_attente` ou `partiel` triées par ancienneté d'échéance
- **`FactureResource`** : ajout du champ `montant_restant` (calculé) et `relances` (relation conditionnelle)
- **`RelanceResource`** : resource JSON dédiée (niveau, type, statut, email, envoyee_le, sentBy)
- **`RelanceClientMail`** + `resources/views/mail/relance.blade.php` : email HTML avec badge niveau (rappel / relance ferme / mise en demeure), récapitulatif financier, style responsive
- **`EnvoyerRelancesJob`** : job quotidien (`ShouldQueue`) — sélectionne les factures échues, détermine le niveau attendu selon les délais settings, envoie uniquement si le niveau précédent a déjà été traité
- **`routes/console.php`** : scheduler — `EnvoyerRelancesJob` déclenché chaque jour à 8h00
- **`SettingsSeeder`** : ajout des 3 templates mail par défaut (`relance_template_n1/n2/n3`)
- **`resend/resend-laravel`** : package installé — driver `resend` pour envoi email en production
- **Tests** : 9 tests `RelanceApiTest` — liste créances, exclusion soldées, inclusion partielles, relance manuelle, blocage sur facture soldée, validation niveau, historique, anti-doublon automatique, blocage niveau2 sans niveau1

#### Frontend
- **`api/modules/relances.ts`** : `relancesApi.indexParFacture()` + `relancesApi.store()`
- **`api/modules/creances.ts`** : `creancesApi.index()`
- **`types/index.ts`** : interface `Relance` ajoutée, `Facture` enrichi avec `montant_restant` et `relances?`
- **`pages/relances/CreancesPage.vue`** : tableau créances impayées avec retard en jours, montant restant dû, badge statut, bouton relance manuelle avec dialog de sélection du niveau
- **`pages/relances/RelancesConfigPage.vue`** : configuration des délais (J+15/30/45) et templates des 3 niveaux avec variables disponibles, sauvegarde via `settingsApi`
- **`router/index.ts`** : routes `/creances` et `/relances/config`
- **`AppMenu.vue`** : section Facturation enrichie — liens Créances et Relances

---

### Corrections — PDF facture : en-tête, désignation, TypeError

- **`FactureController::pdf()`** : type de retour corrigé `StreamedResponse` → `Illuminate\Http\Response` — DomPDF `.stream()` / `.download()` retourne une `Response` standard
- **`pdf/facture.blade.php` + `pdf/devis.blade.php`** : logo `[L] Ledge` en haut à gauche + nom du cabinet en grand — adresse, téléphone et coordonnées du cabinet retirés de l'en-tête (conservés dans le footer)
- **`pdf/facture.blade.php`** : désignation de la ligne = nom de la prestation de la mission + pourcentage de tranche calculé depuis les montants réels (`ligne.total_ht / mission.prix_ht`) — indépendant du snapshot `designation` stocké en base

---

### Ajouts — Facturation : création facture avec tranches automatiques (US-13)

#### Backend
- **Migration** : `add_mode_paiement_to_factures_table` — colonne `mode_paiement` (default `non_defini`) + `make_timbre_taux_id_nullable_on_factures`
- **`FacturationService::creerFacture()`** : entièrement réécrit — détection automatique de la tranche (T1 30%, T2 30%, T3 40%) par comptage des factures `FF` de la mission, `lockForUpdate()` pour éviter les doublons en concurrence, snapshot TVA historisée via `TvaTaux::enVigueurLe($dateFacture)`, date échéance auto = date_facture + 45 jours, `montant_timbre = 0` (timbre ignoré), `mode_paiement = non_defini` à la création
- **`FacturationService::creerFacture()`** : lève `DomainException` si ≥ 3 factures existent déjà pour la mission
- **`PaiementController`** : met à jour `mode_paiement` sur la facture lors de l'enregistrement d'un paiement
- **`PdfService::genererFacture()`** : génère le PDF de facture (template A4 identique au devis, sans timbre, avec mention mode de règlement)
- **`FactureController::pdf()`** : route `GET /api/v1/factures/{id}/pdf`
- **`StoreFactureRequest`** : simplifié — `mission_id` + `date_facture` + `notes` uniquement
- **`StorePaiementRequest`** : `mode_paiement` accepte `virement`, `cheque`, `autre`
- **`FactureResource`** : expose `mode_paiement`
- **Vue Blade `pdf/facture.blade.php`** : template A4 — en-tête cabinet, bloc destinataire, tableau prestation (tranche label, HT, TVA, TTC), totaux, montant en lettres, mode de règlement (RIB si virement), signatures « Bon pour acquit »
- **Tests** : 8 tests `FactureApiTest` — tranche1, tranches T1/T2/T3, erreur >3 tranches, snapshot TVA, paiement partiel, solde, suppression bloquée avec paiements, paiement sur facture soldée bloqué

#### Frontend
- **`api/modules/factures.ts`** : `FacturePayload` simplifié (`mission_id`, `date_facture`, `notes`), ajout `getPdf()` avec `responseType: blob`, `PaiementPayload` avec `autre` comme 3e mode
- **`types/index.ts`** : `Facture` enrichi avec `mode_paiement`
- **`composables/useFactures.ts`** : ajout `telechargerPdf(id, numero)` — pattern blob download
- **`pages/factures/FactureListPage.vue`** : formulaire basé sur mission (plus entreprise), date facture par défaut aujourd'hui, aperçu date échéance (+45j, non modifiable), hint tranche suivante, dialog paiement avec 3 modes, bouton PDF sur chaque ligne

---

### Ajouts — Missions : tâches complètes + statuts mission (US-19)

#### Backend
- **`UpdateTacheRequest`** (nouveau) : FormRequest dédié à la mise à jour des tâches — `titre` en `sometimes` (optionnel) permettant la mise à jour partielle du statut seul sans re-valider tous les champs
- **`TacheController::update()`** : injecte désormais `UpdateTacheRequest` au lieu de `StoreTacheRequest` — SRP respecté (store et update ont des règles différentes)

#### Frontend
- **`MissionListPage.vue`** : ajout `MultiSelect` collaborateurs dans le dialog de création de mission
- **`MissionDetailPage.vue`** : refonte complète —
  - Boutons de changement de statut mission (Reprendre / Suspendre / Terminer / Annuler) selon le statut courant
  - Collaborateurs affichés en chips dans le bloc info
  - Sélecteur `assigné à` (Select user) dans le dialog nouvelle tâche
  - Bouton supprimer tâche avec `ConfirmDialog` + gestion erreur 409 (tâche avec commentaires)
  - Cohérence des tags de statut tâche

---

### Corrections — PDF devis : timbre retiré + montant en lettres

#### Backend
- **`FacturationService::creerDevis()`** : `montant_timbre` forcé à 0 — le timbre fiscal s'applique uniquement sur les factures, pas sur les devis
- **`PdfService::montantEnLettres()`** : nouvelle méthode — convertit un montant float en lettres françaises via `NumberFormatter` (PHP intl, locale `fr`), suffixe « Dinars Algériens »
- **Test** `test_tva_calcule_sur_prix_ht_sans_timbre` : assertions mises à jour (`montant_timbre = 0`, TTC = HT + TVA)

#### Frontend (PDF)
- **`pdf/devis.blade.php`** : colonne Timbre et ligne "Timbre fiscal" supprimées du tableau et des totaux
- Bandeau *Arrêté le présent devis à la somme de **[montant en lettres]*** ajouté sous le bloc TOTAL TTC

---

### Ajouts — PDF devis (US-12)

#### Backend
- **PdfService** : service dédié DomPDF — `genererDevis()` charge les données cabinet (`Setting`) et rend la vue Blade ; conçu pour accueillir `genererFacture()` (US-14)
- **Vue Blade `pdf/devis.blade.php`** : template A4 portrait — en-tête cabinet (nom/NIF/NIS/RIB/adresse/tél), bloc destinataire (raison sociale/NIF/NIS/RC), tableau prestation (code, désignation, prix HT, TVA, timbre), récapitulatif totaux, zone double signature avec mention « Bon pour accord »
- **Route** : `GET /api/v1/devis/{id}/pdf` — streame le PDF directement au client
- **DevisController::pdf()** : délègue à `PdfService`, retourne un stream `application/pdf`
- **Test** : `test_pdf_devis_retourne_un_pdf` — vérifie HTTP 200 + Content-Type `application/pdf`

#### Frontend
- **`api/modules/devis.ts`** : ajout `getPdf(id)` avec `responseType: 'blob'`
- **`composables/useDevis.ts`** : ajout `telechargerPdf(id, numero)` — crée un object URL, déclenche le téléchargement, révoque l'URL
- **`pages/devis/DevisListPage.vue`** : bouton PDF (icône `pi-file-pdf`) visible pour tous les statuts sauf `brouillon`

### Refactoring SOLID — Devis workflow

#### Backend
- **SRP** : logique de transition d'état déplacée du controller vers `FacturationService` — nouvelles méthodes `envoyerDevis()`, `accepterDevis()`, `refuserDevis()`, `supprimerDevis()`, `mettreAJourDevis()` ; chaque méthode lève `DomainException` si la transition est invalide
- **FormRequests** : ajout `UpdateDevisRequest` (notes, date_validite) et `ConvertirEnMissionRequest` (date_debut, date_fin, collaborateur_ids) — suppression des `$request->validate()` inline dans `update()` et `convertirEnMission()`
- **DRY** : `MissionService::genererReference()` supprimée — délègue désormais à `FacturationService::genererNumero()` via le nouveau paramètre optionnel `$colonne` (défaut `'numero'`)
- **DevisController** : réduit à validation + délégation + retour Resource ; gestion HTTP 409 centralisée par `catch (DomainException)`

### Corrections — Devis : une prestation unique (US-11)

#### Backend
- **Migration** : suppression `devis_lignes`, ajout `prestation_id` + `prix_ht` sur `devis`
- **FacturationService::creerDevis()** : calcul automatique `tarif × indice_regime × indice_categorie` — prix saisi manuellement supprimé
- **StoreDevisRequest** : `prestation_id` requis, suppression `lignes[]`
- **DevisResource** : expose `prestation` et `prix_ht` au lieu des lignes
- **Tests** : 7 tests DevisApiTest réécrits (grille tarifaire, TVA, timbre, validation)

#### Frontend
- **`api/modules/devis.ts`** : `DevisPayload` passe à `prestation_id`, suppression `DevisLignePayload`
- **`types/index.ts`** : suppression `DevisLigne`, `Devis` aligné sur le backend
- **`pages/devis/DevisListPage.vue`** : formulaire — section lignes remplacée par `Select` prestation

### Ajouts — Portail client + Dashboard KPI + Refonte design

#### Backend — Portail client (US-29)
- **PortailService** : activation accès portail depuis fiche entreprise — création user `client` avec mot de passe temporaire, toggle actif/inactif
- **EntrepriseController** : méthodes `activerPortail` et `togglePortail`
- **EntrepriseResource** : exposition `portail_user` (id, email, portail_actif) via `whenLoaded`
- Routes : `POST /api/v1/entreprises/{id}/activer-portail`, `POST /api/v1/entreprises/{id}/toggle-portail`

#### Backend — Dashboard KPI (US-33 partiel)
- **DashboardController** : endpoint `GET /api/v1/stats` — compteurs entreprises/missions/factures/devis, CA HT/TTC, impayés, en retard, 5 derniers éléments

#### Frontend — Refonte design
- Nouveau système de layout PrimeVue 4 : `AppLayout`, `AppTopbar`, `AppSidebar`, `AppMenu`, `AppMenuItem`, `AppFooter`, `AppConfigurator`
- `FloatingConfigurator` : switch thème Aura/Lara en live
- Styles globaux refaits : `layout.scss`, `tailwind.css`
- Module API `stats.ts` pour le dashboard
- Pages mises à jour : `LoginPage`, `DashboardPage`, `EntrepriseListPage`

### A faire
- Module Relances (automatiques via queue + manuelles)
- Portail client (lecture seule factures/documents)
- Module KPI / Reporting (CA, missions, performance collaborateurs)
- Module Documents / GED
- PDF facture conforme DGI (US-14) — `PdfService::genererFacture()` + montant en lettres
- Avoirs (FA) — creation depuis facture existante

---

## [0.5.0] — 2026-03-25

### Ajouts — Module Missions + Taches

#### Backend — MissionService + Controllers

- **MissionService** — couche metier centralisee :
  - `creerMission()` : calcul automatique du prix HT (`prestation.tarif_initial x regime_fiscal.indice x categorie.indice`),
    generation de la reference sequentielle (M2026-001), creation mission + rattachement collaborateurs (pivot `mission_user`)
  - `updateMission()` : modification statut/dates/notes/collaborateurs. Prix HT immuable apres creation
  - `deleteMission()` : suppression bloquee si factures associees (HTTP 409)
- **MissionController** — CRUD complet avec filtres (entreprise, statut), eager loading relations
- **TacheController** — CRUD nested sous `/missions/{id}/taches`, changement statut inline
- **Conversion Devis → Mission** — `POST /devis/{id}/convertir-en-mission` : cree une mission depuis un devis accepte/envoye
- **FormRequests** : `StoreMissionRequest`, `UpdateMissionRequest`, `StoreTacheRequest`
- **Resources** : `MissionResource` (avec entreprise, prestation, taches, factures), `TacheResource`

#### Frontend — Pages Missions

- **API modules** : `missions.ts`, `taches.ts` — CRUD complet
- **Composables** : `useMissions.ts`, `useTaches.ts` — logique reactive
- **MissionListPage** — DataTable (reference, entreprise, prestation, prix HT, statut) + Dialog creation (select entreprise/prestation)
- **MissionDetailPage** — detail mission, tranches suggerees (30/30/40), section taches avec statut inline, factures liees
- **Router** : routes `/missions` et `/missions/:id`
- **Sidebar** : ajout item Missions (pi-briefcase) dans la navigation

#### Tests

- **MissionApiTest** (7 tests) : prix HT calcule, bascule prospect→client, CRUD, reference sequentielle, protection suppression
- **TacheApiTest** (3 tests) : creation, liste, mise a jour statut
- **Frontend** (9 tests) : modules API missions + taches

### Corrections

- **fix(auth)** : roles Spatie retournes comme objets `[{name: 'admin', ...}]` — normalisation en strings `['admin']` pour que `isAdmin` fonctionne dans le sidebar
- **fix(routes)** : parametre route taches `{tach}` → `{tache}` (singularisation Laravel incorrecte pour le francais)
- **fix(model)** : ajout `$attributes` defaults sur Tache (statut, priorite) pour coherence modele/BDD

---

## [0.4.0] — 2026-03-25

### Ajouts — Sprint 1 Facturation

#### Backend — Service Layer Facturation

- **FacturationService** — couche metier centralisee :
  - `genererNumero()` : numerotation sequentielle par exercice avec `lockForUpdate()` pour eviter
    les doublons en concurrence (format `{prefixe}{annee}-{sequence}`, ex: FF2026-001)
  - `creerDevis()` : creation devis + lignes, calcul automatique TVA/timbre depuis la date du devis
  - `creerFacture()` : creation facture + lignes avec **snapshots immuables** (taux_tva, montant_tva,
    montant_timbre, montant_ttc copies a la creation et jamais recalcules)
  - `recalculerStatutPaiement()` : mise a jour automatique du statut facture
    (en_attente → partiel → solde) selon la somme des paiements enregistres

- **Controllers REST** (dans `Http/Controllers/Facturation/`) :
  - `DevisController` — CRUD complet. Update/delete limites aux devis en statut `brouillon`
  - `FactureController` — Create/Read/Delete. Suppression bloquee si paiements associes (HTTP 409)
  - `PaiementController` — Create/Read/Delete, nested sous `/factures/{id}/paiements`.
    Enregistrement bloque si facture deja soldee (HTTP 409)

- **FormRequests** — validation stricte des inputs :
  - `StoreDevisRequest` : entreprise_id (exists), dates, lignes[].designation/quantite/prix_unitaire_ht
  - `StoreFactureRequest` : entreprise_id, type (in:FF,FA), dates, lignes
  - `StorePaiementRequest` : montant (integer >0), date_paiement, mode_paiement (in:virement,cheque,espece,autre)

- **API Resources** — serialisation JSON coherente :
  - DevisResource + DevisLigneResource (avec relations lignes incluses)
  - FactureResource + FactureLigneResource (avec snapshots TVA/timbre)
  - PaiementResource

- **Events / Listeners** — decouplage metier via evenements Laravel :
  - `MissionCreated` → `ConvertProspectToClient` : bascule automatique entreprise prospect → client
    a la creation de la premiere mission (via MissionObserver)
  - `InvoicePaid` → `CancelRelancesOnPayment` : annulation automatique des relances en cours
    quand une facture passe au statut solde

- **Routes API** ajoutees dans `routes/api.php` :
  - `GET|POST /api/v1/devis`, `GET|PUT|DELETE /api/v1/devis/{id}`
  - `GET|POST /api/v1/factures`, `GET|DELETE /api/v1/factures/{id}`
  - `GET|POST /api/v1/factures/{facture}/paiements`, `DELETE /api/v1/factures/{facture}/paiements/{paiement}`

- **Protection suppression** renforcee :
  - EntrepriseController : retourne 409 si devis ou missions associes

#### Frontend — Composables + API Modules

- **API Modules** (`api/modules/`) — couche d'abstraction HTTP, jamais d'appel Axios direct
  dans les composants :
  - `entreprises.ts` : getAll (pagine + search), getOne, create, update, delete
  - `exercices.ts` : getAll, getCurrent, create, update, delete
  - `prestations.ts` : getAll, calculerPrix (appel formule backend)
  - `users.ts` : getAll (pagine), getOne, create, update, delete
  - `settings.ts` : getAll, update (batch cle/valeur)
  - `devis.ts` : getAll (pagine), getOne, create, update, delete + DevisPayload/DevisLignePayload types
  - `factures.ts` : getAll (pagine), getOne, create, delete + createPaiement, getPaiements, deletePaiement

- **Composables** (`composables/`) — logique reactive reutilisable, separee des composants Vue :
  - `useEntreprises` : liste reactive, pagination, search, CRUD avec refresh auto
  - `useUsers` : idem pour utilisateurs
  - `useExercices` : liste + CRUD exercices fiscaux
  - `usePrestations` : liste + calcul prix HT
  - `useSettings` : liste + sauvegarde batch
  - `useDevis` : liste paginee, CRUD, updateStatut (brouillon → envoye → accepte/refuse)
  - `useFactures` : liste paginee, CRUD, addPaiement avec refresh statut

- **Types TypeScript** enrichis (`types/index.ts`) :
  - `Mission` : id, entreprise_id, exercice_id, prestation_id, numero, prix_ht, statut, dates
  - `DevisLigne` / `Devis` : lignes avec designation/quantite/prix, statut (brouillon→envoye→accepte→refuse→expire)
  - `FactureLigne` / `Facture` : snapshots taux_tva/montant_tva/montant_timbre/montant_ttc, statut_paiement
  - `Paiement` : montant, date, mode_paiement (virement|cheque|espece|autre), reference

- **Pages refactorisees** avec composables + CRUD complet via PrimeVue Dialog :
  - `EntrepriseListPage` : DataTable + Dialog creation/edition + ConfirmDialog suppression + Select regime/categorie/statut
  - `UserListPage` : DataTable + Dialog avec Password component + Select role
  - `ExerciceListPage` : DataTable + Dialog avec DatePicker
  - `PrestationListPage` : refactorisee avec usePrestations (lecture seule)
  - `SettingsPage` : refactorisee avec useSettings

- **Nouvelles pages** :
  - `DevisListPage` : creation avec lignes dynamiques (ajout/suppression), Select entreprise avec filtre,
    actions statut (envoyer), suppression limitee aux brouillons
  - `FactureListPage` : creation avec lignes + dialog paiement separe (montant, date, mode, reference),
    affichage montant restant du

- **Router** : routes `/devis` et `/factures` ajoutees avec meta backoffice
- **AdminLayout** : menu items Devis (pi-file) et Factures (pi-receipt) ajoutes dans la sidebar

#### Tests

- **Backend PHPUnit** — 28 tests, 59 assertions, tous passent sur SQLite :memory: :
  - `EntrepriseApiTest` (6 tests) : list pagine, create, update, delete protege si missions/devis, delete OK, search
  - `DevisApiTest` (5 tests) : create avec lignes, calcul TVA+timbre, list, delete protege si non-brouillon, numerotation sequentielle
  - `FactureApiTest` (6 tests) : create, snapshot TVA immuable, paiement → statut partiel, paiement complet → solde,
    delete protege si paiements, impossible de payer une facture soldee
  - `TvaRateTest` (3 tests) : taux correct par date, null avant tout taux, accepte string date
  - `TimbreRateTest` (3 tests) : calcul avec plafond, taux correct, null avant tout taux
  - `PrestationTest` (1 test) : prix_ht = tarif × indice_regime × indice_categorie
  - `ConvertProspectToClientTest` (2 tests) : prospect→client a la mission, client reste client
  - Tests Filament V1 supprimes (obsoletes depuis migration N-tier)

- **Frontend Vitest** — 18 tests, tous passent :
  - `types.test.ts` (5 tests) : validation interfaces Entreprise, Facture, Paiement, Devis, PaginatedResponse
  - `api-modules.test.ts` (13 tests) : mock Axios via `vi.hoisted()`, verification des appels HTTP corrects
    pour entreprises, exercices, devis, factures, settings

#### CI/CD

- **GitHub Actions** (`.github/workflows/ci.yml`) — pipeline declenchee sur push/PR vers main et develop :
  - Job `backend` : PHP 8.2, Composer cache, Laravel Pint (lint), PHPUnit sur SQLite :memory:
  - Job `frontend` : Node 20, npm ci, Vitest, vue-tsc (type check), Vite build

---

## [0.3.0] — 2026-03-23

### Correctifs
- fix(frontend): dark mode Aura — correction variables couleurs layout + fix CSRF proxy

---

## [0.2.0] — 2026-03-23

### Ajouts — Frontend Vue 3 + Pages CRUD

#### Frontend Setup
- Vue 3 + TypeScript + Vite + PrimeVue 4 (theme Aura) + Pinia + Vue Router
- Client Axios avec intercepteurs CSRF Sanctum
- Layouts AdminLayout (sidebar desktop / hamburger mobile) + PortailLayout
- Router avec guards auth + role (backoffice vs portail)
- Pinia store auth (login/logout/me)
- CSS mobile-first + skip-link RGAA + focus-visible

#### Pages CRUD
- LoginPage — authentification Sanctum SPA
- DashboardPage — page d'accueil admin (contenu a venir)
- UserListPage — liste + creation/edition utilisateurs avec roles
- EntrepriseListPage — CRUD entreprises (raison sociale, NIF, regime fiscal, categorie)
- ExerciceListPage — CRUD exercices fiscaux
- PrestationListPage — CRUD prestations (catalogue tarifaire)
- SettingsPage — parametres globaux cle/valeur
- PortailDashboard — page portail client (contenu a venir)

---

## [0.1.0] — 2026-03-16

### Ajouts — Backend Laravel API

#### Infrastructure & Configuration
- Laravel 12 (PHP 8.3) + Sanctum v4 (SPA cookie-based) + Spatie Permission v7.2 + DomPDF + Spatie Health
- MySQL 9.1 — fix `ROW_FORMAT=DYNAMIC` pour utf8mb4 (WAMP)
- `Schema::defaultStringLength(191)` dans `AppServiceProvider`
- Middleware `EnsureBackofficeAccess` — bloque les clients sur le back-office
- Middleware `EnsurePortailAccess` — bloque les non-clients, verifie `portail_actif`
- CORS configure pour `localhost:5173`

#### API REST
- AuthController — login/logout/me (Sanctum SPA)
- UserController — CRUD utilisateurs + assignation roles Spatie
- EntrepriseController — CRUD entreprises
- ExerciceController — CRUD exercices fiscaux
- PrestationController — CRUD prestations
- SettingController — CRUD parametres cle/valeur
- FormRequests de validation sur tous les endpoints
- API Resources JSON par domaine

#### Base de donnees — 17 migrations
- `users` + `users.entreprise_id` (nullable FK) + `users.portail_actif`
- `entreprises` — clients & prospects avec regime fiscal et categorie
- `exercices` — exercices fiscaux par annee avec statut `ouvert/cloture`
- `tva_rates` + `timbre_rates` — historique taux avec `date_debut`/`date_fin`
- `settings` — parametres cle/valeur par groupe
- `prestations` + `regimes_fiscaux` + `categories_entreprise` — grille tarifaire
- `missions` + `mission_user` — missions et affectation collaborateurs
- `taches` + `tache_commentaires` — planning et commentaires
- `devis` + `devis_lignes` — gestion des devis
- `factures` + `facture_lignes` — facturation FF/FA avec snapshot TVA
- `paiements` — suivi des encaissements
- `relances` — journal des relances
- `documents` — stockage et partage portail

#### Donnees initiales seedees
- 4 roles : `admin`, `collaborateur`, `secretaire`, `client`
- Compte admin : `admin@ledge.dz` / `password`
- TVA 19% (standard) + 9% (reduit) — LF 2023
- Timbre fiscal 1% plafonne 2 500 DA — LF 2024
- 5 prestations reelles (CAC, ACMPT, AENT, ASSC, A&C)
- Indices regimes fiscaux (Forfait x1.0, Reel x1.5) et categories (TPE x1.0, PME x1.75, GE x2.0)
- Exercice fiscal 2026 ouvert
- 12 parametres `settings` initiaux

#### Modeles Eloquent (18)
- Relations, casts et scopes configures
- `TvaRate::enVigueurLe($date)` + `TimbreRate::enVigueurLe($date)` — resolution historique
- `Prestation::calculerPrixHt($regime, $categorie)` — formule grille tarifaire
- `Exercice::current()` — exercice ouvert de l'annee en cours
- `User::canAccessPanel()` — controle d'acces par panel (backoffice vs portail)

---

## Branches actives

| Branche | Objectif | Statut |
|---|---|---|
| `main` | Production stable | init seulement |
| `develop` | Integration continue | actif |
| `feature/backend-setup` | Laravel API scaffold | merge |
| `feature/auth-api` | Auth Sanctum + Users | merge |
| `feature/core-api` | Controllers CRUD API | merge |
| `feature/frontend-setup` | Vue 3 + PrimeVue + Layout | merge |
| `feature/core-pages` | Pages CRUD frontend | merge |
| `fix/dark-mode-colors` | Fix theme dark mode | merge |
| `feature/facturation` | Sprint 1 — devis, factures, paiements, CI | en cours |

---

## Convention de commits

```
feat(module): description courte
fix(module): description du correctif
chore(deps): mise a jour dependance X
test(module): ajout tests unitaires
docs(changelog): mise a jour journal
refactor(module): refactoring sans changement fonctionnel
```

### Exemples Ledge
```
feat(facturation): calcul automatique TVA + timbre fiscal avec snapshot
feat(portail): activation acces client depuis fiche entreprise
feat(relances): relance automatique J+15 via queue Laravel
fix(factures): snapshot tva_taux_id manquant a la creation d'avoir
fix(exercices): numerotation annuelle ne se reinitialisait pas
chore(deps): mise a jour Laravel 12.x.0
test(facturation): FormuleHTPrixServiceTest — 12 cas couverts
docs(changelog): v0.1.0 — setup initial + migrations + seeders
```
