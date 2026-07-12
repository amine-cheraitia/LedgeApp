<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import multiMonthPlugin from '@fullcalendar/multimonth'
import frLocale from '@fullcalendar/core/locales/fr'
import type { CalendarOptions, EventClickArg } from '@fullcalendar/core'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import { useToast } from 'primevue/usetoast'
import { usePlanning } from '@/composables/usePlanning'
import { useAuthStore } from '@/stores/authStore'
import type { CalendarMission, CalendarTache } from '@/api/modules/planning'
import { prioriteLabel, PRIORITE_COLORS } from '@/utils/priorite'

const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const {
  collaborateurFilter, collaborateurs, fetchCollaborateurs,
  filteredEvents, loadingEvents, loadEvents,
  activeStatuts, toggleStatut,
  missionCountByStatut, prestationsVues, prestationColor, STATUS_BORDER,
  changerStatutMission, onEventDrop, onEventResize,
  teamWeekStart, teamWeekDays, teamGridData, loadingEquipe,
  initWeek, prevWeek, nextWeek, loadEquipeWeek, chargeColor, chargeLabel,
} = usePlanning()

// ── Calendrier ────────────────────────────────────────────────────────────────

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null)

// Options stables — les events sont gérés via l'API FullCalendar (watch ci-dessous)
const calendarOptions: CalendarOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin, multiMonthPlugin],
  locale: frLocale,
  initialView: 'dayGridMonth',
  headerToolbar: {
    left:   'prev,next today',
    center: 'title',
    right:  'multiMonthYear,dayGridMonth,timeGridWeek,listMonth',
  },
  buttonText: {
    today: "Aujourd'hui",
    year:  'Année',
    month: 'Mois',
    week:  'Semaine',
  },
  // La 4e vue « Liste » s'appuie sur listMonth (agenda du mois courant).
  views: {
    listMonth: { buttonText: 'Liste' },
  },
  events:      [],
  datesSet:    (info) => loadEvents(info.startStr.slice(0, 10), info.endStr.slice(0, 10)),
  // Le collaborateur consulte ses tâches en lecture seule (le backend ignore ses dates).
  editable:    auth.isAdmin,
  droppable:   auth.isAdmin,
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

// Ouvre le même modal que le calendrier depuis une chip de la vue Équipe.
function openTacheModal(tache: CalendarTache) {
  selectedEvent.value = tache
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

// ── Onglet Équipe ─────────────────────────────────────────────────────────────

function onEquipeTabActivated() {
  if (!teamWeekStart.value) {
    initWeek()
  } else {
    loadEquipeWeek()
  }
}

const JOURS_FR = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

function formatDayName(iso: string): string {
  const d = new Date(iso)
  return JOURS_FR[(d.getDay() + 6) % 7]
}

function formatDateShort(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

function isToday(iso: string): boolean {
  return iso === new Date().toISOString().slice(0, 10)
}

// ── Init ──────────────────────────────────────────────────────────────────────
// La liste des collaborateurs ne sert qu'à l'admin (vue Équipe).
if (!auth.isCollaborateur) fetchCollaborateurs()
</script>

<template>
  <main class="planning-page" role="main" aria-labelledby="planning-title">
    <h1 id="planning-title" class="page-title">Planning</h1>

    <!-- ══ ADMIN : onglets Missions + Équipe ══ -->
    <Tabs v-if="auth.isAdmin" value="missions">
      <TabList>
        <Tab value="missions">
          <i class="pi pi-calendar" aria-hidden="true" style="margin-right:0.4rem" />
          Missions
        </Tab>
        <Tab value="equipe" @click="onEquipeTabActivated">
          <i class="pi pi-users" aria-hidden="true" style="margin-right:0.4rem" />
          Équipe
        </Tab>
      </TabList>

      <TabPanels>

        <!-- ══════════════════ ONGLET MISSIONS ══════════════════ -->
        <TabPanel value="missions">

          <!-- Barre de la vue missions -->
          <div class="missions-toolbar">
            <div class="missions-stats" aria-label="Statistiques de la période">
              <span v-if="statsEnCours > 0" class="stat-chip stat-chip--blue">
                {{ statsEnCours }} en cours
              </span>
              <span v-if="statsSuspendu > 0" class="stat-chip stat-chip--orange">
                {{ statsSuspendu }} suspendue{{ statsSuspendu > 1 ? 's' : '' }}
              </span>
            </div>
            <Button
              v-if="auth.isAdmin"
              label="Nouvelle mission"
              icon="pi pi-plus"
              aria-label="Créer une nouvelle mission"
              @click="router.push('/missions')"
            />
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
          </div>

          <!-- Calendrier missions -->
          <section class="calendar-wrapper" role="region" aria-label="Calendrier des missions">
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
            </div>
          </section>
        </TabPanel>

        <!-- ══════════════════ ONGLET ÉQUIPE ══════════════════ -->
        <TabPanel value="equipe">
          <div class="equipe-container">

            <!-- Navigation semaine -->
            <div class="equipe-toolbar">
              <Button
                icon="pi pi-chevron-left"
                text
                rounded
                :aria-label="'Semaine précédente'"
                @click="prevWeek"
              />
              <span class="semaine-label">
                Semaine du {{ formatDate(teamWeekStart) }}
              </span>
              <Button
                icon="pi pi-chevron-right"
                text
                rounded
                :aria-label="'Semaine suivante'"
                @click="nextWeek"
              />
            </div>

            <!-- Chargement -->
            <div v-if="loadingEquipe" class="equipe-loading" role="status" aria-live="polite">
              <i class="pi pi-spin pi-spinner" aria-hidden="true" style="font-size:1.5rem" />
              <span>Chargement…</span>
            </div>

            <!-- Aucun collaborateur -->
            <div v-else-if="collaborateurs.length === 0" class="equipe-empty">
              <i class="pi pi-users" aria-hidden="true" style="font-size:2rem;opacity:0.3" />
              <p>Aucun collaborateur trouvé.</p>
            </div>

            <!-- Grille disponibilité -->
            <div
              v-else
              class="equipe-grid"
              role="table"
              aria-label="Disponibilité de l'équipe cette semaine"
              style="overflow-x: auto"
            >
              <!-- En-tête : jours -->
              <div class="equipe-row equipe-row--header" role="row">
                <div class="equipe-cell equipe-cell--name" role="columnheader">Collaborateur</div>
                <div
                  v-for="day in teamWeekDays"
                  :key="day"
                  class="equipe-cell equipe-cell--day-header"
                  :class="{ 'equipe-cell--today': isToday(day) }"
                  role="columnheader"
                >
                  <span class="day-name">{{ formatDayName(day) }}</span>
                  <span class="day-date">{{ formatDateShort(day) }}</span>
                </div>
              </div>

              <!-- Lignes : collaborateurs -->
              <div
                v-for="collab in collaborateurs"
                :key="collab.id"
                class="equipe-row"
                role="row"
              >
                <!-- Nom du collaborateur -->
                <div class="equipe-cell equipe-cell--name" role="rowheader">
                  <span class="collab-avatar" aria-hidden="true">{{ collab.name.charAt(0).toUpperCase() }}</span>
                  <span class="collab-name">{{ collab.name }}</span>
                </div>

                <!-- Cellule par jour -->
                <div
                  v-for="day in teamWeekDays"
                  :key="day"
                  class="equipe-cell equipe-cell--tasks"
                  :class="{
                    'equipe-cell--today': isToday(day),
                    'equipe-cell--dispo': !(teamGridData[collab.id]?.[day]?.length),
                  }"
                  role="cell"
                  :aria-label="`${collab.name}, ${formatDayName(day)} ${formatDateShort(day)} : ${(teamGridData[collab.id]?.[day] ?? []).length === 0 ? 'Disponible' : (teamGridData[collab.id]?.[day] ?? []).length + ' tâche(s)'}`"
                >
                  <div
                    class="charge-bar"
                    :style="{ backgroundColor: chargeColor((teamGridData[collab.id]?.[day] ?? []).length) }"
                    :title="chargeLabel((teamGridData[collab.id]?.[day] ?? []).length)"
                    aria-hidden="true"
                  />
                  <template v-if="(teamGridData[collab.id]?.[day] ?? []).length > 0">
                    <button
                      v-for="tache in teamGridData[collab.id][day]"
                      :key="tache.id"
                      type="button"
                      class="tache-chip"
                      :class="`tache-chip--${tache.statut}`"
                      :title="`${tache.titre} — ${tache.mission_ref ?? ''}`"
                      :aria-label="`Voir la tâche ${tache.titre}`"
                      @click="openTacheModal(tache)"
                    >
                      {{ tache.titre }}
                    </button>
                  </template>
                  <span v-else class="dispo-label">Dispo</span>
                </div>
              </div>
            </div>

            <!-- Légende charge -->
            <div class="charge-legend" aria-label="Légende de la charge">
              <span class="charge-legend-title">Charge :</span>
              <span class="charge-legend-item">
                <span class="charge-dot" style="background:var(--p-green-500)" aria-hidden="true" />Disponible
              </span>
              <span class="charge-legend-item">
                <span class="charge-dot" style="background:var(--p-orange-400)" aria-hidden="true" />1–2 tâches
              </span>
              <span class="charge-legend-item">
                <span class="charge-dot" style="background:var(--p-red-500)" aria-hidden="true" />3+ tâches
              </span>
            </div>
          </div>
        </TabPanel>

      </TabPanels>
    </Tabs>

    <!-- ══ COLLABORATEUR : calendrier de SES tâches uniquement ══ -->
    <template v-else>
      <p class="collab-subtitle">Mes tâches</p>
      <section class="calendar-wrapper" role="region" aria-label="Calendrier de mes tâches">
        <div v-if="loadingEvents" class="calendar-loading" role="status" aria-live="polite">
          <i class="pi pi-spin pi-spinner" aria-hidden="true" style="font-size:1.5rem" />
          <span>Chargement…</span>
        </div>
        <FullCalendar ref="calendarRef" :options="calendarOptions" />
        <!-- Légende : couleur par priorité -->
        <div class="legend" aria-label="Légende des priorités">
          <div class="legend-section">
            <span class="legend-section-title">Priorité</span>
            <div class="legend-items">
              <span v-for="(color, prio) in PRIORITE_COLORS" :key="prio" class="legend-item">
                <span class="legend-dot" :style="{ background: color }" aria-hidden="true" />
                {{ prioriteLabel(Number(prio)) }}
              </span>
            </div>
          </div>
        </div>
      </section>
    </template>

    <!-- Dialog détail mission/tâche (depuis calendrier Missions) -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="selectedEvent ? (isMission(selectedEvent) ? selectedEvent.reference : 'Tâche') : ''"
      :modal="true"
      :draggable="false"
      :style="{ width: '500px', maxWidth: '95vw' }"
    >
      <template v-if="selectedEvent">
        <!-- Mission -->
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
            <Button label="Voir la fiche" icon="pi pi-arrow-right" iconPos="right" outlined @click="voirFiche(selectedEvent.id)" />
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
              <span class="detail-value">{{ selectedTache?.mission_ref ?? '—' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Statut</span>
              <Tag :value="STATUT_LABELS[selectedTache?.statut ?? ''] ?? selectedTache?.statut" :severity="STATUT_SEVERITY[selectedTache?.statut ?? ''] ?? 'info'" />
            </div>
            <div class="detail-row">
              <span class="detail-label">Début</span>
              <span class="detail-value">{{ formatDate(selectedTache?.date_debut) }}</span>
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
            <Button label="Voir la mission" icon="pi pi-arrow-right" iconPos="right" outlined @click="voirMission(selectedTache?.mission_id ?? 0)" />
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
.collab-subtitle {
  margin: 0.25rem 0 1rem;
  color: var(--p-text-muted-color);
  font-size: 0.95rem;
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
  position: relative;
}
/* 7 vues à droite : autoriser le retour à la ligne sur petits écrans (mobile-first). */
.calendar-wrapper :deep(.fc-toolbar) {
  flex-wrap: wrap;
  gap: 0.5rem;
}
.calendar-loading {
  position: absolute;
  inset: 0;
  z-index: 10;
  background: color-mix(in srgb, var(--p-surface-card) 70%, transparent);
  border-radius: 8px;
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

/* ── Missions tab toolbar ───────────────────────────────────────────────────── */
.missions-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding-top: 0.75rem;
  margin-bottom: 0.75rem;
}
.missions-stats { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }

/* ── Chargement équipe (non superposé — la grille n'est pas encore là) ──────── */
.equipe-loading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
  padding: 2rem;
  color: var(--p-text-muted-color);
  font-size: 0.88rem;
}

/* ── Vue Équipe ──────────────────────────────────────────────────────────────── */
.equipe-container {
  padding-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.equipe-toolbar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
}
.semaine-label {
  font-size: 0.95rem;
  font-weight: 600;
  min-width: 200px;
  text-align: center;
}
.equipe-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 3rem;
  color: var(--p-text-muted-color);
}

/* Grille */
.equipe-grid {
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  overflow: hidden;
  min-width: 600px;
}
.equipe-row {
  display: grid;
  grid-template-columns: 160px repeat(7, 1fr);
  border-bottom: 1px solid var(--p-surface-border);
}
.equipe-row:last-child { border-bottom: none; }
.equipe-row--header {
  background: var(--p-surface-ground);
  font-size: 0.78rem;
  font-weight: 600;
}
.equipe-cell {
  padding: 0.5rem 0.4rem;
  border-right: 1px solid var(--p-surface-border);
  min-height: 60px;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  position: relative;
}
.equipe-cell:last-child { border-right: none; }
.equipe-cell--name {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.4rem;
  min-height: auto;
  padding: 0.5rem;
  font-size: 0.82rem;
  font-weight: 500;
  background: var(--p-surface-ground);
  border-right: 2px solid var(--p-surface-border);
  word-break: break-word;
}
.equipe-cell--day-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  gap: 0.1rem;
  text-align: center;
}
.equipe-cell--today {
  background: var(--p-primary-color) !important;
  color: var(--p-primary-contrast-color) !important;
}
.equipe-cell--dispo {
  background: color-mix(in srgb, var(--p-green-500) 8%, transparent);
}
.day-name {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--p-text-muted-color);
}
.equipe-cell--today .day-name { color: inherit; opacity: 0.85; }
.day-date { font-size: 0.82rem; font-weight: 600; }
.collab-avatar {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
}
.collab-name { font-size: 0.8rem; line-height: 1.2; }
.charge-bar {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  border-radius: 0 0 2px 2px;
  transition: background-color 0.2s;
}
.tache-chip {
  display: block;
  width: 100%;
  font: inherit;
  font-size: 0.7rem;
  text-align: left;
  appearance: none;
  border: 0;
  padding: 0.15rem 0.35rem;
  border-radius: 4px;
  background: var(--p-surface-border);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  cursor: pointer;
  line-height: 1.4;
}
.tache-chip:hover { filter: brightness(0.95); }
.tache-chip--en_cours  { background: #8B5CF620; color: #8B5CF6; }
.tache-chip--a_faire   { background: #0EA5E920; color: #0EA5E9; }
.tache-chip--bloquee   { background: #EF444420; color: #EF4444; }
.tache-chip--terminee  { background: #22C55E20; color: #22C55E; }
.dispo-label {
  font-size: 0.68rem;
  color: var(--p-text-muted-color);
  text-align: center;
  margin-top: auto;
  font-style: italic;
}

/* Légende charge */
.charge-legend {
  display: flex;
  align-items: center;
  gap: 1rem;
  font-size: 0.78rem;
  color: var(--p-text-muted-color);
  flex-wrap: wrap;
}
.charge-legend-title { font-weight: 600; }
.charge-legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.charge-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* ── RGAA ────────────────────────────────────────────────────────────────────── */
:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

/* ── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .planning-page { padding: 1rem; }
  .legend { gap: 0.75rem 1rem; }
  .missions-toolbar { flex-direction: column; align-items: flex-start; }
}
</style>
