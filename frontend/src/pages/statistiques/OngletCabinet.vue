<script setup lang="ts">
// Onglet Cabinet — analytique du cabinet (top entreprises, missions, creances).
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Chart from 'primevue/chart'
import { useLayout } from '@/layout/composables/layout'
import { statsApi } from '@/api/modules/stats'
import type { CabinetStats } from '@/api/modules/stats'
import { formatDA, formatDACompact, formatDAKpi } from '@/utils/currency'

const props = defineProps<{ exerciceId: number | null }>()

const { isDarkTheme } = useLayout()

// ── Chargement ───────────────────────────────────────────────────────────
const loading = ref(true)
const error = ref(false)
const stats = ref<CabinetStats | null>(null)

// Message d'etat vide partage par les 4 blocs (aucune donnee sur l'exercice filtre).
const AUCUNE_DONNEE = 'Aucune donnée sur cet exercice.'

async function fetchStats(): Promise<void> {
  loading.value = true
  error.value = false
  try {
    const res = await statsApi.getCabinetStats(props.exerciceId)
    stats.value = res.data
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)
watch(() => props.exerciceId, fetchStats)

// ── Theme (couleurs reactives au dark mode) — meme pattern que DashboardPage ─
const prefersReduced = typeof window !== 'undefined'
  && typeof window.matchMedia === 'function'
  && window.matchMedia('(prefers-reduced-motion: reduce)').matches

const themeColors = ref({ text: '', muted: '', border: '' })

function refreshThemeColors(): void {
  const s = getComputedStyle(document.documentElement)
  themeColors.value = {
    text: s.getPropertyValue('--p-text-color').trim(),
    muted: s.getPropertyValue('--p-text-muted-color').trim(),
    border: s.getPropertyValue('--p-surface-border').trim(),
  }
}

onMounted(refreshThemeColors)
watch(isDarkTheme, async () => {
  await nextTick()
  refreshThemeColors()
})

// Palette categorielle (esprit de la palette missions du dashboard, tons distincts)
const PALETTE = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b', '#ec4899']
function paletteColor(i: number): string {
  return PALETTE[i % PALETTE.length]
}

// ── Bloc a : Top entreprises contributrices (barres horizontales) ──────────
const topEntreprises = computed(() => stats.value?.top_entreprises ?? [])
const topEntreprisesVide = computed(() => topEntreprises.value.length === 0)

const topEntreprisesData = computed(() => ({
  labels: topEntreprises.value.map((e) => e.raison_sociale),
  datasets: [{
    label: 'CA HT net',
    data: topEntreprises.value.map((e) => e.ca_ht_net),
    backgroundColor: isDarkTheme.value ? '#94a3b8' : '#64748b',
    borderRadius: 6,
    maxBarThickness: 28,
  }],
}))

const topEntreprisesOptions = computed(() => ({
  indexAxis: 'y' as const,
  responsive: true,
  maintainAspectRatio: false,
  animation: prefersReduced ? false : undefined,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: { label: (ctx: { parsed: { x: number } }) => formatDA(ctx.parsed.x) },
    },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: { color: themeColors.value.muted, callback: (v: string | number) => formatDACompact(Number(v)) },
      grid: { color: themeColors.value.border },
    },
    y: {
      ticks: { color: themeColors.value.muted },
      grid: { display: false },
    },
  },
}))

const topEntreprisesAria = computed(() => {
  if (topEntreprisesVide.value) return 'Top entreprises contributrices : aucune donnée'
  return 'Top entreprises contributrices, chiffre d\'affaires HT net : '
    + topEntreprises.value.map((e) => `${e.raison_sociale} ${formatDA(e.ca_ht_net)}`).join(', ')
})

// ── Bloc b : Répartition des missions par prestation (doughnut) ────────────
const missionsPrestation = computed(() => stats.value?.missions_par_prestation ?? [])
const missionsPrestationVide = computed(() => missionsPrestation.value.length === 0)

const missionsPrestationData = computed(() => ({
  labels: missionsPrestation.value.map((p) => p.designation),
  datasets: [{
    data: missionsPrestation.value.map((p) => p.total),
    backgroundColor: missionsPrestation.value.map((_, i) => paletteColor(i)),
    borderWidth: 0,
  }],
}))

const missionsPrestationOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: prefersReduced ? false : undefined,
  cutout: '60%',
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: { color: themeColors.value.text, usePointStyle: true, padding: 16 },
    },
    tooltip: {
      callbacks: { label: (ctx: { label: string; parsed: number }) => `${ctx.label} : ${ctx.parsed}` },
    },
  },
}))

const missionsPrestationAria = computed(() => {
  if (missionsPrestationVide.value) return 'Répartition des missions par prestation : aucune donnée'
  return 'Répartition des missions par prestation : '
    + missionsPrestation.value.map((p) => `${p.designation} ${p.total}`).join(', ')
})

// ── Bloc c : Missions par état (barres verticales) ──────────────────────────
const missionsEtatLabels = ['En cours', 'Terminée', 'Suspendue', 'Annulée']

const missionsEtatVide = computed(() => {
  const m = stats.value?.missions_par_etat
  if (!m) return true
  return m.en_cours + m.terminee + m.suspendue + m.annulee === 0
})

const missionsEtatData = computed(() => {
  const m = stats.value?.missions_par_etat
  return {
    labels: missionsEtatLabels,
    datasets: [{
      label: 'Missions',
      data: [m?.en_cours ?? 0, m?.terminee ?? 0, m?.suspendue ?? 0, m?.annulee ?? 0],
      backgroundColor: ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444'],
      borderRadius: 6,
      maxBarThickness: 48,
    }],
  }
})

const missionsEtatOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: prefersReduced ? false : undefined,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: { label: (ctx: { parsed: { y: number } }) => `${ctx.parsed.y} mission${ctx.parsed.y !== 1 ? 's' : ''}` },
    },
  },
  scales: {
    x: { ticks: { color: themeColors.value.muted }, grid: { display: false } },
    y: {
      beginAtZero: true,
      ticks: { color: themeColors.value.muted, precision: 0 },
      grid: { color: themeColors.value.border },
    },
  },
}))

const missionsEtatAria = computed(() => {
  const m = stats.value?.missions_par_etat
  if (!m || missionsEtatVide.value) return 'Missions par état : aucune donnée'
  return `Missions par état : En cours ${m.en_cours}, Terminée ${m.terminee}, Suspendue ${m.suspendue}, Annulée ${m.annulee}`
})

// ── Bloc d : Créances ────────────────────────────────────────────────────
const totalImpaye = computed(() => stats.value?.creances.total_impaye ?? 0)

const agingBuckets = computed(() => {
  const a = stats.value?.creances.aging
  const items = [
    { key: 'retard_15_30', label: '15 – 30 jours', value: a?.retard_15_30 ?? 0, tone: 'warn' as const },
    { key: 'retard_30_60', label: '30 – 60 jours', value: a?.retard_30_60 ?? 0, tone: 'strong' as const },
    { key: 'retard_60_plus', label: '60 jours et +', value: a?.retard_60_plus ?? 0, tone: 'danger' as const },
  ]
  const max = Math.max(...items.map((i) => i.value), 1)
  return items.map((i) => ({ ...i, width: Math.round((i.value / max) * 100) }))
})

const agingVide = computed(() => agingBuckets.value.every((b) => b.value === 0))

const agingAria = computed(() => {
  if (agingVide.value) return 'Créances par ancienneté : aucune donnée'
  return 'Créances par ancienneté : ' + agingBuckets.value.map((b) => `${b.label} ${formatDA(b.value)}`).join(', ')
})

const topDebiteurs = computed(() => stats.value?.creances.top_debiteurs ?? [])
const topDebiteursVide = computed(() => topDebiteurs.value.length === 0)
const maxDebiteur = computed(() => Math.max(...topDebiteurs.value.map((d) => d.montant_impaye), 1))
</script>

<template>
  <div class="cab-grid">
    <!-- Chargement -->
    <div v-if="loading" class="cab-etat" role="status">
      <ProgressSpinner aria-label="Chargement des statistiques du cabinet" style="width: 40px; height: 40px" />
      <span>Chargement…</span>
    </div>

    <!-- Erreur -->
    <div v-else-if="error" class="cab-etat cab-etat--erreur" role="alert">
      <i class="pi pi-exclamation-triangle" aria-hidden="true"></i>
      <p>Impossible de charger les statistiques du cabinet.</p>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="fetchStats" />
    </div>

    <template v-else>
      <!-- Bloc a : Top entreprises contributrices -->
      <section class="card" aria-labelledby="cab-top-title">
        <div class="panel-header">
          <div>
            <h2 id="cab-top-title" class="panel-title">Top entreprises contributrices</h2>
            <p class="cab-subtitle">CA facturé HT, avoirs déduits</p>
          </div>
        </div>
        <div v-if="topEntreprisesVide" class="empty-state" role="status">
          <i class="pi pi-chart-bar text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
          <p>{{ AUCUNE_DONNEE }}</p>
        </div>
        <template v-else>
          <div class="chart-box">
            <Chart type="bar" :data="topEntreprisesData" :options="topEntreprisesOptions" role="img" :aria-label="topEntreprisesAria" />
          </div>
          <table class="sr-only">
            <caption>Top entreprises contributrices — chiffre d'affaires HT net</caption>
            <thead><tr><th>Entreprise</th><th>CA HT net</th></tr></thead>
            <tbody>
              <tr v-for="e in topEntreprises" :key="e.entreprise_id">
                <td>{{ e.raison_sociale }}</td>
                <td>{{ formatDA(e.ca_ht_net) }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </section>

      <!-- Bloc b : Répartition des missions par prestation -->
      <section class="card" aria-labelledby="cab-prestation-title">
        <div class="panel-header">
          <h2 id="cab-prestation-title" class="panel-title">Répartition des missions par prestation</h2>
        </div>
        <div v-if="missionsPrestationVide" class="empty-state" role="status">
          <i class="pi pi-chart-pie text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
          <p>{{ AUCUNE_DONNEE }}</p>
        </div>
        <template v-else>
          <div class="chart-box">
            <Chart type="doughnut" :data="missionsPrestationData" :options="missionsPrestationOptions" role="img" :aria-label="missionsPrestationAria" />
          </div>
          <table class="sr-only">
            <caption>Répartition des missions par prestation</caption>
            <thead><tr><th>Prestation</th><th>Nombre de missions</th></tr></thead>
            <tbody>
              <tr v-for="p in missionsPrestation" :key="p.prestation_id">
                <td>{{ p.designation }}</td>
                <td>{{ p.total }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </section>

      <!-- Bloc c : Missions par état -->
      <section class="card" aria-labelledby="cab-etat-title">
        <div class="panel-header">
          <h2 id="cab-etat-title" class="panel-title">Missions par état</h2>
        </div>
        <div v-if="missionsEtatVide" class="empty-state" role="status">
          <i class="pi pi-briefcase text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
          <p>{{ AUCUNE_DONNEE }}</p>
        </div>
        <template v-else>
          <div class="chart-box">
            <Chart type="bar" :data="missionsEtatData" :options="missionsEtatOptions" role="img" :aria-label="missionsEtatAria" />
          </div>
          <table class="sr-only">
            <caption>Missions par état</caption>
            <thead><tr><th>État</th><th>Nombre</th></tr></thead>
            <tbody>
              <tr><td>En cours</td><td>{{ stats?.missions_par_etat.en_cours ?? 0 }}</td></tr>
              <tr><td>Terminée</td><td>{{ stats?.missions_par_etat.terminee ?? 0 }}</td></tr>
              <tr><td>Suspendue</td><td>{{ stats?.missions_par_etat.suspendue ?? 0 }}</td></tr>
              <tr><td>Annulée</td><td>{{ stats?.missions_par_etat.annulee ?? 0 }}</td></tr>
            </tbody>
          </table>
        </template>
      </section>

      <!-- Bloc d : Créances -->
      <section class="card" aria-labelledby="cab-creances-title">
        <div class="panel-header">
          <h2 id="cab-creances-title" class="panel-title">Créances</h2>
        </div>

        <div class="cab-total">
          <p
            class="cab-total-value"
            :title="formatDA(totalImpaye)"
            :aria-label="`Total impayé : ${formatDA(totalImpaye)}`"
          >{{ formatDAKpi(totalImpaye) }}</p>
          <p class="cab-subtitle">Total impayé, avoirs déduits</p>
        </div>

        <h3 class="cab-subheading">Ancienneté des créances</h3>
        <div v-if="agingVide" class="empty-state" role="status">
          <i class="pi pi-check-circle text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
          <p>{{ AUCUNE_DONNEE }}</p>
        </div>
        <template v-else>
          <ul class="cab-bars" role="img" :aria-label="agingAria">
            <li v-for="bucket in agingBuckets" :key="bucket.key" class="cab-bar">
              <div class="cab-bar__head">
                <span class="cab-bar__label">{{ bucket.label }}</span>
                <span class="cab-bar__value">{{ formatDACompact(bucket.value) }}</span>
              </div>
              <div class="cab-bar__track">
                <div class="cab-bar__fill" :class="`cab-bar__fill--${bucket.tone}`" :style="{ width: bucket.width + '%' }"></div>
              </div>
            </li>
          </ul>
          <table class="sr-only">
            <caption>Créances par ancienneté</caption>
            <thead><tr><th>Ancienneté</th><th>Montant impayé</th></tr></thead>
            <tbody>
              <tr v-for="bucket in agingBuckets" :key="bucket.key">
                <td>{{ bucket.label }}</td>
                <td>{{ formatDA(bucket.value) }}</td>
              </tr>
            </tbody>
          </table>
        </template>

        <h3 class="cab-subheading">Top 5 débiteurs</h3>
        <div v-if="topDebiteursVide" class="empty-state" role="status">
          <i class="pi pi-users text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
          <p>{{ AUCUNE_DONNEE }}</p>
        </div>
        <ol v-else class="cab-rank" aria-label="Classement des entreprises débitrices">
          <li v-for="(d, i) in topDebiteurs" :key="d.entreprise_id" class="cab-rank__item">
            <span class="cab-rank__pos" aria-hidden="true">{{ i + 1 }}</span>
            <div class="cab-rank__body">
              <RouterLink
                :to="`/entreprises/${d.entreprise_id}`"
                class="cab-rank__name"
                :aria-label="`Voir la fiche de ${d.raison_sociale}`"
              >{{ d.raison_sociale }}</RouterLink>
              <div class="cab-rank__track">
                <div class="cab-rank__fill" :style="{ width: Math.round((d.montant_impaye / maxDebiteur) * 100) + '%' }"></div>
              </div>
            </div>
            <span class="cab-rank__amount">{{ formatDA(d.montant_impaye) }}</span>
          </li>
        </ol>
      </section>
    </template>
  </div>
</template>

<style scoped>
.cab-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

@media (max-width: 960px) {
  .cab-grid {
    grid-template-columns: 1fr;
  }
}

.cab-etat {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
}

.cab-etat--erreur i {
  font-size: 2.5rem;
  color: var(--ledge-danger);
}

.cab-etat--erreur p {
  margin: 0;
  font-weight: 600;
  color: var(--p-text-color);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}

.panel-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--p-text-color);
  margin: 0;
}

.cab-subtitle {
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
  margin: 0.25rem 0 0;
}

.cab-subheading {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--p-text-color);
  margin: 1.5rem 0 0.85rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: var(--p-text-muted-color);
  text-align: center;
}

.chart-box {
  position: relative;
  height: 18rem;
}

/* ── Créances : gros chiffre ─────────────────────────────────────────── */
.cab-total {
  margin-bottom: 0.5rem;
}

.cab-total-value {
  font-family: var(--ledge-ff-mono);
  font-size: 1.5rem; /* text-2xl */
  font-weight: 700;
  color: var(--p-text-color);
  margin: 0;
  font-variant-numeric: tabular-nums;
}

/* ── Créances : barres d'ancienneté ───────────────────────────────────── */
.cab-bars {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.cab-bar__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 0.35rem;
}

.cab-bar__label {
  font-size: 0.8125rem;
  color: var(--p-text-color);
  font-weight: 500;
}

.cab-bar__value {
  font-size: 0.8125rem;
  font-weight: 700;
  font-family: var(--ledge-ff-mono);
  color: var(--p-text-color);
}

.cab-bar__track {
  height: 10px;
  background: var(--p-surface-border);
  border-radius: 999px;
  overflow: hidden;
}

.cab-bar__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
  min-width: 2px;
}

.cab-bar__fill--warn { background: var(--ledge-warning); }
.cab-bar__fill--strong { background: var(--ledge-accent); }
.cab-bar__fill--danger { background: var(--ledge-danger); }

/* ── Créances : classement top débiteurs ──────────────────────────────── */
.cab-rank {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.cab-rank__item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.cab-rank__pos {
  flex-shrink: 0;
  width: 1.6rem;
  height: 1.6rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: var(--p-surface-border);
  color: var(--p-text-muted-color);
  font-family: var(--ledge-ff-mono);
  font-size: 0.75rem;
  font-weight: 700;
}

.cab-rank__body {
  flex: 1;
  min-width: 0;
}

.cab-rank__name {
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

.cab-rank__name:hover {
  text-decoration: underline;
}

.cab-rank__name:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
  border-radius: 2px;
}

.cab-rank__track {
  height: 6px;
  background: var(--p-surface-border);
  border-radius: 999px;
  overflow: hidden;
}

.cab-rank__fill {
  height: 100%;
  background: var(--ledge-danger);
  border-radius: 999px;
  transition: width 0.5s ease;
  min-width: 2px;
}

.cab-rank__amount {
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  font-family: var(--ledge-ff-mono);
  color: var(--p-text-color);
}

@media (prefers-reduced-motion: reduce) {
  .cab-bar__fill,
  .cab-rank__fill {
    transition: none !important;
  }
}
</style>
