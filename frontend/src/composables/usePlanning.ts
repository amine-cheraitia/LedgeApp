import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import type { EventInput, EventDropArg } from '@fullcalendar/core'
import type { EventResizeDoneArg } from '@fullcalendar/interaction'
import { planningApi, type CalendarMission, type CalendarTache } from '@/api/modules/planning'
import { missionsApi } from '@/api/modules/missions'
import { tachesApi } from '@/api/modules/taches'
import { usersApi } from '@/api/modules/users'
import type { User } from '@/types'

// ── Palettes ──────────────────────────────────────────────────────────────────

const PRESTATION_PALETTE = [
  '#6366F1', // indigo
  '#EC4899', // rose
  '#14B8A6', // teal
  '#F97316', // orange
  '#8B5CF6', // violet
  '#06B6D4', // cyan
  '#84CC16', // lime
  '#D946EF', // fuchsia
  '#A855F7', // purple
  '#10B981', // emerald
]

const STATUS_BORDER: Record<string, string> = {
  en_cours:  '#3B82F6',
  terminee:  '#22C55E',
  suspendue: '#F59E0B',
  annulee:   '#6B7280',
}

const TACHE_COLORS: Record<string, string> = {
  a_faire:  '#0EA5E9',
  en_cours: '#8B5CF6',
  terminee: '#22C55E',
  bloquee:  '#EF4444',
}

export function prestationColor(id: number | null): string {
  if (id === null) return '#94A3B8'
  return PRESTATION_PALETTE[id % PRESTATION_PALETTE.length]
}

// ── Conversion événements ─────────────────────────────────────────────────────

function missionToEvent(m: CalendarMission): EventInput {
  const bg     = prestationColor(m.prestation_id)
  const border = STATUS_BORDER[m.statut] ?? bg
  return {
    id: `mission-${m.id}`,
    title: m.titre,
    start: m.date_debut,
    end: m.date_fin ?? undefined,
    backgroundColor: bg + 'CC', // 80 % opacité
    borderColor: border,
    textColor: '#fff',
    extendedProps: { ...m },
    allDay: true,
    classNames: [`fc-mission-statut-${m.statut}`],
  }
}

function tacheToEvent(t: CalendarTache): EventInput {
  const color = TACHE_COLORS[t.statut] ?? '#0EA5E9'
  return {
    id: `tache-${t.id}`,
    title: t.titre,
    start: t.date_echeance,
    backgroundColor: color,
    borderColor: color,
    textColor: '#fff',
    extendedProps: { ...t },
    allDay: true,
  }
}

// ── Composable ────────────────────────────────────────────────────────────────

export function usePlanning() {
  const toast = useToast()

  // Filtre collaborateur
  const collaborateurFilter = ref<number | null>(null)
  const collaborateurs = ref<User[]>([])
  const loadingCollab = ref(false)

  // Données brutes
  const rawMissions = ref<CalendarMission[]>([])
  const rawTaches   = ref<CalendarTache[]>([])
  const loadingEvents = ref(false)

  // Plage courante (pour le refresh après action)
  const currentFrom = ref('')
  const currentTo   = ref('')

  // Filtres statuts (actifs par défaut : tout sauf terminée/annulée)
  const activeStatuts = ref<Set<string>>(new Set(['en_cours', 'suspendue']))
  const showTaches    = ref(true)

  // Événements filtrés → passés directement au calendrier
  const filteredEvents = computed<EventInput[]>(() => [
    ...rawMissions.value
      .filter(m => activeStatuts.value.has(m.statut))
      .map(missionToEvent),
    ...(showTaches.value ? rawTaches.value.map(tacheToEvent) : []),
  ])

  // Stats par statut (pour les compteurs dans les chips)
  const missionCountByStatut = computed(() => {
    const counts: Record<string, number> = {}
    for (const m of rawMissions.value) {
      counts[m.statut] = (counts[m.statut] ?? 0) + 1
    }
    return counts
  })

  // Prestations uniques présentes sur la période (pour la légende dynamique)
  const prestationsVues = computed(() => {
    const seen = new Map<number, { id: number; code: string; designation: string }>()
    for (const m of rawMissions.value) {
      if (m.prestation_id && !seen.has(m.prestation_id)) {
        seen.set(m.prestation_id, {
          id: m.prestation_id,
          code: m.prestation_code ?? '',
          designation: m.prestation ?? m.prestation_code ?? '',
        })
      }
    }
    return [...seen.values()]
  })

  // ── Méthodes ───────────────────────────────────────────────────────────────

  async function fetchCollaborateurs() {
    loadingCollab.value = true
    try {
      const resp = await usersApi.getAll({ per_page: 100 })
      collaborateurs.value = resp.data.filter(
        (u) => u.roles.includes('admin') || u.roles.includes('collaborateur'),
      )
    } catch {
      // silencieux — filtre optionnel
    } finally {
      loadingCollab.value = false
    }
  }

  async function loadEvents(from: string, to: string) {
    currentFrom.value = from
    currentTo.value   = to
    loadingEvents.value = true
    try {
      const { data } = await planningApi.getCalendar({
        from,
        to,
        ...(collaborateurFilter.value !== null ? { collaborateur_id: collaborateurFilter.value } : {}),
      })
      rawMissions.value = data.missions
      rawTaches.value   = data.taches
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger le planning.', life: 3000 })
    } finally {
      loadingEvents.value = false
    }
  }

  function toggleStatut(statut: string) {
    const next = new Set(activeStatuts.value)
    next.has(statut) ? next.delete(statut) : next.add(statut)
    activeStatuts.value = next
  }

  function toggleTaches() {
    showTaches.value = !showTaches.value
  }

  async function changerStatutMission(missionId: number, statut: string) {
    await missionsApi.update(missionId, { statut: statut as any })
    // Mise à jour locale immédiate
    const m = rawMissions.value.find(x => x.id === missionId)
    if (m) m.statut = statut as any
    rawMissions.value = [...rawMissions.value] // trigger reactivity
  }

  async function onEventDrop(info: EventDropArg) {
    const props = info.event.extendedProps as CalendarMission | CalendarTache
    try {
      if (props.type === 'mission') {
        const m = props as CalendarMission
        const newStart = info.event.startStr.slice(0, 10)
        const delta = info.delta.days
        const newEnd = m.date_fin
          ? (() => {
              const d = new Date(m.date_fin)
              d.setDate(d.getDate() + delta)
              return d.toISOString().slice(0, 10)
            })()
          : null
        await missionsApi.update(m.id, {
          date_debut: newStart,
          ...(newEnd ? { date_fin: newEnd } : {}),
        })
        toast.add({ severity: 'success', summary: 'Mission déplacée', detail: m.reference, life: 2000 })
      } else {
        const t = props as CalendarTache
        await tachesApi.update(t.mission_id, t.id, {
          date_echeance: info.event.startStr.slice(0, 10),
        })
        toast.add({ severity: 'success', summary: 'Tâche déplacée', detail: t.titre, life: 2000 })
      }
    } catch {
      info.revert()
      toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de déplacer l'événement.", life: 3000 })
    }
  }

  async function onEventResize(info: EventResizeDoneArg) {
    const props = info.event.extendedProps as CalendarMission
    if (props.type !== 'mission') { info.revert(); return }
    try {
      await missionsApi.update(props.id, { date_fin: info.event.endStr.slice(0, 10) })
      toast.add({ severity: 'success', summary: 'Mission redimensionnée', detail: props.reference, life: 2000 })
    } catch {
      info.revert()
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de redimensionner la mission.', life: 3000 })
    }
  }

  // ── Vue Équipe ────────────────────────────────────────────────────────────────

  const teamWeekStart  = ref<string>('')
  const rawTachesEquipe = ref<CalendarTache[]>([])
  const loadingEquipe  = ref(false)

  function initWeek() {
    const d = new Date()
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7)) // ramener au lundi
    teamWeekStart.value = d.toISOString().slice(0, 10)
  }

  function shiftWeek(days: number) {
    const d = new Date(teamWeekStart.value)
    d.setDate(d.getDate() + days)
    teamWeekStart.value = d.toISOString().slice(0, 10)
  }

  const prevWeek = () => shiftWeek(-7)
  const nextWeek = () => shiftWeek(7)

  const teamWeekDays = computed<string[]>(() => {
    if (!teamWeekStart.value) return []
    return Array.from({ length: 7 }, (_, i) => {
      const d = new Date(teamWeekStart.value)
      d.setDate(d.getDate() + i)
      return d.toISOString().slice(0, 10)
    })
  })

  async function loadEquipeWeek() {
    if (!teamWeekStart.value || teamWeekDays.value.length === 0) return
    loadingEquipe.value = true
    try {
      const from = teamWeekStart.value
      const to   = teamWeekDays.value[6]
      const { data } = await planningApi.getCalendar({ from, to })
      rawTachesEquipe.value = data.taches
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de charger la vue équipe.", life: 3000 })
    } finally {
      loadingEquipe.value = false
    }
  }

  watch(teamWeekStart, loadEquipeWeek)

  // Grille : collaborateurId → date → tâches
  const teamGridData = computed<Record<number, Record<string, CalendarTache[]>>>(() => {
    const grid: Record<number, Record<string, CalendarTache[]>> = {}
    for (const t of rawTachesEquipe.value) {
      if (!t.assigned_to || !t.date_echeance) continue
      if (!grid[t.assigned_to]) grid[t.assigned_to] = {}
      if (!grid[t.assigned_to][t.date_echeance]) grid[t.assigned_to][t.date_echeance] = []
      grid[t.assigned_to][t.date_echeance].push(t)
    }
    return grid
  })

  function chargeColor(count: number): string {
    if (count === 0) return 'var(--p-green-500)'
    if (count <= 2)  return 'var(--p-orange-400)'
    return 'var(--p-red-500)'
  }

  function chargeLabel(count: number): string {
    if (count === 0) return 'Disponible'
    if (count <= 2)  return 'Modéré'
    return 'Chargé'
  }

  return {
    // Filtre collaborateur
    collaborateurFilter,
    collaborateurs,
    loadingCollab,
    fetchCollaborateurs,
    // Événements
    filteredEvents,
    loadingEvents,
    loadEvents,
    // Filtres statuts
    activeStatuts,
    showTaches,
    toggleStatut,
    toggleTaches,
    missionCountByStatut,
    // Légende
    prestationsVues,
    prestationColor,
    STATUS_BORDER,
    TACHE_COLORS,
    // Actions
    changerStatutMission,
    onEventDrop,
    onEventResize,
    // Vue Équipe
    teamWeekStart,
    teamWeekDays,
    teamGridData,
    loadingEquipe,
    initWeek,
    prevWeek,
    nextWeek,
    loadEquipeWeek,
    chargeColor,
    chargeLabel,
  }
}
