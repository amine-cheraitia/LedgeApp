import api from '@/api/client'
import type { Devis, PaginatedResponse } from '@/types'

export interface DevisFilters {
  page?: number
  per_page?: number
  search?: string
  entreprise_id?: number
  statut?: string
}

export interface DevisPayload {
  entreprise_id: number
  prestation_id: number
  date_devis: string
  date_validite: string
  notes?: string | null
}

export interface ConvertirEnMissionPayload {
  date_debut: string
  date_fin?: string | null
  collaborateur_ids?: number[]
}

export const devisApi = {
  getAll(params?: DevisFilters): Promise<PaginatedResponse<Devis>> {
    return api.get('/devis', { params }).then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Devis }> {
    return api.get(`/devis/${id}`).then(r => r.data)
  },

  create(data: DevisPayload): Promise<{ data: Devis }> {
    return api.post('/devis', data).then(r => r.data)
  },

  update(id: number, data: Partial<{ entreprise_id: number; prestation_id: number; date_devis: string; date_validite: string; notes: string }>): Promise<{ data: Devis }> {
    return api.put(`/devis/${id}`, data).then(r => r.data)
  },

  envoyer(id: number): Promise<{ data: Devis }> {
    return api.post(`/devis/${id}/envoyer`).then(r => r.data)
  },

  accepter(id: number): Promise<{ data: Devis }> {
    return api.post(`/devis/${id}/accepter`).then(r => r.data)
  },

  refuser(id: number): Promise<{ data: Devis }> {
    return api.post(`/devis/${id}/refuser`).then(r => r.data)
  },

  convertirEnMission(id: number, data: ConvertirEnMissionPayload): Promise<{ data: any }> {
    return api.post(`/devis/${id}/convertir-en-mission`, data).then(r => r.data)
  },

  getPdf(id: number): Promise<Blob> {
    return api.get(`/devis/${id}/pdf`, { responseType: 'blob' }).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/devis/${id}`)
  },
}
