import api from '@/api/client'
import type { Exercice } from '@/types'

export interface ExercicePayload {
  annee: number
  date_ouverture: string
  date_cloture: string
  statut: 'ouvert' | 'cloture'
}

export const exercicesApi = {
  getAll(): Promise<{ data: Exercice[] }> {
    return api.get('/exercices').then(r => r.data)
  },

  getOne(id: number): Promise<{ data: Exercice }> {
    return api.get(`/exercices/${id}`).then(r => r.data)
  },

  getCurrent(): Promise<{ data: Exercice }> {
    return api.get('/exercices/current').then(r => r.data)
  },

  create(data: ExercicePayload): Promise<{ data: Exercice }> {
    return api.post('/exercices', data).then(r => r.data)
  },

  update(id: number, data: Partial<ExercicePayload>): Promise<{ data: Exercice }> {
    return api.put(`/exercices/${id}`, data).then(r => r.data)
  },

  rapportCloturePdf(id: number): Promise<Blob> {
    return api.get(`/exercices/${id}/rapport-cloture/pdf`, { responseType: 'blob' }).then(r => r.data)
  },
}
