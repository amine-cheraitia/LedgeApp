# Ledge — Stratégie de tests

> RNCP 39583 · Expert en Développement Logiciel · YNOV · Cheraitia Mohamed Amine
> Compétences visées : **C2.2.2** (tests unitaires / harnais de test) · **C2.2.3** (sécurité OWASP & accessibilité RGAA)

Ce document décrit la stratégie de tests automatisés de Ledge : ce qui est testé, comment,
et comment mesurer la couverture. Il accompagne le [cahier de recettes](CAHIER-RECETTES.md)
(recette fonctionnelle manuelle) : ici, on parle de **tests automatisés reproductibles**.

---

## 1. Vue d'ensemble

| Couche | Outil | Tests | Fichiers | Couverture lignes |
|---|---|---|---|---|
| **Backend** (Laravel API) | PHPUnit 11 | **414** | 40 | **95 %** |
| **Frontend** (Vue 3 SPA) | Vitest 4 + @vue/test-utils | **557** | 48 | **83 %** |
| **Total** | | **971** | | |

> Suite **calibrée** pour rester proportionnée (RNCP C2.2.2 : un harnais couvrant les fonctionnalités,
> pas une inflation de tests) tout en dépassant 80 % de couverture de lignes des deux côtés. Un
> **gate à 80 %** (`--min=80` back, `thresholds` front) échoue si la couverture régresse.

> La métrique de référence n'est pas le nombre de tests mais la **couverture de la logique
> critique**. Côté backend (règles métier : TVA, facturation, numérotation), la couverture est
> volontairement élevée sur les Services (96–100 %). Côté frontend, la couverture porte sur la
> robustesse de l'IHM (pages, formulaires, gardes de navigation).

---

## 2. Pyramide de tests

```
                 ┌─────────────────────────────┐
                 │   Recette manuelle (E2E)     │   CAHIER-RECETTES.md
                 │   scénarios utilisateur      │   (hors périmètre auto)
                 ├─────────────────────────────┤
        Feature  │  383 tests API backend       │   HTTP réel -> BDD SQLite mémoire
        + pages  │  ~430 tests composants front │   montage réel des composants
                 ├─────────────────────────────┤
        Unit     │   31 tests Services/Modèles  │   logique métier isolée
                 └─────────────────────────────┘
```

- **Unit (backend)** — la logique métier pure, sans HTTP : calcul des tranches, TVA historisée,
  numérotation par exercice, bascule prospect→client (Listener).
- **Feature (backend)** — chaque endpoint est testé de bout en bout (requête HTTP → middleware →
  FormRequest → Service → BDD → Resource JSON), sur une base **SQLite en mémoire** recréée à
  chaque test (`RefreshDatabase`). C'est le niveau qui donne le plus de couverture par test :
  un seul test d'endpoint traverse Controller + FormRequest + Service + Modèle + Resource + Policy.
- **Composants / pages (frontend)** — chaque page est **montée réellement** (`@vue/test-utils` +
  happy-dom) avec ses dépendances (PrimeVue, Pinia, Vue Router), les appels réseau étant simulés.

---

## 3. Stratégie backend (PHPUnit)

**Répartition** : 31 tests unitaires (`tests/Unit`) + 383 tests de fonctionnalité (`tests/Feature`).
Environnement de test isolé : SQLite `:memory:`, cache `array`, mail `array`, queue `sync`
(voir `backend/phpunit.xml`).

**Couverture des Services (logique métier)** — les plus critiques pour le titre :

| Service | Couverture | Rôle |
|---|---|---|
| `TvaTauxService`, `KpiService`, `InvitationService`, `AuditService`, `CalendarService` | 100 % | — |
| `FacturationService` | 96 % | Facturation, tranches, snapshots TVA |
| `PortailService` | 96 % | Isolation des données client |
| `MissionService` | 95 % | Création mission, prix HT immuable |
| `RelanceService` | 94 % | Relances automatiques |

### Exemple — règle métier critique (tranches, anti perte d'arrondi)

`tests/Unit/Services/FacturationServiceTest.php` — vérifie l'**invariant** `T1 + T2 + T3 = prix_ht`
même avec des centimes (la 3ᵉ tranche absorbe l'arrondi) :

```php
public function test_somme_des_trois_tranches_egale_le_prix_ht_avec_centimes(): void
{
    $this->mission->update(['prix_ht' => 100.01]);

    $f1 = $this->service->creerFacture([...'date_facture' => '2026-01-15'], $admin->id);
    $f2 = $this->service->creerFacture([...'date_facture' => '2026-02-15'], $admin->id);
    $f3 = $this->service->creerFacture([...'date_facture' => '2026-03-15'], $admin->id);

    $somme = (float) $f1->montant_ht + (float) $f2->montant_ht + (float) $f3->montant_ht;
    $this->assertEquals(100.01, round($somme, 2));   // pas de dinar perdu
    $this->assertEquals(40.01, (float) $f3->montant_ht); // T3 = solde exact
}
```

### Exemple — TVA historisée (snapshot immuable)

`tests/Unit/Models/TvaTauxTest.php` — le taux dépend de la **date de facturation**, jamais de
`now()` : une facture de 2022 retrouve 17 %, une de 2026 retrouve 19 %.

```php
$ancien  = TvaTaux::enVigueurLe('2022-06-15');
$this->assertEquals(17, (float) $ancien->taux);
$nouveau = TvaTaux::enVigueurLe('2026-03-25');
$this->assertEquals(19, (float) $nouveau->taux);
```

### Exemple — sécurité (OWASP : brute-force login)

`tests/Feature/Api/LoginTest.php` — vérifie le rate limiting `throttle:5,1` :

```php
for ($i = 0; $i < 5; $i++) {
    $this->postJson('/api/v1/login', ['email' => 'brute@test.dz', 'password' => 'mauvais'])
        ->assertStatus(422);
}
// 6ᵉ tentative dans la minute -> bloquée
$this->postJson('/api/v1/login', [...])->assertStatus(429);
```

Autres domaines couverts en Feature : isolation portail (un client ne voit que ses données),
permissions par rôle (secrétaire/collaborateur/admin), protection de suppression (409 si
dépendances), pagination, validation des FormRequests.

---

## 4. Stratégie frontend (Vitest)

**Répartition** (48 fichiers) : 24 pages · 14 composables · 6 (client API, guard router, utils) ·
2 layout · 1 store · 1 composant.

### Harnais de montage partagé

Pour ne pas réécrire la configuration à chaque test, un harnais commun
`src/__tests__/helpers/mount.ts` expose `mountPage()` : il installe **PrimeVue + services
Toast/Confirm + Pinia + un router mémoire**, connecte un utilisateur selon son rôle, et neutralise
les composants incompatibles avec l'environnement de test headless (**Chart.js**, **FullCalendar**).

```ts
// Un test de page type : on simule l'API, puis on monte la vraie page
vi.mock('@/api/modules/prestations', () => ({ prestationsApi: { getAll: mockGetAll, ... } }))
const { wrapper } = await mountPage(PrestationListPage, { role: 'admin' })

expect(mockGetAll).toHaveBeenCalledOnce()
expect(wrapper.text()).toContain('Assistance comptable')
```

**Principes :**
- On simule la **frontière réseau** (`@/api/modules/*`), jamais la logique du composant.
- On teste le **comportement observable** : rendu, appels API avec les bons arguments, réactions
  aux rôles, états vides / de chargement / d'erreur, accessibilité (`role="alert"`, `aria-label`).
- Les branches par rôle sont couvertes en remontant la page avec `{ role: 'secretaire' }`, etc.

Domaines couverts : dashboard (admin/collaborateur/secrétaire), entreprises, facturation, devis,
missions & planning, portail client, relances, utilisateurs, paramètres, la **garde de navigation**
`router.beforeEach` (redirections par rôle) et le **client Axios** (`normalizeApiError` : mapping
des statuts 401/403/419/422/429/5xx).

---

## 5. Couverture & garde-fou anti-régression

La couverture est **mesurable et vérifiée par un seuil**. Si un développement futur fait chuter la
couverture sous le seuil, la commande échoue (utilisable en intégration continue).

| | Seuil | Commande |
|---|---|---|
| Backend | `--min=80` | `composer test:coverage` |
| Frontend | 85 % (lignes/branches/fonctions/instructions) | `npm run test:coverage` |

Le seuil frontend est déclaré dans `frontend/vite.config.ts` (`test.coverage.thresholds`).
Rapports HTML navigables : `backend/coverage-html/index.html` (via `composer test:coverage-html`)
et `frontend/coverage/index.html`.

---

## 6. Comment lancer les tests

```bash
# Backend (depuis backend/)
php artisan test                       # 414 tests
php artisan test --filter=LoginTest    # une classe
composer test:coverage                 # couverture + gate 80 %
composer test:coverage-html            # rapport HTML

# Frontend (depuis frontend/)
npm run test                           # 557 tests
npx vitest run src/__tests__/pages/PrestationListPage.test.ts   # un fichier
npm run test:coverage                  # couverture + gate 85 %
```

> Prérequis couverture backend : driver Xdebug en mode `coverage` — géré automatiquement par le
> script Composer (`@putenv XDEBUG_MODE=coverage`).

---

## 7. Points de défense (soutenance)

Ce qui compte n'est pas le compteur mais la **maîtrise de la démarche**. Trois idées à savoir défendre :

1. **La pyramide** : peu de tests unitaires ciblés sur la logique métier, beaucoup de tests
   d'intégration (Feature API) qui donnent confiance sur le comportement réel, et des tests de
   composants pour l'IHM. Chaque niveau a un rôle.
2. **L'isolation** : BDD en mémoire recréée par test côté back ; simulation de la frontière réseau
   côté front. Les tests sont déterministes et n'ont aucune dépendance externe.
3. **La couverture pilotée par un seuil** : la couverture n'est pas décorative, elle est **vérifiée
   automatiquement** (gate 80 % back / 85 % front) — c'est ce qui la rend utile en CI.

Les règles métier sensibles du domaine (TVA historisée, invariant des tranches, numérotation par
exercice, isolation du portail, brute-force login) sont couvertes par des tests nommés
explicitement — ce sont les meilleurs exemples à présenter.
