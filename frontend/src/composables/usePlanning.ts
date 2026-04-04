import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import type { EventInput, EventDropArg } from '@fullcalendar/core'
import type { EventResizeDoneArg } from '@fullcalendar/interaction'
import { planningApi, type CalendarMission, type CalendarTache } from '@/api/modules/planning'
import { missionsApi } from '@/api/modules/missions'
import { tachesApi } from '@/api/modules/taches'
import { usersApi } from '@/api/modules/users'
import type { User } from '@/types'

const MISSION_COLORS: Record<string, string> = {
  en_cours: '#3B82F6',
  terminee: '#22C55E',
  suspendue: '#F59E0B',
  annulee: '#6B7280',
}

const TACHE_COLORS: Record<string, string> = {
  a_faire: '#0EA5E9',
  en_cours: '#8B5CF6',
  terminee: '#22C55E',
  bloquee: '#EF4444',
}

function missionToEvent(m: CalendarMission): EventInput {
  return {
    id: `mission-${m.id}`,
    title: m.titre,
    start: m.date_debut,
    end: m.date_fin ?? undefined,
    backgroundColor: MISSION_COLORS[m.statut] ?? '#3B82F6',
    borderColor: MISSION_COLORS[m.statut] ?? '#3B82F6',
    extendedProps: { ...m },
    allDay: true,
  }
}

function tacheToEvent(t: CalendarTache): EventInput {
  return {
    id: `tache-${t.id}`,
    title: t.titre,
    start: t.date_echeance,
    backgroundColor: TACHE_COLORS[t.statut] ?? '#0EA5E9',
    borderColor: TACHE_COLORS[t.statut] ?? '#0EA5E9',
    extendedProps: { ...t },
    allDay: true,
  }
}

export function usePlanning() {
  const toast = useToast()
  const collaborateurFilter = ref<number | null>(null)
  const collaborateurs = ref<User[]>([])
  const loadingCollab = ref(false)

  async function fetchCollaborateurs() {
    loadingCollab.value = true
    try {
      const resp = await usersApi.getAll({ per_page: 100 })
      collaborateurs.value = resp.data.filter(
        (u) => u.roles.includes('admin') || u.roles.includes('collaborateur'),
      )
    } catch {
      // silencieux — le filtre est optionnel
    } finally {
      loadingCollab.value = false
    }
  }

  async function fetchEvents(
    fetchInfo: { startStr: string; endStr: string },
    successCallback: (events: EventInput[]) => void,
    failureCallback: (error: Error) => void,
  ) {
    try {
      const params = {
        from: fetchInfo.startStr.slice(0, 10),
        to: fetchInfo.endStr.slice(0, 10),
        ...(collaborateurFilter.value !== null ? { collaborateur_id: collaborateurFilter.value } : {}),
      }
      const { data } = await planningApi.getCalendar(params)
      const events: EventInput[] = [
        ...data.missions.map(missionToEvent),
        ...data.taches.map(tacheToEvent),
      ]
      successCallback(events)
    } catch (err) {
      failureCallback(err instanceof Error ? err : new Error('Erreur calendrier'))
    }
  }

  async function onEventDrop(info: EventDropArg) {
    const props = info.event.extendedProps as CalendarMission | CalendarTache
    try {
      if (props.type === 'mission') {
        const m = props as CalendarMission
        const newStart = info.event.startStr.slice(0, 10)
        const delta = info.delta.days
        const oldEnd = m.date_fin
        const newEnd = oldEnd
          ? (() => {
              const d = new Date(oldEnd)
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
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de déplacer l\'événement.', life: 3000 })
    }
  }

  async function onEventResize(info: EventResizeDoneArg) {
    const props = info.event.extendedProps as CalendarMission
    if (props.type !== 'mission') {
      info.revert()
      return
    }
    try {
      await missionsApi.update(props.id, {
        date_fin: info.event.endStr.slice(0, 10),
      })
      toast.add({ severity: 'success', summary: 'Mission redimensionnée', detail: props.reference, life: 2000 })
    } catch {
      info.revert()
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de redimensionner la mission.', life: 3000 })
    }
  }

  return {
    collaborateurFilter,
    collaborateurs,
    loadingCollab,
    fetchCollaborateurs,
    fetchEvents,
    onEventDrop,
    onEventResize,
  }
}
