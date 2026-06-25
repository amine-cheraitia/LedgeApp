import api from '@/api/client'
import type { Mission, Tache } from '@/types'

export interface CalendarParams {
  from: string
  to: string
  collaborateur_id?: number
}

export interface CalendarData {
  missions: CalendarMission[]
  taches: CalendarTache[]
}

export interface CalendarMission {
  id: number
  type: 'mission'
  reference: string
  titre: string
  date_debut: string
  date_fin: string | null
  statut: Mission['statut']
  prix_ht: number
  prestation_id: number | null
  prestation_code: string | null
  entreprise: string | null
  prestation: string | null
}

export interface CalendarTache {
  id: number
  type: 'tache'
  titre: string
  date_echeance: string
  statut: Tache['statut']
  priorite: number
  mission_id: number
  mission_ref: string | null
  entreprise: string | null
  assigned_to: number | null
  assignee: string | null
}

export const planningApi = {
  getCalendar(params: CalendarParams): Promise<{ data: CalendarData }> {
    return api.get('/calendar', { params }).then((r) => r.data)
  },
}
