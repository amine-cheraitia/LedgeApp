import api from '@/api/client'
import type { Entreprise, PaginatedResponse } from '@/types'

export interface EntrepriseFilters {
  page?: number
  per_page?: number
  search?: string
  statut?: string
}

export const entreprisesApi = {
  getAll(params?: EntrepriseFilters): Promise<PaginatedResponse<Entreprise>> {
    return api.get('/entreprises', { params }).then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Entreprise }> {
    return api.get(`/entreprises/${id}`).then(r => r.data)
  },

  create(data: Partial<Entreprise>): Promise<{ data: Entreprise }> {
    return api.post('/entreprises', data).then(r => r.data)
  },

  update(id: number, data: Partial<Entreprise>): Promise<{ data: Entreprise }> {
    return api.put(`/entreprises/${id}`, data).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/entreprises/${id}`)
  },

  activerPortail(id: number, data: { name: string; email: string }) {
    return api.post(`/entreprises/${id}/activer-portail`, data).then(r => r.data)
  },

  togglePortail(id: number) {
    return api.post(`/entreprises/${id}/toggle-portail`).then(r => r.data)
  },
}
