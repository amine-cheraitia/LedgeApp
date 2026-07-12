import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import type { EventInput, EventDropArg } from '@fullcalendar/core'
import type { EventResizeDoneArg } from '@fullcalendar/interaction'
import { planningApi, type CalendarMission, type CalendarTache } from '@/api/modules/planning'
import { missionsApi } from '@/api/modules/missions'
import { tachesApi } from '@/api/modules/taches'
import { usersApi } from '@/api/modules/users'
import { useAuthStore } from '@/stores/authStore'
import type { User } from '@/types'
import { prioriteColor } from '@/utils/priorite'

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

// Couleur d'une tâche selon sa priorité : voir @/utils/priorite (source de vérité, 4 niveaux).

export function prestationColor(id: number | null): string {
  if (id === null) return '#94A3B8'
  return PRESTATION_PALETTE[id % PRESTATION_PALETTE.length]
}

// ── Conversion événements ─────────────────────────────────────────────────────

// Ajoute n jours à une date ISO 'YYYY-MM-DD'. UTC pour éviter les décalages DST.
function addDays(iso: string, n: number): string {
  const d = new Date(iso + 'T00:00:00Z')
  d.setUTCDate(d.getUTCDate() + n)
  return d.toISOString().slice(0, 10)
}

// Extrait le message d'erreur backend (message global ou 1re erreur de validation).
function extractError(e: any, fallback: string): string {
  return (
    e?.response?.data?.message
    ?? (Object.values(e?.response?.data?.errors ?? {})?.[0] as string[] | undefined)?.[0]
    ?? fallback
  )
}

function missionToEvent(m: CalendarMission): EventInput {
  const bg     = prestationColor(m.prestation_id)
  const border = STATUS_BORDER[m.statut] ?? bg
  return {
    id: `mission-${m.id}`,
    title: m.titre,
    start: m.date_debut,
    // FullCalendar : la fin d'un événement allDay est EXCLUSIVE → +1 jour pour couvrir date_fin.
    end: m.date_fin ? addDays(m.date_fin, 1) : undefined,
    backgroundColor: bg + 'CC', // 80 % opacité
    borderColor: border,
    textColor: '#fff',
    extendedProps: { ...m },
    allDay: true,
    classNames: [`fc-mission-statut-${m.statut}`],
  }
}

// Tâche → événement (planning collaborateur). Couleur selon la priorité.
function tacheToEvent(t: CalendarTache): EventInput {
  const color  = prioriteColor(t.priorite)
  const start  = t.date_debut ?? t.date_echeance ?? undefined
  const endSrc = t.date_echeance ?? t.date_debut
  return {
    id: `tache-${t.id}`,
    title: t.titre,
    start,
    // FullCalendar : la fin d'un événement allDay est EXCLUSIVE → +1 jour pour couvrir l'échéance.
    end: endSrc ? addDays(endSrc, 1) : undefined,
    backgroundColor: color + 'CC', // 80 % opacité
    borderColor: color,
    textColor: '#fff',
    extendedProps: { ...t },
    allDay: true,
    classNames: [`fc-tache-prio-${t.priorite}`],
  }
}

// ── Composable ────────────────────────────────────────────────────────────────

export function usePlanning() {
  const toast = useToast()
  const auth  = useAuthStore()

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

  // Événements filtrés selon le rôle :
  // - collaborateur → SES tâches uniquement (déjà scopées serveur), colorées par priorité ;
  // - admin → missions filtrées par statut (les tâches vivent dans l'onglet Équipe).
  const filteredEvents = computed<EventInput[]>(() =>
    auth.isCollaborateur
      ? rawTaches.value.map(tacheToEvent)
      : rawMissions.value
          .filter(m => activeStatuts.value.has(m.statut))
          .map(missionToEvent),
  )

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
    if (next.has(statut)) {
      next.delete(statut)
    } else {
      next.add(statut)
    }
    activeStatuts.value = next
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
    const delta = info.delta.days
    try {
      if (props.type === 'mission') {
        const m = props as CalendarMission
        await missionsApi.update(m.id, {
          ...(m.date_debut ? { date_debut: addDays(m.date_debut, delta) } : {}),
          ...(m.date_fin ? { date_fin: addDays(m.date_fin, delta) } : {}),
        })
        toast.add({ severity: 'success', summary: 'Mission déplacée', detail: m.reference, life: 2000 })
      } else {
        const t = props as CalendarTache
        // Deplacer la barre decale les deux dates du meme delta (jours).
        await tachesApi.update(t.mission_id, t.id, {
          ...(t.date_debut ? { date_debut: addDays(t.date_debut, delta) } : {}),
          ...(t.date_echeance ? { date_echeance: addDays(t.date_echeance, delta) } : {}),
        })
        toast.add({ severity: 'success', summary: 'Tâche déplacée', detail: t.titre, life: 2000 })
      }
    } catch (e: any) {
      info.revert()
      toast.add({ severity: 'error', summary: 'Erreur', detail: extractError(e, "Impossible de déplacer l'événement."), life: 4000 })
    }
  }

  async function onEventResize(info: EventResizeDoneArg) {
    const props = info.event.extendedProps as CalendarMission | CalendarTache
    // endStr est EXCLUSIF (allDay) → dernier jour réel = endStr - 1.
    const newEnd = info.event.endStr
      ? addDays(info.event.endStr.slice(0, 10), -1)
      : info.event.startStr.slice(0, 10)
    try {
      if (props.type === 'mission') {
        await missionsApi.update(props.id, { date_fin: newEnd })
        toast.add({ severity: 'success', summary: 'Mission redimensionnée', detail: props.reference, life: 2000 })
      } else {
        // Redimensionner une tâche ajuste son échéance (même convention que la mission).
        await tachesApi.update(props.mission_id, props.id, { date_echeance: newEnd })
        toast.add({ severity: 'success', summary: 'Tâche redimensionnée', detail: props.titre, life: 2000 })
      }
    } catch (e: any) {
      info.revert()
      toast.add({ severity: 'error', summary: 'Erreur', detail: extractError(e, "Impossible de redimensionner l'événement."), life: 4000 })
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

  // Grille : collaborateurId → date (YYYY-MM-DD) → tâches présentes ce jour-là.
  // Chaque tâche couvre TOUTE sa plage début → échéance (intersectée avec la semaine visible).
  const teamGridData = computed<Record<number, Record<string, CalendarTache[]>>>(() => {
    const grid: Record<number, Record<string, CalendarTache[]>> = {}
    const days = teamWeekDays.value
    if (days.length === 0) return grid
    const weekStart = days[0]
    const weekEnd   = days[days.length - 1]

    for (const t of rawTachesEquipe.value) {
      if (!t.assigned_to) continue
      const startStr = t.date_debut ?? t.date_echeance
      const endStr   = t.date_echeance ?? t.date_debut
      if (!startStr || !endStr) continue           // tâche sans aucune date → ignorée

      const from = startStr > weekStart ? startStr : weekStart   // intersection avec la semaine
      const to   = endStr   < weekEnd   ? endStr   : weekEnd
      if (from > to) continue                       // hors semaine visible

      if (!grid[t.assigned_to]) grid[t.assigned_to] = {}
      for (let d = from; d <= to; d = addDays(d, 1)) {  // inclusif, ≤ 7 itérations
        ;(grid[t.assigned_to][d] ??= []).push(t)
      }
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
    toggleStatut,
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
