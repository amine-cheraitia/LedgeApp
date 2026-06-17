import api from '@/api/client'
import type { Tache } from '@/types'

export interface TachePayload {
  titre: string
  description?: string
  assigned_to?: number | null
  statut?: string
  date_echeance?: string | null
  priorite?: number
}

export const tachesApi = {
  getAll(missionId: number): Promise<{ data: Tache[] }> {
    return api.get(`/missions/${missionId}/taches`).then(r => r.data)
  },

  getOne(missionId: number, tacheId: number): Promise<{ data: Tache }> {
    return api.get(`/missions/${missionId}/taches/${tacheId}`).then(r => r.data)
  },

  create(missionId: number, data: TachePayload): Promise<{ data: Tache }> {
    return api.post(`/missions/${missionId}/taches`, data).then(r => r.data)
  },

  update(missionId: number, tacheId: number, data: Partial<TachePayload>): Promise<{ data: Tache }> {
    return api.put(`/missions/${missionId}/taches/${tacheId}`, data).then(r => r.data)
  },

  delete(missionId: number, tacheId: number): Promise<void> {
    return api.delete(`/missions/${missionId}/taches/${tacheId}`)
  },
}
