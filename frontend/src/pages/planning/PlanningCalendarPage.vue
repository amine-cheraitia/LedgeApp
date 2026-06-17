<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import frLocale from '@fullcalendar/core/locales/fr'
import type { CalendarOptions, EventClickArg } from '@fullcalendar/core'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { usePlanning } from '@/composables/usePlanning'
import type { CalendarMission, CalendarTache } from '@/api/modules/planning'

const { collaborateurFilter, collaborateurs, loadingCollab, fetchCollaborateurs, fetchEvents, onEventDrop, onEventResize } =
  usePlanning()

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null)
const dialogVisible = ref(false)
const selectedEvent = ref<CalendarMission | CalendarTache | null>(null)

const STATUT_SEVERITY: Record<string, 'info' | 'success' | 'warn' | 'danger' | 'secondary'> = {
  en_cours: 'info',
  terminee: 'success',
  suspendue: 'warn',
  annulee: 'secondary',
  a_faire: 'info',
  bloquee: 'danger',
}

const STATUT_LABELS: Record<string, string> = {
  en_cours: 'En cours',
  terminee: 'Terminée',
  suspendue: 'Suspendue',
  annulee: 'Annulée',
  a_faire: 'À faire',
  bloquee: 'Bloquée',
}

const calendarOptions: CalendarOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
  locale: frLocale,
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
  },
  events: fetchEvents,
  editable: true,
  droppable: true,
  eventDrop: onEventDrop,
  eventResize: onEventResize,
  eventClick: (info: EventClickArg) => {
    selectedEvent.value = info.event.extendedProps as CalendarMission | CalendarTache
    dialogVisible.value = true
  },
  height: 'auto',
  buttonText: {
    today: "Aujourd'hui",
    month: 'Mois',
    week: 'Semaine',
    day: 'Jour',
    list: 'Liste',
  },
}

function refreshCalendar() {
  calendarRef.value?.getApi().refetchEvents()
}

watch(collaborateurFilter, () => {
  refreshCalendar()
})

onMounted(() => {
  fetchCollaborateurs()
})

function isMission(event: CalendarMission | CalendarTache | null): event is CalendarMission {
  return event?.type === 'mission'
}
</script>

<template>
  <main class="planning-page" role="main" aria-labelledby="planning-title">
    <div class="page-header">
      <h1 id="planning-title" class="page-title">Planning</h1>
      <div class="page-actions">
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
          class="filtre-collab"
        />
      </div>
    </div>

    <section
      class="calendar-wrapper"
      role="region"
      aria-label="Calendrier des missions et tâches"
    >
      <div class="legend" aria-label="Légende des couleurs">
        <span class="legend-item">
          <span class="legend-dot" style="background:#3B82F6" aria-hidden="true"></span>Mission en cours
        </span>
        <span class="legend-item">
          <span class="legend-dot" style="background:#22C55E" aria-hidden="true"></span>Terminée
        </span>
        <span class="legend-item">
          <span class="legend-dot" style="background:#F59E0B" aria-hidden="true"></span>Suspendue
        </span>
        <span class="legend-item">
          <span class="legend-dot" style="background:#6B7280" aria-hidden="true"></span>Annulée
        </span>
        <span class="legend-item">
          <span class="legend-dot" style="background:#0EA5E9" aria-hidden="true"></span>Tâche à faire
        </span>
        <span class="legend-item">
          <span class="legend-dot" style="background:#8B5CF6" aria-hidden="true"></span>Tâche en cours
        </span>
        <span class="legend-item">
          <span class="legend-dot" style="background:#EF4444" aria-hidden="true"></span>Tâche bloquée
        </span>
      </div>

      <FullCalendar ref="calendarRef" :options="calendarOptions" />
    </section>

    <!-- Dialog détail événement -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="selectedEvent ? (isMission(selectedEvent) ? 'Mission — ' + selectedEvent.reference : 'Tâche') : ''"
      :modal="true"
      :draggable="false"
      :style="{ width: '480px' }"
      aria-live="polite"
    >
      <template v-if="selectedEvent">
        <!-- Mission -->
        <template v-if="isMission(selectedEvent)">
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">Référence</span>
              <span class="detail-value">{{ selectedEvent.reference }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Entreprise</span>
              <span class="detail-value">{{ selectedEvent.entreprise ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Prestation</span>
              <span class="detail-value">{{ selectedEvent.prestation ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Statut</span>
              <Tag
                :value="STATUT_LABELS[selectedEvent.statut] ?? selectedEvent.statut"
                :severity="STATUT_SEVERITY[selectedEvent.statut] ?? 'info'"
              />
            </div>
            <div class="detail-row">
              <span class="detail-label">Début</span>
              <span class="detail-value">{{ selectedEvent.date_debut }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Fin</span>
              <span class="detail-value">{{ selectedEvent.date_fin ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Prix HT</span>
              <span class="detail-value">{{ selectedEvent.prix_ht.toLocaleString('fr-DZ') }} DA</span>
            </div>
          </div>
        </template>

        <!-- Tâche -->
        <template v-else>
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">Titre</span>
              <span class="detail-value">{{ selectedEvent.titre }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Mission</span>
              <span class="detail-value">{{ (selectedEvent as CalendarTache).mission_ref ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Entreprise</span>
              <span class="detail-value">{{ selectedEvent.entreprise ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Statut</span>
              <Tag
                :value="STATUT_LABELS[selectedEvent.statut] ?? selectedEvent.statut"
                :severity="STATUT_SEVERITY[selectedEvent.statut] ?? 'info'"
              />
            </div>
            <div class="detail-row">
              <span class="detail-label">Échéance</span>
              <span class="detail-value">{{ (selectedEvent as CalendarTache).date_echeance }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Priorité</span>
              <span class="detail-value">{{ (selectedEvent as CalendarTache).priorite }} / 5</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Assigné à</span>
              <span class="detail-value">{{ (selectedEvent as CalendarTache).assignee ?? '—' }}</span>
            </div>
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

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.filtre-collab {
  min-width: 220px;
}

.calendar-wrapper {
  background: var(--p-surface-0);
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  margin-bottom: 1rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

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

.detail-label {
  width: 110px;
  flex-shrink: 0;
  font-weight: 500;
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
}

.detail-value {
  font-size: 0.875rem;
}

:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

@media (max-width: 768px) {
  .planning-page {
    padding: 1rem;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .filtre-collab {
    width: 100%;
  }

  .legend {
    gap: 0.5rem 1rem;
  }
}
</style>
