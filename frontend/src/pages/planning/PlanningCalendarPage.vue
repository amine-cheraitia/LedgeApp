<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import frLocale from '@fullcalendar/core/locales/fr'
import type { CalendarOptions, EventClickArg } from '@fullcalendar/core'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { usePlanning } from '@/composables/usePlanning'
import type { CalendarMission, CalendarTache } from '@/api/modules/planning'

const router = useRouter()
const toast  = useToast()

const {
  collaborateurFilter, collaborateurs, loadingCollab, fetchCollaborateurs,
  filteredEvents, loadingEvents, loadEvents,
  activeStatuts, showTaches, toggleStatut, toggleTaches,
  missionCountByStatut, prestationsVues, prestationColor, STATUS_BORDER, TACHE_COLORS,
  changerStatutMission, onEventDrop, onEventResize,
} = usePlanning()

// ── Calendrier ────────────────────────────────────────────────────────────────

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null)

// Options stables — les events sont gérés via l'API FullCalendar (watch ci-dessous)
const calendarOptions: CalendarOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
  locale: frLocale,
  initialView: 'dayGridMonth',
  headerToolbar: {
    left:   'prev,next today',
    center: 'title',
    right:  'dayGridMonth,timeGridWeek,listWeek',
  },
  buttonText: {
    today:  "Aujourd'hui",
    month:  'Mois',
    week:   'Semaine',
    list:   'Liste',
  },
  events:      [],
  datesSet:    (info) => loadEvents(info.startStr.slice(0, 10), info.endStr.slice(0, 10)),
  editable:    true,
  droppable:   true,
  eventDrop:   onEventDrop,
  eventResize: onEventResize,
  eventClick:  openDetail,
  height: 'auto',
}

// Synchroniser FullCalendar dès que les events filtrés changent
watch(filteredEvents, (events) => {
  const api = calendarRef.value?.getApi()
  if (!api) return
  api.removeAllEvents()
  api.addEventSource(events)
}, { deep: true })

// Re-charger quand le filtre collaborateur change
watch(collaborateurFilter, () => {
  const api = calendarRef.value?.getApi()
  if (!api) return
  const { activeStart, activeEnd } = api.view
  loadEvents(activeStart.toISOString().slice(0, 10), activeEnd.toISOString().slice(0, 10))
})

// ── Dialog détail / action ────────────────────────────────────────────────────

const dialogVisible  = ref(false)
const selectedEvent  = ref<CalendarMission | CalendarTache | null>(null)
const pendingStatut  = ref('')
const savingStatut   = ref(false)

function openDetail(info: EventClickArg) {
  selectedEvent.value = info.event.extendedProps as CalendarMission | CalendarTache
  if (isMission(selectedEvent.value)) {
    pendingStatut.value = selectedEvent.value.statut
  }
  dialogVisible.value = true
}

function isMission(e: CalendarMission | CalendarTache | null): e is CalendarMission {
  return e?.type === 'mission'
}

async function enregistrerStatut() {
  if (!selectedEvent.value || !isMission(selectedEvent.value)) return
  savingStatut.value = true
  try {
    await changerStatutMission(selectedEvent.value.id, pendingStatut.value)
    toast.add({ severity: 'success', summary: 'Statut mis à jour', life: 2000 })
    dialogVisible.value = false
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier le statut.', life: 3000 })
  } finally {
    savingStatut.value = false
  }
}

function voirFiche(id: number) {
  dialogVisible.value = false
  router.push(`/missions/${id}`)
}

function voirMission(missionId: number) {
  dialogVisible.value = false
  router.push(`/missions/${missionId}`)
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const STATUT_LABELS: Record<string, string> = {
  en_cours:  'En cours',
  terminee:  'Terminée',
  suspendue: 'Suspendue',
  annulee:   'Annulée',
  a_faire:   'À faire',
  bloquee:   'Bloquée',
  terminée:  'Terminée',
}

const STATUT_SEVERITY: Record<string, 'info' | 'success' | 'warn' | 'danger' | 'secondary'> = {
  en_cours:  'info',
  terminee:  'success',
  suspendue: 'warn',
  annulee:   'secondary',
  a_faire:   'info',
  bloquee:   'danger',
}

const MISSION_STATUTS = ['en_cours', 'suspendue', 'terminee', 'annulee'] as const

const statutOptions = MISSION_STATUTS.map(s => ({ label: STATUT_LABELS[s], value: s }))

function prioriteLabel(p: number): string {
  if (p <= 1) return 'Très faible'
  if (p === 2) return 'Faible'
  if (p === 3) return 'Moyenne'
  if (p === 4) return 'Haute'
  return 'Critique'
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

const statut_changed = computed(() =>
  isMission(selectedEvent.value) && pendingStatut.value !== selectedEvent.value?.statut
)

// Accès typé aux champs tâche (évite les `as` dans le template)
const selectedTache = computed<CalendarTache | null>(() =>
  selectedEvent.value?.type === 'tache' ? (selectedEvent.value as CalendarTache) : null
)

// Mini-stats header
const statsEnCours  = computed(() => missionCountByStatut.value['en_cours']  ?? 0)
const statsSuspendu = computed(() => missionCountByStatut.value['suspendue'] ?? 0)

// ── Init ──────────────────────────────────────────────────────────────────────
fetchCollaborateurs()
</script>

<template>
  <main class="planning-page" role="main" aria-labelledby="planning-title">

    <!-- En-tête -->
    <div class="page-header">
      <div class="header-left">
        <h1 id="planning-title" class="page-title">Planning</h1>
        <div class="header-stats" aria-label="Statistiques de la période">
          <span v-if="statsEnCours > 0" class="stat-chip stat-chip--blue">
            {{ statsEnCours }} en cours
          </span>
          <span v-if="statsSuspendu > 0" class="stat-chip stat-chip--orange">
            {{ statsSuspendu }} suspendue{{ statsSuspendu > 1 ? 's' : '' }}
          </span>
        </div>
      </div>
      <div class="header-right">
        <label for="filtre-collaborateur" class="sr-only">Filtrer par collaborateur</label>
        <Select
          id="filtre-collaborateur"
          v-model="collaborateurFilter"
          :options="collaborateurs"
          option-label="name"
          option-value="id"
          placeholder="Tous les collaborateurs"
          :loading="loadingCollab"
          show-clear
          aria-label="Filtrer par collaborateur"
          style="min-width: 220px"
        />
      </div>
    </div>

    <!-- Filtres statuts -->
    <div class="statut-filters" role="group" aria-label="Filtrer par statut de mission">
      <button
        v-for="statut in MISSION_STATUTS"
        :key="statut"
        class="statut-chip"
        :class="{ 'statut-chip--active': activeStatuts.has(statut) }"
        :style="activeStatuts.has(statut) ? { backgroundColor: STATUS_BORDER[statut], borderColor: STATUS_BORDER[statut] } : { borderColor: STATUS_BORDER[statut], color: STATUS_BORDER[statut] }"
        :aria-pressed="activeStatuts.has(statut)"
        :aria-label="`${activeStatuts.has(statut) ? 'Masquer' : 'Afficher'} les missions ${STATUT_LABELS[statut]?.toLowerCase()}`"
        @click="toggleStatut(statut)"
      >
        <span class="statut-chip__dot" :style="{ backgroundColor: activeStatuts.has(statut) ? '#fff' : STATUS_BORDER[statut] }" aria-hidden="true" />
        {{ STATUT_LABELS[statut] }}
        <span v-if="missionCountByStatut[statut]" class="statut-chip__count">
          {{ missionCountByStatut[statut] }}
        </span>
      </button>
      <button
        class="statut-chip"
        :class="{ 'statut-chip--active': showTaches }"
        :style="showTaches ? { backgroundColor: '#8B5CF6', borderColor: '#8B5CF6' } : { borderColor: '#8B5CF6', color: '#8B5CF6' }"
        :aria-pressed="showTaches"
        aria-label="Afficher ou masquer les tâches"
        @click="toggleTaches"
      >
        <span class="statut-chip__dot" :style="{ backgroundColor: showTaches ? '#fff' : '#8B5CF6' }" aria-hidden="true" />
        Tâches
      </button>
    </div>

    <!-- Calendrier -->
    <section class="calendar-wrapper" role="region" aria-label="Calendrier des missions et tâches">
      <div v-if="loadingEvents" class="calendar-loading" role="status" aria-live="polite">
        <i class="pi pi-spin pi-spinner" aria-hidden="true" style="font-size:1.5rem" />
        <span>Chargement…</span>
      </div>

      <FullCalendar ref="calendarRef" :options="calendarOptions" />

      <!-- Légende -->
      <div class="legend" aria-label="Légende">
        <div v-if="prestationsVues.length > 0" class="legend-section">
          <span class="legend-section-title">Type de mission</span>
          <div class="legend-items">
            <span v-for="p in prestationsVues" :key="p.id" class="legend-item">
              <span class="legend-dot" :style="{ background: prestationColor(p.id) }" aria-hidden="true" />
              {{ p.designation || p.code }}
            </span>
          </div>
        </div>
        <div class="legend-section">
          <span class="legend-section-title">Statut (bordure)</span>
          <div class="legend-items">
            <span v-for="(color, statut) in STATUS_BORDER" :key="statut" class="legend-item">
              <span class="legend-border-sample" :style="{ borderColor: color }" aria-hidden="true" />
              {{ STATUT_LABELS[statut] }}
            </span>
          </div>
        </div>
        <div class="legend-section">
          <span class="legend-section-title">Tâches</span>
          <div class="legend-items">
            <span class="legend-item">
              <span class="legend-dot" style="background:#0EA5E9" aria-hidden="true" />À faire
            </span>
            <span class="legend-item">
              <span class="legend-dot" style="background:#8B5CF6" aria-hidden="true" />En cours
            </span>
            <span class="legend-item">
              <span class="legend-dot" style="background:#EF4444" aria-hidden="true" />Bloquée
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- Dialog détail + action -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="selectedEvent ? (isMission(selectedEvent) ? selectedEvent.reference : 'Tâche') : ''"
      :modal="true"
      :draggable="false"
      :style="{ width: '500px', maxWidth: '95vw' }"
    >
      <template v-if="selectedEvent">

        <!-- ── Mission ── -->
        <template v-if="isMission(selectedEvent)">
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">Entreprise</span>
              <span class="detail-value">{{ selectedEvent.entreprise ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Prestation</span>
              <span class="detail-value">
                <span
                  class="prestation-badge"
                  :style="{ background: prestationColor(selectedEvent.prestation_id) + '22', color: prestationColor(selectedEvent.prestation_id), border: `1px solid ${prestationColor(selectedEvent.prestation_id)}44` }"
                >
                  {{ selectedEvent.prestation_code }} — {{ selectedEvent.prestation }}
                </span>
              </span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Période</span>
              <span class="detail-value">{{ formatDate(selectedEvent.date_debut) }} → {{ formatDate(selectedEvent.date_fin) }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Prix HT</span>
              <span class="detail-value">{{ selectedEvent.prix_ht.toLocaleString('fr-DZ') }} DA</span>
            </div>
            <div class="detail-row detail-row--statut">
              <span class="detail-label">Statut</span>
              <div class="statut-edit">
                <Select
                  v-model="pendingStatut"
                  :options="statutOptions"
                  option-label="label"
                  option-value="value"
                  aria-label="Changer le statut de la mission"
                  style="min-width: 160px"
                />
                <Button
                  v-if="statut_changed"
                  label="Enregistrer"
                  icon="pi pi-check"
                  size="small"
                  :loading="savingStatut"
                  @click="enregistrerStatut"
                  aria-label="Enregistrer le nouveau statut"
                />
              </div>
            </div>
          </div>
          <div class="dialog-actions">
            <Button
              label="Voir la fiche"
              icon="pi pi-arrow-right"
              iconPos="right"
              outlined
              @click="voirFiche(selectedEvent.id)"
            />
          </div>
        </template>

        <!-- ── Tâche ── -->
        <template v-else>
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">Titre</span>
              <span class="detail-value">{{ selectedEvent.titre }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Mission</span>
              <span class="detail-value">{{ selectedTache?.mission_ref ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Entreprise</span>
              <span class="detail-value">{{ selectedTache?.entreprise ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Statut</span>
              <Tag
                :value="STATUT_LABELS[selectedTache?.statut ?? ''] ?? selectedTache?.statut"
                :severity="STATUT_SEVERITY[selectedTache?.statut ?? ''] ?? 'info'"
              />
            </div>
            <div class="detail-row">
              <span class="detail-label">Échéance</span>
              <span class="detail-value">{{ formatDate(selectedTache?.date_echeance) }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Priorité</span>
              <span class="detail-value">{{ prioriteLabel(selectedTache?.priorite ?? 3) }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Assigné à</span>
              <span class="detail-value">{{ selectedTache?.assignee ?? '—' }}</span>
            </div>
          </div>
          <div class="dialog-actions">
            <Button
              label="Voir la mission"
              icon="pi pi-arrow-right"
              iconPos="right"
              outlined
              @click="voirMission(selectedTache?.mission_id ?? 0)"
            />
          </div>
        </template>
      </template>
    </Dialog>
  </main>
</template>

<style scoped>
.planning-page {
  padding: 1.5rem;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}
.header-stats {
  display: flex;
  gap: 0.5rem;
}
.stat-chip {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.6rem;
  border-radius: 9999px;
}
.stat-chip--blue   { background: #3B82F620; color: #3B82F6; }
.stat-chip--orange { background: #F59E0B20; color: #F59E0B; }

/* ── Filtres statuts ─────────────────────────────────────────────────────────── */
.statut-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.statut-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.75rem;
  border-radius: 9999px;
  border: 1.5px solid;
  background: transparent;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  color: inherit;
}
.statut-chip:hover { opacity: 0.85; }
.statut-chip--active { color: #fff !important; }
.statut-chip__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.statut-chip__count {
  background: rgba(255,255,255,0.25);
  border-radius: 9999px;
  padding: 0 0.35rem;
  font-size: 0.72rem;
  font-weight: 700;
}
.statut-chip--active .statut-chip__count {
  background: rgba(255,255,255,0.3);
}

/* ── Calendrier ─────────────────────────────────────────────────────────────── */
.calendar-wrapper {
  background: var(--p-surface-card);
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.calendar-loading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
  padding: 1rem;
  color: var(--p-text-muted-color);
  font-size: 0.88rem;
}

/* ── Légende ─────────────────────────────────────────────────────────────────── */
.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 2rem;
  margin-top: 1rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--p-surface-border);
}
.legend-section {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.legend-section-title {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--p-text-muted-color);
  font-weight: 600;
}
.legend-items {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 1rem;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  color: var(--p-text-muted-color);
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.legend-border-sample {
  display: inline-block;
  width: 16px;
  height: 0;
  border-top: 3px solid;
  flex-shrink: 0;
}

/* ── Dialog ─────────────────────────────────────────────────────────────────── */
.detail-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.detail-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.detail-row--statut {
  align-items: flex-start;
  padding-top: 0.25rem;
}
.detail-label {
  width: 90px;
  flex-shrink: 0;
  font-weight: 500;
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
}
.detail-value {
  font-size: 0.875rem;
}
.statut-edit {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.prestation-badge {
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
  white-space: nowrap;
}
.dialog-actions {
  margin-top: 1.25rem;
  display: flex;
  justify-content: flex-end;
}

/* ── RGAA ────────────────────────────────────────────────────────────────────── */
:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

/* ── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .planning-page { padding: 1rem; }
  .page-header { flex-direction: column; align-items: flex-start; }
  .legend { gap: 0.75rem 1rem; }
}
</style>
