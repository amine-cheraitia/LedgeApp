<script setup lang="ts">
import { useDashboardStats } from '@/composables/useDashboardStats'
import { useAuthStore } from '@/stores/auth'
import { useCountUp } from '@/composables/useCountUp'
import { useLayout } from '@/layout/composables/layout'
import SecretaireDashboardSection from '@/pages/dashboard/SecretaireDashboardSection.vue'
import { prioriteLabel, prioriteSeverity } from '@/utils/priorite'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import Select from 'primevue/select'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Chart from 'primevue/chart'
import ProgressSpinner from 'primevue/progressspinner'

const auth = useAuthStore()
const { isDarkTheme } = useLayout()
const {
  loading,
  adminStats: stats,
  collaborateurStats: collabStats,
  secretaireStats,
  fetchAdminStats,
  fetchCollaborateurStats,
  fetchSecretaireStats,
} = useDashboardStats()
const exerciceId = ref<number | null>(null)

async function fetchStats() {
  if (auth.isCollaborateur) {
    await fetchCollaborateurStats()
    return
  }
  if (auth.isSecretaire) {
    await fetchSecretaireStats()
    return
  }
  await fetchAdminStats(exerciceId.value)
}

onMounted(fetchStats)

// ── Count-up animations ────────────────────────────────────────────────────
const missionTotal   = computed(() => collabStats.value?.missions.total ?? 0)
const tacheTotal     = computed(() => collabStats.value?.taches.total ?? 0)
const tauxCompletion = computed(() => collabStats.value?.taches.taux_completion ?? 0)
const tachesBloquees = computed(() => collabStats.value?.taches.bloquees ?? 0)

const animMissions = useCountUp(missionTotal)
const animTaches   = useCountUp(tacheTotal)
const animTaux     = useCountUp(tauxCompletion)
const animBloquees = useCountUp(tachesBloquees)

// ── Helpers ────────────────────────────────────────────────────────────────
function alerteSeverity(type: string): 'error' | 'warn' | 'info' | 'success' {
  return type === 'danger' ? 'error' : type === 'warn' ? 'warn' : 'info'
}

function formatDA(montant: number): string {
  return new Intl.NumberFormat('fr-DZ', { style: 'decimal', minimumFractionDigits: 2 }).format(montant) + ' DA'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatRelativeDate(dateStr: string): string {
  const diff = Math.round((Date.now() - new Date(dateStr).getTime()) / 86400000)
  if (diff === 0) return "Aujourd'hui"
  if (diff === 1) return 'Hier'
  if (diff < 7) return `Il y a ${diff} jours`
  return formatDate(dateStr)
}

function joursRestants(dateStr: string): number {
  return Math.ceil((new Date(dateStr).getTime() - Date.now()) / 86400000)
}

function statutPaiementSeverity(statut: string): string {
  const map: Record<string, string> = { en_attente: 'warn', partiel: 'info', solde: 'success' }
  return map[statut] ?? 'secondary'
}

function statutPaiementLabel(statut: string): string {
  const map: Record<string, string> = { en_attente: 'En attente', partiel: 'Partiel', solde: 'Soldé' }
  return map[statut] ?? statut
}

function statutMissionSeverity(statut: string): string {
  const map: Record<string, string> = { en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger' }
  return map[statut] ?? 'secondary'
}

function statutMissionLabel(statut: string): string {
  const map: Record<string, string> = { en_cours: 'En cours', terminee: 'Terminée', suspendue: 'Suspendue', annulee: 'Annulée' }
  return map[statut] ?? statut
}

// ── SVG Donut Chart ────────────────────────────────────────────────────────
const RING_RADIUS = 38
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS
const DONUT_GAP = 4 // séparation visuelle entre segments (unités SVG)

const donutSegments = computed(() => {
  const t = collabStats.value?.taches
  if (!t || t.total === 0) return []
  const C = RING_CIRCUMFERENCE
  const parts = [
    { value: t.terminees, color: '#22c55e' },
    { value: t.en_cours,  color: '#3b82f6' },
    { value: t.a_faire,   color: '#94a3b8' },
    { value: t.bloquees,  color: '#ef4444' },
  ]
  const active = parts.filter(p => p.value > 0)
  let cumulative = 0
  return active.map(p => {
    const rawArc = (p.value / t.total) * C
    // gap uniquement s'il y a plusieurs segments, arc minimum de 8 pour rester visible
    const gap = active.length > 1 ? DONUT_GAP : 0
    const arc = Math.max(rawArc - gap, 8)
    const seg = {
      color: p.color,
      dasharray: `${arc} ${C}`,
      dashoffset: C - cumulative,
      pct: Math.round(p.value / t.total * 100),
    }
    cumulative += rawArc // position suivante basée sur l'arc brut
    return seg
  })
})

// ── Charts admin (Chart.js) ─────────────────────────────────────────────────
const moisCourts = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']
const moisLongs = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']

const prefersReduced = typeof window !== 'undefined'
  && typeof window.matchMedia === 'function'
  && window.matchMedia('(prefers-reduced-motion: reduce)').matches

function formatDACompact(montant: number): string {
  return new Intl.NumberFormat('fr-DZ', { notation: 'compact', maximumFractionDigits: 1 }).format(montant) + ' DA'
}

// Couleurs lues sur les tokens PrimeVue (réactives au dark mode)
const themeColors = ref({ text: '', muted: '', border: '', primary: '' })

function refreshThemeColors(): void {
  const s = getComputedStyle(document.documentElement)
  themeColors.value = {
    text: s.getPropertyValue('--p-text-color').trim(),
    muted: s.getPropertyValue('--p-text-muted-color').trim(),
    border: s.getPropertyValue('--p-surface-border').trim(),
    primary: s.getPropertyValue('--p-primary-color').trim(),
  }
}

onMounted(refreshThemeColors)
watch(isDarkTheme, async () => {
  await nextTick()
  refreshThemeColors()
})

// CA mensuel (barres)
const caMensuelVide = computed(() => !(stats.value?.ca_mensuel?.data ?? []).some((v) => v > 0))

const caMensuelData = computed(() => ({
  labels: moisCourts,
  datasets: [{
    label: 'CA TTC',
    data: stats.value?.ca_mensuel?.data ?? [],
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
  const d = stats.value?.ca_mensuel
  if (!d) return ''
  return `Chiffre d'affaires mensuel ${d.annee} : ` + d.data.map((v, i) => `${moisLongs[i]} ${formatDA(v)}`).join(', ')
})

// Missions par statut (camembert)
const missionsVides = computed(() => !stats.value?.missions || stats.value.missions.total === 0)

const missionsData = computed(() => {
  const m = stats.value?.missions
  return {
    labels: ['En cours', 'Terminées', 'Suspendues', 'Annulées'],
    datasets: [{
      data: [m?.en_cours ?? 0, m?.terminees ?? 0, m?.suspendues ?? 0, m?.annulees ?? 0],
      backgroundColor: ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444'],
      borderWidth: 0,
    }],
  }
})

const missionsOptions = computed(() => ({
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

const missionsAria = computed(() => {
  const m = stats.value?.missions
  if (!m) return ''
  return `Répartition des missions par statut : En cours ${m.en_cours}, Terminées ${m.terminees}, Suspendues ${m.suspendues}, Annulées ${m.annulees}`
})

// ── Today's date display ───────────────────────────────────────────────────
const todayLabel = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <section aria-labelledby="dashboard-title">
  <div class="grid grid-cols-12 gap-8">
    <!-- Header -->
    <div class="col-span-12">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h2 id="dashboard-title" class="text-2xl font-bold text-surface-900 dark:text-surface-0 m-0">Tableau de bord</h2>
          <p class="text-muted-color mt-1">Bienvenue, {{ auth.user?.name }}.</p>
        </div>
        <div v-if="auth.isAdmin && stats">
          <label for="filtre-exercice" class="sr-only">Filtrer par exercice</label>
          <Select
            id="filtre-exercice"
            v-model="exerciceId"
            :options="[{ id: null, annee: 'Tous les exercices' }, ...stats.exercices]"
            option-label="annee"
            option-value="id"
            aria-label="Filtrer les KPI par exercice"
            @change="fetchStats"
          />
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="col-span-12 flex justify-center py-20">
      <ProgressSpinner aria-label="Chargement du tableau de bord" />
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- Dashboard collaborateur                                          -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <template v-if="!loading && auth.isCollaborateur && collabStats">

      <!-- ── Row 1 : Hero Banner ── -->
      <div class="col-span-12">
        <div class="hero-banner" role="banner">
          <div class="hero-banner__left">
            <p class="hero-today">{{ todayLabel }}</p>
            <h3 class="hero-greeting">Bonjour, {{ auth.user?.name }}</h3>
            <div class="hero-summary" aria-label="Résumé de votre activité">
              <span class="hero-chip">
                <i class="pi pi-briefcase" aria-hidden="true"></i>
                {{ collabStats.missions.en_cours }} mission{{ collabStats.missions.en_cours !== 1 ? 's' : '' }} en cours
              </span>
              <span class="hero-chip">
                <i class="pi pi-check-square" aria-hidden="true"></i>
                {{ collabStats.taches.en_cours }} tâche{{ collabStats.taches.en_cours !== 1 ? 's' : '' }} en cours
              </span>
              <span
                class="hero-chip"
                :class="collabStats.taches.taux_completion >= 70 ? 'hero-chip--success' : 'hero-chip--warn'"
              >
                <i class="pi pi-chart-pie" aria-hidden="true"></i>
                {{ collabStats.taches.taux_completion }}% complété
              </span>
            </div>
          </div>
          <div
            v-if="collabStats.taches.en_retard > 0"
            class="hero-alert"
            role="alert"
            aria-live="polite"
          >
            <i class="pi pi-exclamation-triangle hero-alert__icon" aria-hidden="true"></i>
            <div>
              <p class="hero-alert__title">Tâches en retard</p>
              <p class="hero-alert__msg">
                {{ collabStats.taches.en_retard }} tâche{{ collabStats.taches.en_retard !== 1 ? 's' : '' }} dépassée{{ collabStats.taches.en_retard !== 1 ? 's' : '' }}
              </p>
            </div>
          </div>
          <div v-else class="hero-alert hero-alert--ok" role="status">
            <i class="pi pi-check-circle hero-alert__icon" aria-hidden="true"></i>
            <div>
              <p class="hero-alert__title">À jour</p>
              <p class="hero-alert__msg">Aucune tâche en retard</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Row 2 : KPI Cards avec count-up ── -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <article class="card kpi-card kpi-card--blue" aria-label="Missions assignées">
          <div class="kpi-card__top">
            <p class="kpi-card__label">Missions assignées</p>
            <span class="kpi-card__icon" aria-hidden="true"><i class="pi pi-briefcase"></i></span>
          </div>
          <p class="kpi-card__value" :aria-label="`${collabStats.missions.total} missions assignées`">{{ animMissions }}</p>
          <p class="kpi-card__sub">
            <span class="text-blue-500 font-semibold">{{ collabStats.missions.en_cours }} en cours</span>
            <span class="kpi-dot" aria-hidden="true"> · </span>
            <span class="text-green-500 font-semibold">{{ collabStats.missions.terminees }} terminées</span>
          </p>
        </article>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <article class="card kpi-card kpi-card--orange" aria-label="Mes tâches">
          <div class="kpi-card__top">
            <p class="kpi-card__label">Mes tâches</p>
            <span class="kpi-card__icon" aria-hidden="true"><i class="pi pi-check-square"></i></span>
          </div>
          <p class="kpi-card__value" :aria-label="`${collabStats.taches.total} tâches`">{{ animTaches }}</p>
          <p class="kpi-card__sub">
            <span class="text-amber-500 font-semibold">{{ collabStats.taches.en_cours }} en cours</span>
            <span class="kpi-dot" aria-hidden="true"> · </span>
            <span>{{ collabStats.taches.a_faire }} à faire</span>
          </p>
        </article>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <article
          class="card kpi-card"
          :class="collabStats.taches.taux_completion >= 70 ? 'kpi-card--green' : 'kpi-card--orange'"
          :aria-label="`Taux de complétion : ${collabStats.taches.taux_completion}%`"
        >
          <div class="kpi-card__top">
            <p class="kpi-card__label">Taux de complétion</p>
            <span class="kpi-card__icon" aria-hidden="true">
              <i :class="collabStats.taches.taux_completion >= 70 ? 'pi pi-check-circle' : 'pi pi-clock'"></i>
            </span>
          </div>
          <p
            class="kpi-card__value"
            :class="collabStats.taches.taux_completion >= 70 ? 'text-green-500' : 'text-amber-500'"
            :aria-label="`Taux de complétion : ${collabStats.taches.taux_completion}%`"
          >{{ animTaux }}%</p>
          <p class="kpi-card__sub">{{ collabStats.taches.terminees }} / {{ collabStats.taches.total }} tâches terminées</p>
        </article>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <article
          class="card kpi-card"
          :class="collabStats.taches.bloquees > 0 ? 'kpi-card--red' : 'kpi-card--green'"
          :aria-label="`${collabStats.taches.bloquees} tâche${collabStats.taches.bloquees !== 1 ? 's' : ''} bloquée${collabStats.taches.bloquees !== 1 ? 's' : ''}`"
        >
          <div class="kpi-card__top">
            <p class="kpi-card__label">Tâches bloquées</p>
            <span class="kpi-card__icon" aria-hidden="true">
              <i :class="collabStats.taches.bloquees > 0 ? 'pi pi-ban' : 'pi pi-check'"></i>
            </span>
          </div>
          <p class="kpi-card__value" :class="collabStats.taches.bloquees > 0 ? 'text-red-500' : 'text-green-500'">
            {{ animBloquees }}
          </p>
          <p class="kpi-card__sub">{{ collabStats.taches.bloquees > 0 ? 'Nécessitent votre attention' : 'Aucun blocage actif' }}</p>
        </article>
      </div>

      <!-- ── Row 3 : Charts 4+4+4 ── -->

      <!-- Graphique 1 : Répartition des tâches (donut SVG) -->
      <div class="col-span-12 lg:col-span-4">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Répartition des tâches</h3>
            <span class="kpi-pill kpi-pill--muted">{{ collabStats.taches.total }} total</span>
          </div>
          <div v-if="collabStats.taches.total === 0" class="empty-state" role="status">
            <i class="pi pi-chart-pie text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune tâche assignée.</p>
          </div>
          <div v-else class="donut-wrap" aria-label="Répartition des tâches par statut">
            <div class="donut-svg-wrap">
              <svg viewBox="0 0 88 88" class="donut-svg" aria-hidden="true">
                <circle class="donut-track" cx="44" cy="44" r="38" />
                <circle
                  v-for="(seg, i) in donutSegments"
                  :key="i"
                  class="donut-seg"
                  cx="44" cy="44" r="38"
                  :stroke="seg.color"
                  :style="{ strokeDasharray: seg.dasharray, strokeDashoffset: seg.dashoffset }"
                />
              </svg>
              <div class="donut-center" aria-hidden="true">
                <span class="donut-total">{{ collabStats.taches.taux_completion }}%</span>
                <span class="donut-sub">complété</span>
              </div>
            </div>
            <ul class="chart-legend" role="list" aria-label="Légende répartition tâches">
              <li class="chart-legend-item" :class="{ 'chart-legend-item--zero': collabStats.taches.terminees === 0 }">
                <span class="chart-legend-dot" style="background:#22c55e" aria-hidden="true"></span>
                <span class="chart-legend-label">Terminées</span>
                <span class="chart-legend-val">{{ collabStats.taches.terminees }}</span>
                <span v-if="collabStats.taches.terminees > 0" class="chart-legend-pct">{{ Math.round(collabStats.taches.terminees / collabStats.taches.total * 100) }}%</span>
              </li>
              <li class="chart-legend-item" :class="{ 'chart-legend-item--zero': collabStats.taches.en_cours === 0 }">
                <span class="chart-legend-dot" style="background:#3b82f6" aria-hidden="true"></span>
                <span class="chart-legend-label">En cours</span>
                <span class="chart-legend-val">{{ collabStats.taches.en_cours }}</span>
                <span v-if="collabStats.taches.en_cours > 0" class="chart-legend-pct">{{ Math.round(collabStats.taches.en_cours / collabStats.taches.total * 100) }}%</span>
              </li>
              <li class="chart-legend-item" :class="{ 'chart-legend-item--zero': collabStats.taches.a_faire === 0 }">
                <span class="chart-legend-dot" style="background:#94a3b8" aria-hidden="true"></span>
                <span class="chart-legend-label">À faire</span>
                <span class="chart-legend-val">{{ collabStats.taches.a_faire }}</span>
                <span v-if="collabStats.taches.a_faire > 0" class="chart-legend-pct">{{ Math.round(collabStats.taches.a_faire / collabStats.taches.total * 100) }}%</span>
              </li>
              <li class="chart-legend-item" :class="{ 'chart-legend-item--zero': collabStats.taches.bloquees === 0 }">
                <span class="chart-legend-dot" style="background:#ef4444" aria-hidden="true"></span>
                <span class="chart-legend-label">Bloquées</span>
                <span class="chart-legend-val">{{ collabStats.taches.bloquees }}</span>
                <span v-if="collabStats.taches.bloquees > 0" class="chart-legend-pct">{{ Math.round(collabStats.taches.bloquees / collabStats.taches.total * 100) }}%</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Graphique 2 : Progression des missions (barres horizontales) -->
      <div class="col-span-12 lg:col-span-4">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Progression des missions</h3>
            <span class="kpi-pill kpi-pill--muted">{{ collabStats.missions.total }} missions</span>
          </div>
          <div v-if="collabStats.mes_missions.length === 0" class="empty-state" role="status">
            <i class="pi pi-chart-bar text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune mission assignée.</p>
          </div>
          <ul v-else class="bar-list" role="list" aria-label="Progression par mission">
            <li v-for="m in collabStats.mes_missions" :key="m.id" class="bar-item">
              <div class="bar-item__header">
                <router-link :to="`/missions/${m.id}`" class="mission-ref" :aria-label="`Mission ${m.reference}`">
                  {{ m.reference }}
                </router-link>
                <div class="bar-item__right">
                  <Tag :value="statutMissionLabel(m.statut)" :severity="statutMissionSeverity(m.statut)" />
                  <span class="bar-pct">{{ m.progression }}%</span>
                </div>
              </div>
              <div
                class="bar-track"
                role="progressbar"
                :aria-valuenow="m.progression"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="`${m.reference} : ${m.progression}%`"
              >
                <div
                  class="bar-fill"
                  :style="{ width: m.progression + '%' }"
                  :class="m.progression === 100 ? 'bar-fill--green' : m.progression > 0 ? 'bar-fill--blue' : 'bar-fill--empty'"
                ></div>
              </div>
              <p v-if="m.entreprise" class="bar-client">{{ m.entreprise }}</p>
            </li>
          </ul>
        </div>
      </div>

      <!-- Graphique 3 : Timeline des échéances -->
      <div class="col-span-12 lg:col-span-4">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Échéances à venir</h3>
            <span class="kpi-pill kpi-pill--muted">30 jours</span>
          </div>
          <div v-if="!collabStats.echeances || collabStats.echeances.length === 0" class="empty-state" role="status">
            <i class="pi pi-calendar text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune échéance imminente.</p>
          </div>
          <ul v-else class="timeline-list" role="list" aria-label="Échéances à venir">
            <li
              v-for="e in collabStats.echeances"
              :key="`${e.type}-${e.id}`"
              class="timeline-item"
              :class="{
                'timeline-item--retard': e.en_retard,
                'timeline-item--urgent': !e.en_retard && joursRestants(e.date) <= 7
              }"
            >
              <span class="timeline-icon" aria-hidden="true">
                <i :class="e.type === 'mission' ? 'pi pi-briefcase' : 'pi pi-check-square'"></i>
              </span>
              <div class="timeline-body">
                <router-link
                  v-if="e.mission_id"
                  :to="`/missions/${e.mission_id}`"
                  class="timeline-title"
                  :aria-label="`${e.type === 'mission' ? 'Mission' : 'Tâche'} ${e.titre}`"
                >{{ e.titre }}</router-link>
                <span v-else class="timeline-title">{{ e.titre }}</span>
                <p v-if="e.entreprise" class="timeline-meta">{{ e.entreprise }}</p>
              </div>
              <div class="timeline-date">
                <span
                  class="timeline-badge"
                  :class="{
                    'timeline-badge--retard': e.en_retard,
                    'timeline-badge--urgent': !e.en_retard && joursRestants(e.date) <= 7,
                    'timeline-badge--ok': !e.en_retard && joursRestants(e.date) > 7
                  }"
                  :aria-label="e.en_retard ? 'En retard' : `Dans ${joursRestants(e.date)} jours`"
                >
                  {{ e.en_retard ? 'Retard' : `J-${joursRestants(e.date)}` }}
                </span>
                <time :datetime="e.date" class="timeline-dt">{{ formatDate(e.date) }}</time>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Row 4 : Listes 6+6 ── -->

      <!-- Mes missions -->
      <div class="col-span-12 lg:col-span-6">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Mes missions</h3>
            <router-link to="/missions" class="panel-link" aria-label="Voir toutes mes missions">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1" aria-hidden="true"></i>
            </router-link>
          </div>
          <div v-if="collabStats.mes_missions.length === 0" class="empty-state" role="status">
            <i class="pi pi-inbox text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune mission assignée.</p>
          </div>
          <ul v-else class="mission-list" role="list" aria-label="Mes missions récentes">
            <li v-for="m in collabStats.mes_missions" :key="m.id" class="mission-item">
              <div class="mission-item__main">
                <router-link :to="`/missions/${m.id}`" class="mission-ref" :aria-label="`Ouvrir la mission ${m.reference}`">
                  {{ m.reference }}
                </router-link>
                <span class="mission-client">{{ m.entreprise ?? '—' }}</span>
              </div>
              <div class="mission-item__right">
                <Tag :value="statutMissionLabel(m.statut)" :severity="statutMissionSeverity(m.statut)" />
                <div
                  class="mission-progress"
                  role="progressbar"
                  :aria-valuenow="m.progression"
                  aria-valuemin="0"
                  aria-valuemax="100"
                  :aria-label="`Progression ${m.reference} : ${m.progression}%`"
                >
                  <div class="mission-progress__bar">
                    <div class="mission-progress__fill" :style="{ width: m.progression + '%' }"></div>
                  </div>
                  <span class="mission-progress__pct">{{ m.progression }}%</span>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Tâches urgentes avec badge priorité -->
      <div class="col-span-12 lg:col-span-6">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Tâches à traiter</h3>
            <router-link to="/missions" class="panel-link" aria-label="Voir toutes mes missions">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1" aria-hidden="true"></i>
            </router-link>
          </div>
          <div v-if="collabStats.mes_taches_urgentes.length === 0" class="empty-state" role="status">
            <i class="pi pi-check-circle text-4xl text-green-500 mb-3" aria-hidden="true"></i>
            <p>Aucune tâche en cours.</p>
          </div>
          <ul v-else class="task-list" role="list" aria-label="Tâches urgentes à traiter">
            <li
              v-for="t in collabStats.mes_taches_urgentes"
              :key="t.id"
              class="task-item"
              :class="{ 'task-item--retard': t.en_retard, 'task-item--en-cours': t.statut === 'en_cours' && !t.en_retard }"
            >
              <span class="task-item__icon" aria-hidden="true">
                <i
                  :class="['pi', t.en_retard ? 'pi-exclamation-triangle text-red-500' : t.statut === 'en_cours' ? 'pi-spin pi-spinner text-blue-500' : 'pi-circle']"
                  :style="!t.en_retard && t.statut !== 'en_cours' ? 'color: var(--p-text-muted-color)' : ''"
                ></i>
              </span>
              <div class="task-item__body">
                <p class="task-title">{{ t.titre }}</p>
                <p class="task-meta">
                  <router-link :to="`/missions/${t.mission_id}`" class="task-mission-link" :aria-label="`Mission ${t.mission_reference}`">
                    {{ t.mission_reference }}
                  </router-link>
                  <span v-if="t.entreprise"> · {{ t.entreprise }}</span>
                </p>
              </div>
              <div class="task-item__end">
                <div class="task-badges">
                  <Tag
                    v-if="t.priorite >= 2"
                    :value="prioriteLabel(t.priorite)"
                    :severity="prioriteSeverity(t.priorite)"
                  />
                  <Tag :value="t.statut === 'en_cours' ? 'En cours' : 'À faire'" :severity="t.statut === 'en_cours' ? 'info' : 'secondary'" />
                </div>
                <time
                  v-if="t.date_fin"
                  :datetime="t.date_fin"
                  class="task-date"
                  :class="{ 'task-date--retard': t.en_retard }"
                >{{ formatDate(t.date_fin) }}</time>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Row 5 : Fil d'activité récente ── -->
      <div class="col-span-12">
        <div class="card">
          <div class="panel-header">
            <h3 class="panel-title">Activité récente</h3>
            <span class="kpi-pill kpi-pill--green">Tâches terminées</span>
          </div>
          <div
            v-if="!collabStats.activite_recente || collabStats.activite_recente.length === 0"
            class="empty-state"
            role="status"
          >
            <i class="pi pi-history text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune activité récente.</p>
          </div>
          <ul v-else class="activity-grid" role="list" aria-label="Fil d'activité récent">
            <li
              v-for="a in collabStats.activite_recente"
              :key="a.id"
              class="activity-item"
            >
              <span class="activity-icon" aria-hidden="true">
                <i class="pi pi-check-circle"></i>
              </span>
              <div class="activity-body">
                <p class="activity-title">{{ a.titre }}</p>
                <p class="activity-meta">
                  <router-link
                    v-if="a.mission_id"
                    :to="`/missions/${a.mission_id}`"
                    class="task-mission-link"
                    :aria-label="`Mission ${a.mission_reference}`"
                  >{{ a.mission_reference }}</router-link>
                  <span v-else>—</span>
                </p>
              </div>
              <time :datetime="a.date" class="activity-date">{{ formatRelativeDate(a.date) }}</time>
            </li>
          </ul>
        </div>
      </div>

    </template>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- Dashboard secrétaire                                               -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div v-if="!loading && auth.isSecretaire && secretaireStats" class="col-span-12">
      <SecretaireDashboardSection :stats="secretaireStats" />
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- Dashboard admin                                                    -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <template v-if="stats && !loading && auth.isAdmin">
      <!-- Alertes -->
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

      <!-- KPI widgets -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-4">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">CA du mois</span>
            <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-chart-line text-cyan-500 text-xl!" aria-hidden="true"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">{{ formatDA(stats.kpi.ca_mois) }}</div>
          <div class="mt-4">
            <span class="text-muted-color text-sm">Factures émises ce mois</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-4">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">TVA collectée</span>
            <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-percentage text-purple-500 text-xl!" aria-hidden="true"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">{{ formatDA(stats.kpi.tva_collectee) }}</div>
          <div class="mt-4">
            <span class="text-muted-color text-sm">Cumul sur la période</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-4">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Taux de recouvrement</span>
            <div
              class="flex items-center justify-center rounded-border"
              :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'bg-green-100 dark:bg-green-400/10' : 'bg-amber-100 dark:bg-amber-400/10'"
              style="width: 2.5rem; height: 2.5rem"
            >
              <i
                class="text-xl!"
                :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'pi pi-check-circle text-green-500' : 'pi pi-exclamation-circle text-amber-500'"
                aria-hidden="true"
              ></i>
            </div>
          </div>
          <div
            class="font-bold text-2xl"
            :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'"
          >
            {{ stats.kpi.taux_recouvrement }}%
          </div>
          <div class="mt-3">
            <div
              role="progressbar"
              :aria-valuenow="stats.kpi.taux_recouvrement"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-label="`Taux de recouvrement : ${stats.kpi.taux_recouvrement}%`"
              class="recouvrement-bar"
            >
              <div
                class="recouvrement-fill"
                :style="{ width: stats.kpi.taux_recouvrement + '%' }"
                :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'fill-success' : 'fill-warn'"
              />
            </div>
            <span class="text-muted-color text-xs mt-1 block">Seuil : {{ stats.kpi.seuil_recouvrement }}%</span>
          </div>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Clients</span>
            <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-building text-blue-500 text-xl!"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ stats.entreprises.clients }}</div>
          <div class="flex items-center mt-4">
            <span class="text-muted-color">{{ stats.entreprises.prospects }} prospects</span>
            <span class="text-muted-color mx-2">&bull;</span>
            <span class="font-medium">{{ stats.entreprises.total }} total</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Missions actives</span>
            <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-briefcase text-blue-500 text-xl!"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ stats.missions.en_cours }}</div>
          <div class="flex items-center mt-4">
            <span class="text-green-500 font-medium">{{ stats.missions.terminees }} terminées</span>
            <span class="text-muted-color mx-2">&bull;</span>
            <span class="text-muted-color">{{ stats.missions.total }} total</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Chiffre d'affaires</span>
            <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-dollar text-cyan-500 text-xl!"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ formatDA(stats.factures.ca_ttc) }}</div>
          <div class="flex items-center mt-4">
            <span class="text-green-500 font-medium">{{ formatDA(stats.factures.total_paye) }}</span>
            <span class="text-muted-color ml-1">encaissé</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Impayés</span>
            <div class="flex items-center justify-center rounded-border" :class="stats.factures.en_retard > 0 ? 'bg-red-100 dark:bg-red-400/10' : 'bg-green-100 dark:bg-green-400/10'" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-exclamation-triangle text-xl!" :class="stats.factures.en_retard > 0 ? 'text-red-500' : 'text-green-500'"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ formatDA(stats.factures.total_impaye) }}</div>
          <div class="flex items-center mt-4">
            <span v-if="stats.factures.en_retard > 0" class="text-red-500 font-medium">{{ stats.factures.en_retard }} en retard</span>
            <span v-else class="text-green-500 font-medium">Aucun retard</span>
            <span class="text-muted-color mx-2">&bull;</span>
            <span class="text-muted-color">{{ stats.factures.en_attente + stats.factures.partielles }} factures</span>
          </div>
        </div>
      </div>

      <!-- ── Graphiques ── -->
      <!-- CA mensuel (barres) -->
      <div class="col-span-12 xl:col-span-8">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Chiffre d'affaires mensuel</h3>
            <span class="kpi-pill kpi-pill--muted">{{ stats.ca_mensuel.annee }}</span>
          </div>
          <div v-if="caMensuelVide" class="empty-state" role="status">
            <i class="pi pi-chart-bar text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune facture sur cet exercice.</p>
          </div>
          <template v-else>
            <div class="chart-box">
              <Chart type="bar" :data="caMensuelData" :options="caMensuelOptions" role="img" :aria-label="caMensuelAria" />
            </div>
            <table class="sr-only">
              <caption>Chiffre d'affaires TTC par mois — {{ stats.ca_mensuel.annee }}</caption>
              <thead><tr><th>Mois</th><th>CA TTC</th></tr></thead>
              <tbody>
                <tr v-for="(val, i) in stats.ca_mensuel.data" :key="i">
                  <td>{{ moisLongs[i] }}</td>
                  <td>{{ formatDA(val) }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </div>
      </div>

      <!-- Missions par statut (camembert) -->
      <div class="col-span-12 xl:col-span-4">
        <div class="card h-full">
          <div class="panel-header">
            <h3 class="panel-title">Missions par statut</h3>
            <span class="kpi-pill kpi-pill--muted">{{ stats.missions.total }} total</span>
          </div>
          <div v-if="missionsVides" class="empty-state" role="status">
            <i class="pi pi-briefcase text-4xl mb-3" style="color: var(--p-text-muted-color)" aria-hidden="true"></i>
            <p>Aucune mission sur cet exercice.</p>
          </div>
          <template v-else>
            <div class="chart-box">
              <Chart type="doughnut" :data="missionsData" :options="missionsOptions" role="img" :aria-label="missionsAria" />
            </div>
            <table class="sr-only">
              <caption>Répartition des missions par statut</caption>
              <thead><tr><th>Statut</th><th>Nombre</th></tr></thead>
              <tbody>
                <tr><td>En cours</td><td>{{ stats.missions.en_cours }}</td></tr>
                <tr><td>Terminées</td><td>{{ stats.missions.terminees }}</td></tr>
                <tr><td>Suspendues</td><td>{{ stats.missions.suspendues }}</td></tr>
                <tr><td>Annulées</td><td>{{ stats.missions.annulees }}</td></tr>
              </tbody>
            </table>
          </template>
        </div>
      </div>

      <!-- Devis résumé -->
      <div class="col-span-12 xl:col-span-4">
        <div class="card h-full">
          <div class="text-surface-900 dark:text-surface-0 font-bold text-lg mb-4">Devis</div>
          <ul class="list-none p-0 m-0">
            <li class="flex items-center justify-between py-3 border-b border-surface">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2rem; height: 2rem">
                  <i class="pi pi-file text-blue-500 text-sm"></i>
                </div>
                <span class="text-surface-900 dark:text-surface-0 font-medium">En attente</span>
              </div>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ stats.devis.en_attente }}</span>
            </li>
            <li class="flex items-center justify-between py-3 border-b border-surface">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center bg-green-100 dark:bg-green-400/10 rounded-border" style="width: 2rem; height: 2rem">
                  <i class="pi pi-check text-green-500 text-sm"></i>
                </div>
                <span class="text-surface-900 dark:text-surface-0 font-medium">Acceptés</span>
              </div>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ stats.devis.acceptes }}</span>
            </li>
            <li class="flex items-center justify-between py-3">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border" style="width: 2rem; height: 2rem">
                  <i class="pi pi-wallet text-purple-500 text-sm"></i>
                </div>
                <span class="text-surface-900 dark:text-surface-0 font-medium">CA potentiel</span>
              </div>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ formatDA(stats.devis.ca_potentiel) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Factures récentes -->
      <div class="col-span-12 xl:col-span-8">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <div class="text-surface-900 dark:text-surface-0 font-bold text-lg">Dernières factures</div>
            <router-link to="/factures" class="text-primary font-medium no-underline hover:underline text-sm">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1"></i>
            </router-link>
          </div>
          <DataTable :value="stats.recentes.factures" :rows="5" responsiveLayout="scroll">
            <Column field="numero" header="N°" style="min-width: 8rem">
              <template #body="{ data }">
                <span class="font-bold">{{ data.numero }}</span>
              </template>
            </Column>
            <Column header="Entreprise" style="min-width: 10rem">
              <template #body="{ data }">
                {{ data.entreprise?.raison_sociale ?? '—' }}
              </template>
            </Column>
            <Column header="Montant TTC" style="min-width: 8rem">
              <template #body="{ data }">
                {{ formatDA(data.montant_ttc) }}
              </template>
            </Column>
            <Column header="Statut" style="min-width: 7rem">
              <template #body="{ data }">
                <Tag :value="statutPaiementLabel(data.statut_paiement)" :severity="statutPaiementSeverity(data.statut_paiement)" />
              </template>
            </Column>
            <Column header="Date" style="min-width: 7rem">
              <template #body="{ data }">
                {{ formatDate(data.date_facture) }}
              </template>
            </Column>
          </DataTable>
          <div v-if="stats.recentes.factures.length === 0" class="text-center text-muted-color py-6">
            Aucune facture pour le moment.
          </div>
        </div>
      </div>

      <!-- Missions récentes -->
      <div class="col-span-12">
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div class="text-surface-900 dark:text-surface-0 font-bold text-lg">Dernières missions</div>
            <router-link to="/missions" class="text-primary font-medium no-underline hover:underline text-sm">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1"></i>
            </router-link>
          </div>
          <DataTable :value="stats.recentes.missions" :rows="5" responsiveLayout="scroll">
            <Column field="reference" header="Référence" style="min-width: 8rem">
              <template #body="{ data }">
                <router-link :to="`/missions/${data.id}`" class="text-primary font-bold no-underline hover:underline">
                  {{ data.reference }}
                </router-link>
              </template>
            </Column>
            <Column header="Entreprise" style="min-width: 10rem">
              <template #body="{ data }">
                {{ data.entreprise?.raison_sociale ?? '—' }}
              </template>
            </Column>
            <Column header="Prestation" style="min-width: 10rem">
              <template #body="{ data }">
                {{ data.prestation?.libelle ?? '—' }}
              </template>
            </Column>
            <Column header="Prix HT" style="min-width: 8rem">
              <template #body="{ data }">
                {{ formatDA(data.prix_ht) }}
              </template>
            </Column>
            <Column header="Statut" style="min-width: 7rem">
              <template #body="{ data }">
                <Tag :value="statutMissionLabel(data.statut)" :severity="statutMissionSeverity(data.statut)" />
              </template>
            </Column>
            <Column header="Début" style="min-width: 7rem">
              <template #body="{ data }">
                {{ data.date_debut ? formatDate(data.date_debut) : '—' }}
              </template>
            </Column>
          </DataTable>
          <div v-if="stats.recentes.missions.length === 0" class="text-center text-muted-color py-6">
            Aucune mission pour le moment.
          </div>
        </div>
      </div>
    </template>
  </div>
  </section>
</template>

<style scoped>
/* ── Charts (Chart.js) ────────────────────────────────────────────────── */
.chart-box {
  position: relative;
  height: 18rem;
}

/* ── Recouvrement bar (admin) ─────────────────────────────────────────── */
.recouvrement-bar {
  height: 6px;
  background: var(--p-surface-border);
  border-radius: 3px;
  overflow: hidden;
}

.recouvrement-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease;
}

.fill-success {
  background: var(--p-green-500, #22c55e);
}

.fill-warn {
  background: var(--p-amber-500, #f59e0b);
}

/* ── Hero Banner ──────────────────────────────────────────────────────── */
/* === Ledger header (sober, flat, data-dense) ============================ */
.hero-banner {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding: 1.5rem 1.75rem;
  border-radius: var(--ledge-radius-card);
  box-shadow: var(--ledge-shadow-card);
  background: var(--p-surface-0);
  border: 1px solid var(--p-surface-200);
  border-top: 3px solid var(--ledge-accent);
  color: var(--p-text-color);
  position: relative;
}

.app-dark .hero-banner {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}

.hero-banner__left {
  flex: 1;
  min-width: 0;
}

.hero-today {
  font-family: var(--ledge-ff-mono);
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--p-text-muted-color);
  margin: 0 0 0.4rem;
}

.hero-today::before {
  content: '§ ';
  color: var(--ledge-accent);
  font-family: var(--ledge-ff-display);
  font-style: italic;
  font-weight: 500;
}

.hero-greeting {
  font-family: var(--ledge-ff-display);
  font-weight: 400;
  font-size: 1.85rem;
  line-height: 1.1;
  letter-spacing: -0.01em;
  margin: 0 0 1rem;
  color: var(--p-text-color);
  font-variation-settings: 'opsz' 144, 'SOFT' 30;
}

.hero-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
  border-top: 1px solid var(--p-surface-200);
  padding-top: 0.85rem;
}

.app-dark .hero-summary {
  border-top-color: var(--p-surface-700);
}

.hero-chip {
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
  background: transparent;
  border-radius: 0;
}

.hero-chip:last-child {
  border-right: none;
  margin-right: 0;
  padding-right: 0;
}

.hero-chip i {
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
}

.app-dark .hero-chip {
  border-right-color: var(--p-surface-700);
}

.hero-chip--success { color: var(--ledge-success); }
.hero-chip--success i { color: var(--ledge-success); }
.hero-chip--warn    { color: var(--ledge-warning); }
.hero-chip--warn i  { color: var(--ledge-warning); }

.hero-alert {
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

.hero-alert--ok {
  background: color-mix(in srgb, var(--ledge-success), transparent 92%);
  border-color: color-mix(in srgb, var(--ledge-success), transparent 75%);
  border-left-color: var(--ledge-success);
  color: var(--ledge-success);
}

.hero-alert__title {
  font-family: var(--ledge-ff-mono);
  font-weight: 600;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin: 0;
}

.hero-alert__msg {
  font-size: 0.85rem;
  font-weight: 500;
  margin: 0.2rem 0 0;
  color: var(--p-text-color);
}

.hero-alert__icon {
  font-size: 1.4rem;
  flex-shrink: 0;
}

/* ── KPI Cards ────────────────────────────────────────────────────────── */
/* background/border/padding/radius gérés par .card (dark mode natif PrimeVue) */
.kpi-card {
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.kpi-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  border-radius: var(--ledge-radius-card) var(--ledge-radius-card) 0 0;
}
.kpi-card:hover {
  box-shadow: var(--ledge-shadow-card-hover);
  transform: translateY(-2px);
}
.kpi-card--blue::before   { background: #3b82f6; }
.kpi-card--orange::before { background: #f59e0b; }
.kpi-card--green::before  { background: #22c55e; }
.kpi-card--red::before    { background: #ef4444; }

.kpi-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.kpi-card__label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
  margin: 0;
}
.kpi-card__icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.125rem;
  flex-shrink: 0;
}
.kpi-card--blue .kpi-card__icon   { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.kpi-card--orange .kpi-card__icon { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.kpi-card--green .kpi-card__icon  { background: rgba(34, 197, 94, 0.12);  color: #22c55e; }
.kpi-card--red .kpi-card__icon    { background: rgba(239, 68, 68, 0.12);  color: #ef4444; }

.kpi-card__value {
  font-size: 2.75rem;
  font-weight: 700;
  color: var(--p-text-color);
  line-height: 1;
  margin: 0 0 1rem;
}
.kpi-card__sub {
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
  margin: 0.5rem 0 0;
}
.kpi-dot {
  color: var(--p-text-muted-color);
  font-weight: 400;
}
/* Pills used only in panel headers, not KPI cards */
.kpi-pill {
  display: inline-flex;
  align-items: center;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.625rem;
  border-radius: 999px;
}
.kpi-pill--blue   { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.kpi-pill--green  { background: rgba(34, 197, 94, 0.12);  color: #22c55e; }
.kpi-pill--orange { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.kpi-pill--muted  { background: var(--p-surface-border);  color: var(--p-text-muted-color); }

/* KPI progress bar */
.kpi-progress-bar {
  height: 6px;
  background: var(--p-surface-border);
  border-radius: 999px;
  overflow: hidden;
  margin: 0 0 0.25rem;
}
.kpi-progress-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}
.kpi-progress-fill--green  { background: #22c55e; }
.kpi-progress-fill--orange { background: #f59e0b; }

/* ── Panel header ─────────────────────────────────────────────────────── */
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
.panel-link {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--p-primary-color);
  text-decoration: none;
  cursor: pointer;
  border-radius: 4px;
}
.panel-link:hover { text-decoration: underline; }
.panel-link:focus-visible { outline: 2px solid var(--p-primary-color); outline-offset: 2px; }

/* ── Empty state ──────────────────────────────────────────────────────── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
  text-align: center;
}

/* ── Mission list ─────────────────────────────────────────────────────── */
.mission-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.mission-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 0.5rem;
  border-radius: 8px;
  transition: background 0.15s ease;
  cursor: pointer;
}
.mission-item:hover { background: rgba(128, 128, 128, 0.06); }
.mission-item + .mission-item { border-top: 1px solid var(--p-surface-border); }
.mission-item__main {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}
.mission-ref {
  font-weight: 700;
  font-size: 0.9375rem;
  color: var(--p-primary-color);
  text-decoration: none;
  white-space: nowrap;
}
.mission-ref:hover { text-decoration: underline; }
.mission-ref:focus-visible { outline: 2px solid var(--p-primary-color); outline-offset: 2px; border-radius: 3px; }
.mission-client {
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 18ch;
}
.mission-item__right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.375rem;
  flex-shrink: 0;
  min-width: 130px;
}
.mission-progress {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
}
.mission-progress__bar {
  flex: 1;
  height: 5px;
  background: var(--p-surface-border);
  border-radius: 999px;
  overflow: hidden;
}
.mission-progress__fill {
  height: 100%;
  background: var(--p-primary-color);
  border-radius: 999px;
  transition: width 0.4s ease;
}
.mission-progress__pct {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  min-width: 2.25rem;
  text-align: right;
}

/* ── Task list ────────────────────────────────────────────────────────── */
.task-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.task-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 0.5rem 0.875rem 0.75rem;
  border-radius: 8px;
  border-left: 3px solid transparent;
  transition: background 0.15s ease;
}
.task-item:hover { background: rgba(128, 128, 128, 0.06); }
.task-item + .task-item { border-top: 1px solid var(--p-surface-border); }
.task-item--retard   { border-left-color: #ef4444; }
.task-item--en-cours { border-left-color: #3b82f6; }
.task-item__icon {
  margin-top: 2px;
  flex-shrink: 0;
  font-size: 0.9375rem;
  width: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
}
.task-item__body {
  flex: 1;
  min-width: 0;
}
.task-title {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--p-text-color);
  margin: 0 0 0.2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.task-meta {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  margin: 0;
}
.task-mission-link { color: var(--p-primary-color); text-decoration: none; }
.task-mission-link:hover { text-decoration: underline; }
.task-mission-link:focus-visible { outline: 2px solid var(--p-primary-color); outline-offset: 2px; border-radius: 2px; }
.task-item__end {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.3rem;
  flex-shrink: 0;
}
.task-badges {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}
.task-date { font-size: 0.75rem; color: var(--p-text-muted-color); }
.task-date--retard { color: #ef4444; font-weight: 600; }

/* ── Donut SVG Chart ──────────────────────────────────────────────────── */
.donut-wrap {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
  padding-top: 0.5rem;
}
.donut-svg-wrap {
  position: relative;
  width: 110px;
  height: 110px;
  flex-shrink: 0;
}
.donut-svg {
  width: 110px;
  height: 110px;
  transform: rotate(-90deg);
}
.donut-track {
  fill: none;
  stroke: var(--p-surface-border);
  stroke-width: 12;
}
.donut-seg {
  fill: none;
  stroke-width: 12;
  stroke-linecap: butt;
  transition: stroke-dashoffset 0.5s ease;
}
.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.donut-total {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--p-text-color);
  line-height: 1;
}
.donut-sub {
  font-size: 0.6875rem;
  color: var(--p-text-muted-color);
  margin-top: 2px;
}
.chart-legend {
  list-style: none;
  padding: 0;
  margin: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.575rem;
  min-width: 100px;
}
.chart-legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.chart-legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.chart-legend-label {
  flex: 1;
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}
.chart-legend-val {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--p-text-color);
}
.chart-legend-pct {
  font-size: 0.6875rem;
  color: var(--p-text-muted-color);
  margin-left: 0.2rem;
}
.chart-legend-item--zero {
  opacity: 0.4;
}
.chart-legend-item--zero .chart-legend-dot {
  background: var(--p-surface-border) !important;
}

/* ── Bar Chart (missions) ─────────────────────────────────────────────── */
.bar-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}
.bar-item {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.bar-item__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.bar-item__right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}
.bar-pct {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--p-text-color);
  min-width: 2.25rem;
  text-align: right;
}
.bar-track {
  height: 8px;
  background: var(--p-surface-border);
  border-radius: 999px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}
.bar-fill--green { background: #22c55e; }
.bar-fill--blue  { background: var(--p-primary-color); }
.bar-fill--empty { background: var(--p-surface-border); width: 0 !important; }
.bar-client {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Timeline des échéances ───────────────────────────────────────────── */
.timeline-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem 0.5rem;
  border-radius: 8px;
  transition: background 0.15s ease;
  border-left: 2px solid transparent;
}
.timeline-item + .timeline-item { border-top: 1px solid var(--p-surface-border); }
.timeline-item:hover { background: rgba(128, 128, 128, 0.06); }
.timeline-item--retard { border-left-color: #ef4444; }
.timeline-item--urgent { border-left-color: #f59e0b; }

.timeline-icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 6px;
  background: rgba(128, 128, 128, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  flex-shrink: 0;
  margin-top: 1px;
}
.timeline-item--retard .timeline-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.timeline-item--urgent .timeline-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.timeline-body {
  flex: 1;
  min-width: 0;
}
.timeline-title {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--p-text-color);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}
a.timeline-title { color: var(--p-primary-color); }
a.timeline-title:hover { text-decoration: underline; }
a.timeline-title:focus-visible { outline: 2px solid var(--p-primary-color); outline-offset: 2px; border-radius: 2px; }
.timeline-meta {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  margin: 0.1rem 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.timeline-date {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.2rem;
  flex-shrink: 0;
}
.timeline-badge {
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}
.timeline-badge--retard { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.timeline-badge--urgent { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.timeline-badge--ok     { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.timeline-dt {
  font-size: 0.6875rem;
  color: var(--p-text-muted-color);
}

/* ── Activité récente ─────────────────────────────────────────────────── */
.activity-grid {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 0.75rem;
}
.activity-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem;
  border-radius: 8px;
  background: rgba(128, 128, 128, 0.04);
  border: 1px solid var(--p-surface-border);
  transition: background 0.15s ease;
}
.activity-item:hover { background: rgba(128, 128, 128, 0.08); }

.activity-icon {
  width: 2rem;
  height: 2rem;
  border-radius: 6px;
  background: rgba(34, 197, 94, 0.12);
  color: #22c55e;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.activity-body {
  flex: 1;
  min-width: 0;
}
.activity-title {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--p-text-color);
  margin: 0 0 0.2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.activity-meta {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  margin: 0;
}
.activity-date {
  font-size: 0.6875rem;
  color: var(--p-text-muted-color);
  flex-shrink: 0;
  white-space: nowrap;
}

/* ── Reduced motion ───────────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  .kpi-card { transition: none; }
  .kpi-ring__fill { transition: none; }
  .donut-seg { transition: none; }
  .bar-fill { transition: none; }
  .mission-progress__fill { transition: none; }
  .mission-item, .task-item, .timeline-item, .activity-item { transition: none; }
}
</style>
