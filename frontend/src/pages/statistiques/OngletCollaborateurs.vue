<script setup lang="ts">
// Onglet Collaborateurs — stats realisees + objectifs du collaborateur selectionne.
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Chart from 'primevue/chart'
import ProgressSpinner from 'primevue/progressspinner'
import { useUsers } from '@/composables/useUsers'
import { useLayout } from '@/layout/composables/layout'
import { statsApi, type CollaborateurKpiStats, type KpiObjectifType } from '@/api/modules/stats'
import { formatDA, formatDACompact, formatDAKpi } from '@/utils/currency'
import { chartColor } from '@/utils/chartPalette'
import ObjectifsEditor from '@/pages/statistiques/ObjectifsEditor.vue'

const props = defineProps<{ exerciceId: number | null }>()

const { users, fetchUsers } = useUsers()
const { isDarkTheme } = useLayout()

// ── Selection du collaborateur ───────────────────────────────────────────────
// Filtre CLIENT strict : jamais 'secretaire' (hors perimetre KPI) ni 'client'.
const collaborateurOptions = computed(() =>
  users.value
    .filter((u) => u.roles.includes('admin') || u.roles.includes('collaborateur'))
    .map((u) => ({ label: u.name, value: u.id })),
)

const selectedUserId = ref<number | null>(null)
const stats = ref<CollaborateurKpiStats | null>(null)
const loading = ref(false)
const error = ref(false)

async function fetchStats(): Promise<void> {
  if (!selectedUserId.value) {
    stats.value = null
    return
  }
  loading.value = true
  error.value = false
  try {
    const res = await statsApi.getCollaborateurKpiStats(selectedUserId.value, props.exerciceId)
    stats.value = res.data
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

watch(selectedUserId, fetchStats)
watch(() => props.exerciceId, () => {
  if (selectedUserId.value) fetchStats()
})

onMounted(() => {
  fetchUsers({ role: ['admin', 'collaborateur'], per_page: 100 })
})

// ── Jauges realise vs cible ───────────────────────────────────────────────────
const typesObjectif: { key: KpiObjectifType; label: string }[] = [
  { key: 'ca_ht', label: 'CA HT' },
  { key: 'missions_cloturees', label: 'Missions clôturées' },
  { key: 'taches_terminees', label: 'Tâches terminées' },
]

function pourcentage(realise: number, objectif: number | null | undefined): number {
  if (objectif === null || objectif === undefined) return 0
  if (objectif === 0) return realise >= 0 ? 100 : 0
  return Math.round((realise / objectif) * 100)
}

function progressSeverity(pct: number): string {
  if (pct >= 100) return 'success'
  if (pct >= 70) return 'info'
  if (pct >= 40) return 'warn'
  return 'danger'
}

// ── Charts (Chart.js) ─────────────────────────────────────────────────────────
const moisCourts = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']
const moisLongs = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']

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

// CA HT realise par mois (barres)
const caMensuelVide = computed(() => !(stats.value?.realise_mensuel?.data ?? []).some((v) => v > 0))

const caMensuelData = computed(() => ({
  labels: moisCourts,
  datasets: [{
    label: 'CA HT réalisé',
    data: stats.value?.realise_mensuel?.data ?? [],
    backgroundColor: isDarkTheme.value ? '#94a3b8' : '#64748b',
    borderRadius: 6,
    maxBarThickness: 38,
  }],
}))

const caMensuelOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: prefersReduced ? false : undefined,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: { label: (ctx: { parsed: { y: number } }) => formatDA(ctx.parsed.y) },
    },
  },
  scales: {
    x: { ticks: { color: themeColors.value.muted }, grid: { display: false } },
    y: {
      beginAtZero: true,
      ticks: { color: themeColors.value.muted, callback: (v: string | number) => formatDACompact(Number(v)) },
      grid: { color: themeColors.value.border },
    },
  },
}))

const caMensuelAria = computed(() => {
  const d = stats.value?.realise_mensuel
  if (!d) return ''
  return `Chiffre d'affaires HT réalisé mensuel ${d.annee} : ` + d.data.map((v, i) => `${moisLongs[i]} ${formatDA(v)}`).join(', ')
})

// Tâches par statut (camembert)
const tachesVides = computed(() => {
  const t = stats.value?.taches_par_statut
  if (!t) return true
  return t.a_faire + t.en_cours + t.terminee + t.bloquee === 0
})

const tachesData = computed(() => {
  const t = stats.value?.taches_par_statut
  return {
    labels: ['À faire', 'En cours', 'Terminée', 'Bloquée'],
    datasets: [{
      data: [t?.a_faire ?? 0, t?.en_cours ?? 0, t?.terminee ?? 0, t?.bloquee ?? 0],
      backgroundColor: ['#94a3b8', '#3b82f6', '#22c55e', '#ef4444'],
      borderWidth: 0,
    }],
  }
})

const tachesOptions = computed(() => ({
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

const tachesAria = computed(() => {
  const t = stats.value?.taches_par_statut
  if (!t) return ''
  return `Répartition des tâches par statut : À faire ${t.a_faire}, En cours ${t.en_cours}, Terminée ${t.terminee}, Bloquée ${t.bloquee}`
})

// Missions par prestation (participation via ses missions, tous statuts)
const missionsPrestation = computed(() => stats.value?.missions_par_prestation ?? [])
const missionsPrestationVide = computed(() => missionsPrestation.value.length === 0)

const missionsPrestationData = computed(() => ({
  labels: missionsPrestation.value.map((p) => p.designation),
  datasets: [{
    data: missionsPrestation.value.map((p) => p.total),
    backgroundColor: missionsPrestation.value.map((_, i) => chartColor(i)),
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
      callbacks: {
        label: (ctx: { label: string; parsed: number }) =>
          `${ctx.label} : ${ctx.parsed} mission${ctx.parsed !== 1 ? 's' : ''}`,
      },
    },
  },
}))

const missionsPrestationAria = computed(() => {
  if (missionsPrestationVide.value) return 'Missions par prestation : aucune donnée'
  return 'Missions du collaborateur par prestation : '
    + missionsPrestation.value.map((p) => `${p.designation} ${p.total}`).join(', ')
})
</script>

<template>
  <div class="onglet-collaborateurs">
    <div class="collab-select-row">
      <label for="select-collaborateur">Collaborateur</label>
      <Select
        id="select-collaborateur"
        v-model="selectedUserId"
        :options="collaborateurOptions"
        option-label="label"
        option-value="value"
        placeholder="Sélectionnez un collaborateur"
        aria-label="Sélectionner un collaborateur"
        show-clear
        filter
        class="collab-select"
      />
    </div>

    <div v-if="!selectedUserId" class="oc-empty-state">
      <i class="pi pi-user" aria-hidden="true"></i>
      <p>Sélectionnez un collaborateur pour afficher ses statistiques.</p>
    </div>

    <div v-else-if="loading" class="oc-loading-state" role="status" aria-live="polite">
      <ProgressSpinner aria-label="Chargement des statistiques du collaborateur" style="width: 40px; height: 40px" />
      <span>Chargement…</span>
    </div>

    <div v-else-if="error" class="oc-error-state" role="alert">
      <i class="pi pi-exclamation-triangle" aria-hidden="true"></i>
      <p>Impossible de charger les statistiques du collaborateur.</p>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="fetchStats" />
    </div>

    <template v-else-if="stats">
      <!-- Cartes KPI -->
      <div class="oc-kpi-cards">
        <article class="card oc-kpi-card" aria-label="CA HT réalisé">
          <p class="oc-kpi-label">CA HT réalisé</p>
          <p class="oc-kpi-value" :title="formatDA(stats.realise.ca_ht)">{{ formatDAKpi(stats.realise.ca_ht) }}</p>
          <p class="oc-kpi-hint">Réalisé sur missions clôturées uniquement</p>
        </article>
        <article class="card oc-kpi-card" aria-label="Missions clôturées">
          <p class="oc-kpi-label">Missions clôturées</p>
          <p class="oc-kpi-value">{{ Math.round(stats.realise.missions_cloturees) }}</p>
        </article>
        <article class="card oc-kpi-card" aria-label="Tâches terminées">
          <p class="oc-kpi-label">Tâches terminées</p>
          <p class="oc-kpi-value">{{ Math.round(stats.realise.taches_terminees) }}</p>
        </article>
        <article class="card oc-kpi-card" :class="{ 'oc-kpi-card--warn': stats.realise.taches_en_retard > 0 }" aria-label="Tâches en retard">
          <p class="oc-kpi-label">Tâches en retard</p>
          <p class="oc-kpi-value">{{ Math.round(stats.realise.taches_en_retard) }}</p>
        </article>
        <article class="card oc-kpi-card" aria-label="Délai moyen de traitement d'une tâche">
          <p class="oc-kpi-label">Délai moyen</p>
          <p class="oc-kpi-value">{{ stats.realise.delai_moyen_tache.toFixed(1) }} j</p>
        </article>
      </div>

      <!-- Graphiques -->
      <div class="oc-charts-grid">
        <div class="card oc-chart-panel">
          <div class="oc-panel-header">
            <h2 class="oc-panel-title">CA HT réalisé par mois</h2>
            <span class="oc-panel-pill">{{ stats.realise_mensuel.annee }}</span>
          </div>
          <div v-if="caMensuelVide" class="oc-empty-state oc-empty-state--inline" role="status">
            <p>Aucun CA réalisé sur cet exercice.</p>
          </div>
          <template v-else>
            <div class="oc-chart-box">
              <Chart type="bar" :data="caMensuelData" :options="caMensuelOptions" role="img" :aria-label="caMensuelAria" />
            </div>
            <table class="sr-only">
              <caption>Chiffre d'affaires HT réalisé par mois — {{ stats.realise_mensuel.annee }}</caption>
              <thead><tr><th>Mois</th><th>CA HT</th></tr></thead>
              <tbody>
                <tr v-for="(val, i) in stats.realise_mensuel.data" :key="i">
                  <td>{{ moisLongs[i] }}</td>
                  <td>{{ formatDA(val) }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </div>

        <div class="card oc-chart-panel">
          <div class="oc-panel-header">
            <h2 class="oc-panel-title">Tâches par statut</h2>
          </div>
          <div v-if="tachesVides" class="oc-empty-state oc-empty-state--inline" role="status">
            <p>Aucune tâche.</p>
          </div>
          <template v-else>
            <div class="oc-chart-box">
              <Chart type="doughnut" :data="tachesData" :options="tachesOptions" role="img" :aria-label="tachesAria" />
            </div>
            <table class="sr-only">
              <caption>Répartition des tâches par statut</caption>
              <thead><tr><th>Statut</th><th>Nombre</th></tr></thead>
              <tbody>
                <tr><td>À faire</td><td>{{ stats.taches_par_statut.a_faire }}</td></tr>
                <tr><td>En cours</td><td>{{ stats.taches_par_statut.en_cours }}</td></tr>
                <tr><td>Terminée</td><td>{{ stats.taches_par_statut.terminee }}</td></tr>
                <tr><td>Bloquée</td><td>{{ stats.taches_par_statut.bloquee }}</td></tr>
              </tbody>
            </table>
          </template>
        </div>

        <div class="card oc-chart-panel">
          <div class="oc-panel-header">
            <h2 class="oc-panel-title">Missions par prestation</h2>
          </div>
          <div v-if="missionsPrestationVide" class="oc-empty-state oc-empty-state--inline" role="status">
            <p>Aucune mission sur cet exercice.</p>
          </div>
          <template v-else>
            <div class="oc-chart-box">
              <Chart type="doughnut" :data="missionsPrestationData" :options="missionsPrestationOptions" role="img" :aria-label="missionsPrestationAria" />
            </div>
            <table class="sr-only">
              <caption>Répartition des missions du collaborateur par prestation</caption>
              <thead><tr><th>Prestation</th><th>Missions</th></tr></thead>
              <tbody>
                <tr v-for="p in missionsPrestation" :key="p.prestation_id">
                  <td>{{ p.designation }}</td>
                  <td>{{ p.total }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </div>
      </div>

      <!-- Jauges réalisé vs cible -->
      <div class="card oc-jauges-panel">
        <h2 class="oc-panel-title">Réalisé vs cible</h2>
        <div class="oc-jauges-grid">
          <div v-for="type in typesObjectif" :key="type.key" class="oc-jauge-row">
            <div class="oc-jauge-label">{{ type.label }}</div>
            <template v-if="stats.objectifs[type.key] != null">
              <div class="oc-jauge-pct-row">
                <Tag
                  class="oc-jauge-tag"
                  :value="`${pourcentage(stats.realise[type.key], stats.objectifs[type.key]?.valeur)}%`"
                  :severity="progressSeverity(pourcentage(stats.realise[type.key], stats.objectifs[type.key]?.valeur))"
                  :aria-label="`Progression ${type.label} : ${pourcentage(stats.realise[type.key], stats.objectifs[type.key]?.valeur)} pour cent`"
                />
              </div>
              <ProgressBar
                :value="Math.min(pourcentage(stats.realise[type.key], stats.objectifs[type.key]?.valeur), 100)"
                :aria-label="`Progression ${type.label} : ${pourcentage(stats.realise[type.key], stats.objectifs[type.key]?.valeur)}%`"
                style="height: 6px; margin-top: 4px;"
              />
            </template>
            <span v-else class="oc-jauge-empty">Pas d'objectif fixé</span>
          </div>
        </div>
      </div>

      <!-- Editeur des objectifs annuels -->
      <div class="card">
        <ObjectifsEditor
          :key="stats.user.id"
          :user="stats.user"
          :objectifs="stats.objectifs"
          :exercice-id="exerciceId"
          @saved="fetchStats"
        />
      </div>
    </template>
  </div>
</template>

<style scoped>
.onglet-collaborateurs {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.collab-select-row {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  max-width: 320px;
}

.collab-select-row label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
}

.collab-select {
  width: 100%;
}

.oc-empty-state,
.oc-loading-state,
.oc-error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
  text-align: center;
}

.oc-empty-state--inline {
  padding: 1.5rem 1rem;
}

.oc-empty-state i { font-size: 2.5rem; opacity: 0.4; }
.oc-error-state i { font-size: 2.5rem; color: var(--ledge-danger); }
.oc-error-state p { margin: 0; font-weight: 600; color: var(--p-text-color); }

/* ── Cartes KPI ────────────────────────────────────────────────────────── */
.oc-kpi-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
}

.oc-kpi-card {
  padding: 1rem 1.25rem;
}

.oc-kpi-card--warn {
  border-color: var(--ledge-danger);
}

.oc-kpi-label {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  margin: 0 0 0.4rem;
}

.oc-kpi-value {
  font-family: var(--ledge-ff-mono);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--p-text-color);
  margin: 0;
}

.oc-kpi-hint {
  font-size: 0.7rem;
  color: var(--p-text-muted-color);
  margin: 0.4rem 0 0;
}

/* ── Graphiques ────────────────────────────────────────────────────────── */
.oc-charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1rem;
}

.oc-chart-panel {
  padding: 1.25rem;
}

.oc-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.oc-panel-title {
  font-size: 1rem;
  font-weight: 700;
  margin: 0;
  color: var(--p-text-color);
}

.oc-panel-pill {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  background: var(--p-surface-ground);
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}

.oc-chart-box {
  position: relative;
  height: 16rem;
}

/* ── Jauges ────────────────────────────────────────────────────────────── */
.oc-jauges-panel {
  padding: 1.25rem;
}

.oc-jauges-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-top: 0.75rem;
}

.oc-jauge-row {
  padding: 0.75rem;
  background: var(--p-surface-ground);
  border-radius: 6px;
}

.oc-jauge-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-color);
  margin-bottom: 0.5rem;
}

.oc-jauge-pct-row {
  display: flex;
  justify-content: flex-end;
}

.oc-jauge-tag {
  font-family: var(--ledge-ff-mono);
}

.oc-jauge-empty {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

@media (max-width: 600px) {
  .collab-select-row {
    max-width: 100%;
  }
}
</style>
