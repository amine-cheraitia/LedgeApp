<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useCountUp } from '@/composables/useCountUp'
import { formatDA, formatDACompact, formatDAKpi } from '@/utils/currency'
import type { SecretaireStats } from '@/api/modules/stats'

const props = defineProps<{
  stats: SecretaireStats
}>()

// ── Helpers ──────────────────────────────────────────────────────────────
function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function alerteSeverity(type: string): 'error' | 'warn' | 'info' | 'success' {
  return type === 'danger' ? 'error' : type === 'warn' ? 'warn' : 'info'
}

function statutPaiementLabel(statut: string): string {
  const map: Record<string, string> = { en_attente: 'En attente', partiel: 'Partiel', solde: 'Soldé' }
  return map[statut] ?? statut
}

function statutPaiementSeverity(statut: string): 'warn' | 'info' | 'success' | 'secondary' {
  const map: Record<string, 'warn' | 'info' | 'success'> = { en_attente: 'warn', partiel: 'info', solde: 'success' }
  return map[statut] ?? 'secondary'
}

function urgentRowClass(data: { en_retard: boolean }): string {
  return data.en_retard ? 'reco-row--retard' : ''
}

const todayLabel = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

// ── Count-up (useCountUp respecte deja prefers-reduced-motion) ──────────────
const totalRelancesDues = computed(() =>
  props.stats.relances_dues.niveau_1 + props.stats.relances_dues.niveau_2 + props.stats.relances_dues.niveau_3,
)

const animCreances = useCountUp(computed(() => Math.round(props.stats.creances.total_impaye)))
const animRetard = useCountUp(computed(() => props.stats.creances.en_retard))
const animRelances = useCountUp(totalRelancesDues)
const animEncaissements = useCountUp(computed(() => Math.round(props.stats.encaissements_mois.montant)))

// ── Chart : aging (barres horizontales) ────────────────────────────────────
const agingTotal = computed(() =>
  props.stats.aging.retard_15_30 + props.stats.aging.retard_30_60 + props.stats.aging.retard_60_plus,
)

const agingBuckets = computed(() => {
  const a = props.stats.aging
  const items = [
    { label: '15 – 29 jours', value: a.retard_15_30, tone: 'warn' as const },
    { label: '30 – 59 jours', value: a.retard_30_60, tone: 'strong' as const },
    { label: '60 jours et +', value: a.retard_60_plus, tone: 'danger' as const },
  ]
  const max = Math.max(...items.map((i) => i.value), 1)
  const total = agingTotal.value
  return items.map((i) => ({
    ...i,
    width: Math.round((i.value / max) * 100),
    pct: total ? Math.round((i.value / total) * 100) : 0,
  }))
})

// ── Chart : donut relances (SVG) ───────────────────────────────────────────
const RING_R = 38
const RING_C = 2 * Math.PI * RING_R
const DONUT_GAP = 4

const relancesNiveaux = computed(() => {
  const r = props.stats.relances_dues
  return [
    { label: 'Niveau 1', value: r.niveau_1, color: '#2563eb' },
    { label: 'Niveau 2', value: r.niveau_2, color: '#B45309' },
    { label: 'Niveau 3', value: r.niveau_3, color: '#B91C1C' },
  ]
})

const relancesSegments = computed(() => {
  const total = totalRelancesDues.value
  if (total === 0) return []
  const active = relancesNiveaux.value.filter((p) => p.value > 0)
  let cumulative = 0
  return active.map((p) => {
    const rawArc = (p.value / total) * RING_C
    const gap = active.length > 1 ? DONUT_GAP : 0
    const arc = Math.max(rawArc - gap, 8)
    const seg = { color: p.color, dasharray: `${arc} ${RING_C}`, dashoffset: RING_C - cumulative }
    cumulative += rawArc
    return seg
  })
})

// ── Ranking : top débiteurs (barres) ───────────────────────────────────────
const maxDebiteur = computed(() => Math.max(...props.stats.top_debiteurs.map((d) => d.montant_impaye), 1))

const agingAriaLabel = computed(() =>
  `Aging des créances : ${agingBuckets.value.map((b) => `${b.label}, ${formatDA(b.value)}`).join(' ; ')}`,
)
const relancesAriaLabel = computed(() =>
  `Relances dues : ${relancesNiveaux.value.map((n) => `${n.label}, ${n.value}`).join(' ; ')}`,
)
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <!-- ── Alertes ── -->
    <div v-if="stats.alertes.length" class="col-span-12" role="alert" aria-live="polite">
      <Message
        v-for="(alerte, i) in stats.alertes"
        :key="i"
        :severity="alerteSeverity(alerte.type)"
        :closable="false"
        class="mb-2"
      >
        {{ alerte.message }}
      </Message>
    </div>

    <!-- ── Row 1 : Bandeau ── -->
    <div class="col-span-12">
      <div class="reco-hero">
        <div class="reco-hero__left">
          <p class="reco-today">{{ todayLabel }}</p>
          <h2 class="reco-greeting">Facturation &amp; recouvrement</h2>
          <div class="reco-summary" aria-label="Synthèse">
            <span class="reco-chip">
              <i class="pi pi-exclamation-circle" aria-hidden="true"></i>
              {{ formatDA(stats.creances.total_impaye) }} à recouvrer
            </span>
            <span class="reco-chip reco-chip--success">
              <i class="pi pi-wallet" aria-hidden="true"></i>
              {{ formatDA(stats.encaissements_mois.montant) }} encaissé ce mois
            </span>
            <span class="reco-chip" :class="totalRelancesDues > 0 ? 'reco-chip--warn' : 'reco-chip--success'">
              <i class="pi pi-bell" aria-hidden="true"></i>
              {{ totalRelancesDues }} relance{{ totalRelancesDues !== 1 ? 's' : '' }} due{{ totalRelancesDues !== 1 ? 's' : '' }}
            </span>
          </div>
        </div>
        <div
          v-if="stats.aging.retard_60_plus > 0"
          class="reco-alert"
          role="alert"
          aria-live="polite"
        >
          <i class="pi pi-exclamation-triangle reco-alert__icon" aria-hidden="true"></i>
          <div>
            <p class="reco-alert__title">Créances critiques</p>
            <p class="reco-alert__msg">{{ formatDA(stats.aging.retard_60_plus) }} à plus de 60 jours</p>
          </div>
        </div>
        <div v-else class="reco-alert reco-alert--ok" role="status">
          <i class="pi pi-check-circle reco-alert__icon" aria-hidden="true"></i>
          <div>
            <p class="reco-alert__title">Sous contrôle</p>
            <p class="reco-alert__msg">Aucune créance &gt; 60 jours</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Row 2 : Worklist « À faire » ── -->
    <div class="col-span-12">
      <section class="card reco-todo-card" aria-labelledby="todo-title">
        <div class="panel-header">
          <h3 id="todo-title" class="panel-title">À faire</h3>
          <span v-if="stats.actions.length" class="reco-pill">{{ stats.actions.length }} point{{ stats.actions.length !== 1 ? 's' : '' }} d'attention</span>
        </div>
        <div v-if="stats.actions.length === 0" class="reco-todo-empty" role="status">
          <i class="pi pi-check-circle" aria-hidden="true"></i>
          <span>Rien d'urgent — tout est à jour.</span>
        </div>
        <ul v-else class="reco-todo-list" role="list">
          <li v-for="a in stats.actions" :key="a.key">
            <RouterLink :to="a.route" class="reco-todo" :class="`reco-todo--${a.severity}`">
              <span class="reco-todo__icon" aria-hidden="true"><i :class="['pi', a.icon]"></i></span>
              <span class="reco-todo__label">{{ a.label }}</span>
              <span class="reco-todo__count" :aria-label="`${a.count} élément${a.count !== 1 ? 's' : ''}`">{{ a.count }}</span>
              <i class="pi pi-chevron-right reco-todo__arrow" aria-hidden="true"></i>
            </RouterLink>
          </li>
        </ul>
      </section>
    </div>

    <!-- ── Row 3 : KPI ── -->
    <div class="col-span-12 sm:col-span-6 xl:col-span-4">
      <article class="card reco-kpi reco-kpi--danger" aria-labelledby="kpi-impaye">
        <div class="reco-kpi__top">
          <p id="kpi-impaye" class="reco-kpi__label">Créances totales</p>
          <span class="reco-kpi__icon" aria-hidden="true"><i class="pi pi-exclamation-circle"></i></span>
        </div>
        <p
          class="reco-kpi__value"
          :title="formatDA(stats.creances.total_impaye)"
          :aria-label="`Créances totales : ${formatDA(stats.creances.total_impaye)}`"
        >
          {{ formatDAKpi(animCreances) }}
        </p>
        <p class="reco-kpi__sub">
          {{ stats.creances.clients_debiteurs }} débiteur{{ stats.creances.clients_debiteurs !== 1 ? 's' : '' }} · {{ animRetard }} en retard
        </p>
      </article>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-4">
      <article class="card reco-kpi reco-kpi--warn" aria-labelledby="kpi-relances">
        <div class="reco-kpi__top">
          <p id="kpi-relances" class="reco-kpi__label">Relances dues</p>
          <span class="reco-kpi__icon" aria-hidden="true"><i class="pi pi-bell"></i></span>
        </div>
        <p class="reco-kpi__value">{{ animRelances }}</p>
        <p class="reco-kpi__sub">
          N1 : {{ stats.relances_dues.niveau_1 }} · N2 : {{ stats.relances_dues.niveau_2 }} · N3 : {{ stats.relances_dues.niveau_3 }}
        </p>
      </article>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-4">
      <article class="card reco-kpi reco-kpi--success" aria-labelledby="kpi-encaisse">
        <div class="reco-kpi__top">
          <p id="kpi-encaisse" class="reco-kpi__label">Encaissé ce mois</p>
          <span class="reco-kpi__icon" aria-hidden="true"><i class="pi pi-wallet"></i></span>
        </div>
        <p
          class="reco-kpi__value"
          :title="formatDA(stats.encaissements_mois.montant)"
          :aria-label="`Encaissé ce mois : ${formatDA(stats.encaissements_mois.montant)}`"
        >
          {{ formatDAKpi(animEncaissements) }}
        </p>
        <p class="reco-kpi__sub">
          {{ stats.encaissements_mois.count }} paiement{{ stats.encaissements_mois.count !== 1 ? 's' : '' }}
        </p>
      </article>
    </div>

    <!-- ── Row 4 : Graphiques ── -->
    <div class="col-span-12 lg:col-span-6">
      <section class="card h-full" aria-labelledby="aging-title">
        <div class="panel-header">
          <h3 id="aging-title" class="panel-title">Aging des créances</h3>
          <span class="reco-pill">{{ formatDACompact(agingTotal) }}</span>
        </div>
        <div v-if="agingTotal === 0" class="reco-empty" role="status">
          <i class="pi pi-check-circle text-3xl" aria-hidden="true"></i>
          <p>Aucune créance en retard.</p>
        </div>
        <ul v-else class="reco-bars" role="img" :aria-label="agingAriaLabel">
          <li v-for="bucket in agingBuckets" :key="bucket.label" class="reco-bar">
            <div class="reco-bar__head">
              <span class="reco-bar__label">{{ bucket.label }}</span>
              <span class="reco-bar__value">{{ formatDA(bucket.value) }}</span>
            </div>
            <div class="reco-bar__track">
              <div class="reco-bar__fill" :class="`reco-bar__fill--${bucket.tone}`" :style="{ width: bucket.width + '%' }"></div>
            </div>
            <span class="reco-bar__pct">{{ bucket.pct }}% du total</span>
          </li>
        </ul>
      </section>
    </div>

    <div class="col-span-12 lg:col-span-6">
      <section class="card h-full" aria-labelledby="relances-title">
        <div class="panel-header">
          <h3 id="relances-title" class="panel-title">Relances dues</h3>
          <span class="reco-pill">{{ totalRelancesDues }} total</span>
        </div>
        <div v-if="totalRelancesDues === 0" class="reco-empty" role="status">
          <i class="pi pi-bell-slash text-3xl" aria-hidden="true"></i>
          <p>Aucune relance à envoyer.</p>
        </div>
        <div v-else class="reco-donut-wrap" role="img" :aria-label="relancesAriaLabel">
          <div class="reco-donut">
            <svg viewBox="0 0 88 88" class="reco-donut__svg" aria-hidden="true">
              <circle class="reco-donut__track" cx="44" cy="44" r="38" />
              <circle
                v-for="(seg, i) in relancesSegments"
                :key="i"
                class="reco-donut__seg"
                cx="44" cy="44" r="38"
                :stroke="seg.color"
                :style="{ strokeDasharray: seg.dasharray, strokeDashoffset: seg.dashoffset }"
              />
            </svg>
            <div class="reco-donut__center" aria-hidden="true">
              <span class="reco-donut__total">{{ totalRelancesDues }}</span>
              <span class="reco-donut__sub">relances</span>
            </div>
          </div>
          <ul class="reco-legend" role="list">
            <li v-for="n in relancesNiveaux" :key="n.label" class="reco-legend__item" :class="{ 'reco-legend__item--zero': n.value === 0 }">
              <span class="reco-legend__dot" :style="{ background: n.color }" aria-hidden="true"></span>
              <span class="reco-legend__label">{{ n.label }}</span>
              <span class="reco-legend__val">{{ n.value }}</span>
            </li>
          </ul>
        </div>
      </section>
    </div>

    <!-- ── Row 5 : Top débiteurs ── -->
    <div class="col-span-12 lg:col-span-5">
      <section class="card h-full" aria-labelledby="top-debiteurs-title">
        <div class="panel-header">
          <h3 id="top-debiteurs-title" class="panel-title">Top 5 clients débiteurs</h3>
        </div>
        <div v-if="stats.top_debiteurs.length === 0" class="reco-empty" role="status">
          <i class="pi pi-users text-3xl" aria-hidden="true"></i>
          <p>Aucun client débiteur.</p>
        </div>
        <ol v-else class="reco-rank" aria-label="Classement des clients débiteurs">
          <li v-for="(d, i) in stats.top_debiteurs" :key="d.entreprise_id" class="reco-rank__item">
            <span class="reco-rank__pos" aria-hidden="true">{{ i + 1 }}</span>
            <div class="reco-rank__body">
              <RouterLink :to="`/entreprises/${d.entreprise_id}`" class="reco-rank__name" :aria-label="`Fiche de ${d.raison_sociale}`">
                {{ d.raison_sociale }}
              </RouterLink>
              <div class="reco-rank__track">
                <div class="reco-rank__fill" :style="{ width: Math.round((d.montant_impaye / maxDebiteur) * 100) + '%' }"></div>
              </div>
            </div>
            <span class="reco-rank__amount">{{ formatDA(d.montant_impaye) }}</span>
          </li>
        </ol>
      </section>
    </div>

    <!-- ── Row 5 : Créances urgentes ── -->
    <div class="col-span-12 lg:col-span-7">
      <section class="card h-full" aria-labelledby="recentes-creances-title">
        <div class="panel-header reco-panel-header--wrap">
          <h3 id="recentes-creances-title" class="panel-title">Créances urgentes</h3>
          <div class="reco-actions">
            <RouterLink to="/creances">
              <Button label="Voir les créances" icon="pi pi-exclamation-circle" severity="secondary" outlined size="small" />
            </RouterLink>
          </div>
        </div>
        <DataTable
          :value="stats.recentes_creances"
          responsive-layout="scroll"
          :rows="5"
          :row-class="urgentRowClass"
          aria-label="Créances urgentes"
        >
          <Column field="numero" header="N° facture">
            <template #body="{ data }"><span class="reco-num">{{ data.numero }}</span></template>
          </Column>
          <Column field="entreprise" header="Entreprise" />
          <Column header="Restant dû">
            <template #body="{ data }">
              <span class="font-semibold">{{ formatDA(data.montant_restant) }}</span>
            </template>
          </Column>
          <Column header="Échéance">
            <template #body="{ data }">
              <span :class="{ 'reco-echeance--retard': data.en_retard }">{{ formatDate(data.date_echeance) }}</span>
            </template>
          </Column>
          <Column header="Statut">
            <template #body="{ data }">
              <Tag :value="statutPaiementLabel(data.statut_paiement)" :severity="statutPaiementSeverity(data.statut_paiement)" />
            </template>
          </Column>
        </DataTable>
        <p v-if="stats.recentes_creances.length === 0" class="reco-empty" role="status">
          <i class="pi pi-check-circle text-3xl" aria-hidden="true"></i>
          Aucune créance en cours.
        </p>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* ════════════ Bandeau (éditorial Ledger) ════════════ */
.reco-hero {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding: 1.5rem 1.75rem;
  border-radius: var(--ledge-radius-md, 6px);
  background: var(--p-surface-0);
  border: 1px solid var(--p-surface-200);
  border-top: 3px solid var(--ledge-accent);
  color: var(--p-text-color);
}
.app-dark .reco-hero {
  background: var(--p-surface-900);
  border-color: var(--p-surface-700);
}
.reco-hero__left { flex: 1; min-width: 0; }
.reco-today {
  font-family: var(--ledge-ff-mono);
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--p-text-muted-color);
  margin: 0 0 0.4rem;
}
.reco-today::before {
  content: '§ ';
  color: var(--ledge-accent);
  font-family: var(--ledge-ff-display);
  font-style: italic;
}
.reco-greeting {
  font-family: var(--ledge-ff-display);
  font-weight: 400;
  font-size: 1.85rem;
  line-height: 1.1;
  letter-spacing: -0.01em;
  margin: 0 0 1rem;
  color: var(--p-text-color);
}
.reco-summary {
  display: flex;
  flex-wrap: wrap;
  border-top: 1px solid var(--p-surface-200);
  padding-top: 0.85rem;
}
.app-dark .reco-summary { border-top-color: var(--p-surface-700); }
.reco-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.15rem 1.25rem 0.15rem 0;
  margin-right: 1.25rem;
  border-right: 1px dotted var(--p-surface-300);
  font-family: var(--ledge-ff-mono);
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--p-text-color);
}
.reco-chip:last-child { border-right: none; margin-right: 0; padding-right: 0; }
.reco-chip i { color: var(--p-text-muted-color); font-size: 0.85rem; }
.app-dark .reco-chip { border-right-color: var(--p-surface-700); }
.reco-chip--success, .reco-chip--success i { color: var(--ledge-success); }
.reco-chip--warn, .reco-chip--warn i { color: var(--ledge-warning); }
.app-dark .reco-chip--success, .app-dark .reco-chip--success i { color: #34d399; }
.app-dark .reco-chip--warn, .app-dark .reco-chip--warn i { color: #fbbf24; }

.reco-alert {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1.1rem;
  border-radius: var(--ledge-radius-sm, 2px);
  background: color-mix(in srgb, var(--ledge-danger), transparent 92%);
  border: 1px solid color-mix(in srgb, var(--ledge-danger), transparent 75%);
  border-left: 3px solid var(--ledge-danger);
  color: var(--ledge-danger);
  flex-shrink: 0;
  align-self: center;
}
.reco-alert--ok {
  background: color-mix(in srgb, var(--ledge-success), transparent 92%);
  border-color: color-mix(in srgb, var(--ledge-success), transparent 75%);
  border-left-color: var(--ledge-success);
  color: var(--ledge-success);
}
.app-dark .reco-alert { color: #f87171; }
.app-dark .reco-alert--ok { color: #34d399; }
.reco-alert__icon { font-size: 1.4rem; flex-shrink: 0; }
.reco-alert__title {
  font-family: var(--ledge-ff-mono);
  font-weight: 600;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin: 0;
}
.reco-alert__msg { font-size: 0.85rem; font-weight: 500; margin: 0.2rem 0 0; color: var(--p-text-color); }

/* ════════════ Worklist « À faire » ════════════ */
.reco-todo-card { border-left: 3px solid var(--ledge-accent); }
.reco-todo-empty {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0;
  color: var(--ledge-success);
  font-weight: 500;
}
.app-dark .reco-todo-empty { color: #34d399; }
.reco-todo-empty i { font-size: 1.25rem; }
.reco-todo-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.6rem;
}
.reco-todo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0.9rem;
  border-radius: var(--ledge-radius-sm, 4px);
  border: 1px solid var(--p-surface-200);
  border-left: 3px solid var(--p-surface-300);
  background: var(--p-surface-0);
  color: var(--p-text-color);
  text-decoration: none;
  transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
  cursor: pointer;
}
.app-dark .reco-todo { background: var(--p-surface-800); border-color: var(--p-surface-700); }
.reco-todo:hover { transform: translateX(2px); background: var(--p-surface-50); }
.app-dark .reco-todo:hover { background: var(--p-surface-700); }
.reco-todo:focus-visible { outline: 2px solid var(--ledge-accent); outline-offset: 2px; }
.reco-todo--danger { border-left-color: var(--ledge-danger); }
.reco-todo--warn   { border-left-color: var(--ledge-warning); }
.reco-todo--info   { border-left-color: #2563eb; }
.reco-todo__icon {
  width: 2rem; height: 2rem;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  font-size: 0.95rem;
  background: var(--p-surface-100);
  color: var(--p-text-muted-color);
}
.app-dark .reco-todo__icon { background: var(--p-surface-700); }
.reco-todo--danger .reco-todo__icon { background: color-mix(in srgb, var(--ledge-danger), transparent 88%); color: var(--ledge-danger); }
.reco-todo--warn .reco-todo__icon   { background: color-mix(in srgb, var(--ledge-warning), transparent 88%); color: var(--ledge-warning); }
.reco-todo--info .reco-todo__icon   { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.reco-todo__label { flex: 1; font-size: 0.85rem; font-weight: 500; min-width: 0; }
.reco-todo__count {
  flex-shrink: 0;
  font-family: var(--ledge-ff-mono);
  font-weight: 700;
  font-size: 0.9rem;
  min-width: 1.6rem;
  text-align: center;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background: var(--p-surface-100);
  color: var(--p-text-color);
}
.app-dark .reco-todo__count { background: var(--p-surface-700); }
.reco-todo--danger .reco-todo__count { background: color-mix(in srgb, var(--ledge-danger), transparent 85%); color: var(--ledge-danger); }
.reco-todo--warn .reco-todo__count   { background: color-mix(in srgb, var(--ledge-warning), transparent 85%); color: var(--ledge-warning); }
.reco-todo__arrow { color: var(--p-text-muted-color); font-size: 0.75rem; flex-shrink: 0; }

/* ════════════ KPI cards ════════════ */
.reco-kpi {
  position: relative;
  overflow: hidden;
  height: 100%;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.reco-kpi::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.reco-kpi:hover { box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12); transform: translateY(-2px); }
.reco-kpi--danger::before  { background: var(--ledge-danger); }
.reco-kpi--warn::before    { background: var(--ledge-warning); }
.reco-kpi--accent::before  { background: var(--ledge-accent); }
.reco-kpi--info::before    { background: #2563eb; }
.reco-kpi--success::before { background: var(--ledge-success); }
.reco-kpi__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.reco-kpi__label { font-size: 0.875rem; font-weight: 500; color: var(--p-text-muted-color); margin: 0; }
.reco-kpi__icon {
  width: 2.5rem; height: 2.5rem;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.125rem; flex-shrink: 0;
}
.reco-kpi--danger .reco-kpi__icon  { background: color-mix(in srgb, var(--ledge-danger), transparent 88%); color: var(--ledge-danger); }
.reco-kpi--warn .reco-kpi__icon    { background: color-mix(in srgb, var(--ledge-warning), transparent 88%); color: var(--ledge-warning); }
.reco-kpi--accent .reco-kpi__icon  { background: color-mix(in srgb, var(--ledge-accent), transparent 88%); color: var(--ledge-accent); }
.reco-kpi--info .reco-kpi__icon    { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
.reco-kpi--success .reco-kpi__icon { background: color-mix(in srgb, var(--ledge-success), transparent 88%); color: var(--ledge-success); }
.reco-kpi__value {
  font-family: var(--ledge-ff-mono);
  font-size: 1.7rem;
  font-weight: 700;
  color: var(--p-text-color);
  line-height: 1.05;
  margin: 0;
  font-variant-numeric: tabular-nums;
}
.reco-kpi__sub { font-size: 0.8125rem; color: var(--p-text-muted-color); margin: 0.6rem 0 0; }

/* ════════════ Panel header partagé ════════════ */
.panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.panel-title { font-size: 1rem; font-weight: 700; color: var(--p-text-color); margin: 0; }
.reco-panel-header--wrap { flex-wrap: wrap; gap: 0.75rem; }
.reco-pill {
  display: inline-flex;
  align-items: center;
  font-family: var(--ledge-ff-mono);
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.2rem 0.625rem;
  border-radius: 999px;
  background: var(--p-surface-border);
  color: var(--p-text-muted-color);
}
.reco-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

/* ════════════ Empty state ════════════ */
.reco-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: var(--p-text-muted-color);
  text-align: center;
}

/* ════════════ Chart : aging ════════════ */
.reco-bars { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1.1rem; }
.reco-bar__head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem; }
.reco-bar__label { font-size: 0.8125rem; color: var(--p-text-color); font-weight: 500; }
.reco-bar__value { font-size: 0.8125rem; font-weight: 700; font-family: var(--ledge-ff-mono); color: var(--p-text-color); }
.reco-bar__track { height: 10px; background: var(--p-surface-border); border-radius: 999px; overflow: hidden; }
.reco-bar__fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.reco-bar__fill--warn   { background: var(--ledge-warning); }
.reco-bar__fill--strong { background: var(--ledge-accent); }
.reco-bar__fill--danger { background: var(--ledge-danger); }
.reco-bar__pct { display: block; font-size: 0.7rem; color: var(--p-text-muted-color); margin-top: 0.25rem; }

/* ════════════ Chart : donut ════════════ */
.reco-donut-wrap { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; padding-top: 0.5rem; }
.reco-donut { position: relative; width: 110px; height: 110px; flex-shrink: 0; }
.reco-donut__svg { width: 110px; height: 110px; transform: rotate(-90deg); }
.reco-donut__track { fill: none; stroke: var(--p-surface-border); stroke-width: 12; }
.reco-donut__seg { fill: none; stroke-width: 12; stroke-linecap: butt; transition: stroke-dashoffset 0.5s ease; }
.reco-donut__center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.reco-donut__total { font-size: 1.6rem; font-weight: 700; color: var(--p-text-color); line-height: 1; font-variant-numeric: tabular-nums; }
.reco-donut__sub { font-size: 0.65rem; color: var(--p-text-muted-color); margin-top: 2px; }
.reco-legend { list-style: none; padding: 0; margin: 0; flex: 1; display: flex; flex-direction: column; gap: 0.6rem; min-width: 120px; }
.reco-legend__item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; }
.reco-legend__item--zero { opacity: 0.45; }
.reco-legend__dot { width: 0.7rem; height: 0.7rem; border-radius: 3px; flex-shrink: 0; }
.reco-legend__label { color: var(--p-text-color); }
.reco-legend__val { margin-left: auto; font-weight: 700; font-family: var(--ledge-ff-mono); color: var(--p-text-color); }

/* ════════════ Ranking : top débiteurs ════════════ */
.reco-rank { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem; }
.reco-rank__item { display: flex; align-items: center; gap: 0.85rem; }
.reco-rank__pos {
  flex-shrink: 0;
  width: 1.6rem; height: 1.6rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 999px;
  background: var(--p-surface-border);
  color: var(--p-text-muted-color);
  font-family: var(--ledge-ff-mono);
  font-size: 0.75rem;
  font-weight: 700;
}
.reco-rank__item:first-child .reco-rank__pos { background: color-mix(in srgb, var(--ledge-danger), transparent 85%); color: var(--ledge-danger); }
.reco-rank__body { flex: 1; min-width: 0; }
.reco-rank__name {
  display: inline-block;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--p-primary-color);
  text-decoration: none;
  margin-bottom: 0.3rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.reco-rank__name:hover { text-decoration: underline; }
.reco-rank__name:focus-visible { outline: 2px solid var(--p-primary-color); outline-offset: 2px; border-radius: 2px; }
.reco-rank__track { height: 6px; background: var(--p-surface-border); border-radius: 999px; overflow: hidden; }
.reco-rank__fill { height: 100%; background: var(--ledge-danger); border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }
.reco-rank__amount { flex-shrink: 0; font-size: 0.8125rem; font-weight: 700; font-family: var(--ledge-ff-mono); color: var(--ledge-danger); }
.app-dark .reco-rank__fill, .app-dark .reco-rank__amount { background: initial; }
.app-dark .reco-rank__amount { color: #f87171; }
.app-dark .reco-rank__fill { background: #f87171; }

/* ════════════ Table créances urgentes ════════════ */
.reco-num { font-family: var(--ledge-ff-mono); font-weight: 600; }
.reco-echeance--retard { color: var(--ledge-danger); font-weight: 600; }
.app-dark .reco-echeance--retard { color: #f87171; }
:deep(.reco-row--retard) { background: color-mix(in srgb, var(--ledge-danger), transparent 95%); }

/* ════════════ Responsive ════════════ */
@media (max-width: 640px) {
  .reco-greeting { font-size: 1.5rem; }
  .reco-todo-list { grid-template-columns: 1fr; }
}

/* ════════════ Reduced motion ════════════ */
@media (prefers-reduced-motion: reduce) {
  .reco-kpi,
  .reco-todo,
  .reco-bar__fill,
  .reco-donut__seg,
  .reco-rank__fill { transition: none !important; }
}
</style>
