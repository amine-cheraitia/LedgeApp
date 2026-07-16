import api from '@/api/client'
import type { Entreprise, PaginatedResponse } from '@/types'

export interface EntrepriseFilters {
  page?: number
  per_page?: number
  search?: string
  statut?: string
  wilaya?: string
}

// Compteurs globaux (independants des filtres) pour le sous-titre de la liste
export interface EntrepriseCompteurs {
  total: number
  clients: number
  prospects: number
}

export const entreprisesApi = {
  getAll(params?: EntrepriseFilters): Promise<PaginatedResponse<Entreprise> & { compteurs?: EntrepriseCompteurs }> {
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

  // Active l'acces : cree le compte client et envoie une invitation a definir
  // le mot de passe. invitation_url est un repli copiable (sans mot de passe).
  activerPortail(id: number, data: { name: string; email: string }): Promise<{ message: string; invitation_url: string }> {
    return api.post(`/entreprises/${id}/activer-portail`, data).then(r => r.data)
  },

  renvoyerInvitation(id: number): Promise<{ message: string; invitation_url: string }> {
    return api.post(`/entreprises/${id}/renvoyer-invitation`).then(r => r.data)
  },

  togglePortail(id: number) {
    return api.post(`/entreprises/${id}/toggle-portail`).then(r => r.data)
  },

  wilayas(): Promise<{ data: string[] }> {
    return api.get('/entreprises/wilayas').then(r => r.data)
  },

  exportCsv(params?: Omit<EntrepriseFilters, 'page' | 'per_page'>): Promise<Blob> {
    return api.get('/entreprises/export-csv', { params, responseType: 'blob' }).then(r => r.data)
  },
}
