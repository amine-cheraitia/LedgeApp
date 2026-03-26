import api from '@/api/client'
import type { Mission, PaginatedResponse } from '@/types'

export interface MissionFilters {
  page?: number
  per_page?: number
  search?: string
  entreprise_id?: number
  statut?: string
}

export interface MissionPayload {
  entreprise_id: number
  prestation_id: number
  date_debut: string
  date_fin: string
  collaborateur_ids?: number[]
  notes?: string
}

export interface MissionUpdatePayload {
  date_debut?: string
  date_fin?: string
  statut?: string
  collaborateur_ids?: number[]
  notes?: string
}

export const missionsApi = {
  getAll(params?: MissionFilters): Promise<PaginatedResponse<Mission>> {
    return api.get('/missions', { params }).then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Mission }> {
    return api.get(`/missions/${id}`).then(r => r.data)
  },

  create(data: MissionPayload): Promise<{ data: Mission }> {
    return api.post('/missions', data).then(r => r.data)
  },

  update(id: number, data: MissionUpdatePayload): Promise<{ data: Mission }> {
    return api.put(`/missions/${id}`, data).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/missions/${id}`)
  },
}
