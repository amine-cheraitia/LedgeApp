import api from '@/api/client'
import type { Devis, PaginatedResponse } from '@/types'

export interface DevisFilters {
  page?: number
  per_page?: number
  search?: string
  entreprise_id?: number
  statut?: string
}

export interface DevisLignePayload {
  prestation_id?: number | null
  designation: string
  quantite: number
  prix_unitaire_ht: number
}

export interface DevisPayload {
  entreprise_id: number
  date_devis: string
  date_validite: string
  notes?: string | null
  lignes: DevisLignePayload[]
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

  update(id: number, data: Partial<{ statut: string; notes: string; date_validite: string }>): Promise<{ data: Devis }> {
    return api.put(`/devis/${id}`, data).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/devis/${id}`)
  },
}
