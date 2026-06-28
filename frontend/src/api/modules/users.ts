import api from '@/api/client'
import type { User, PaginatedResponse } from '@/types'

export interface UserFilters {
  page?: number
  per_page?: number
  search?: string
  // Tableau → ?role[]=admin&role[]=collaborateur (Spatie role() = OR côté back)
  role?: string | string[]
}

export interface UserPayload {
  name: string
  email: string
  role: string
  entreprise_id?: number | null
}

export const usersApi = {
  getAll(params?: UserFilters): Promise<PaginatedResponse<User>> {
    return api.get('/users', { params }).then(r => r.data)
  },

  getOne(id: number): Promise<{ data: User }> {
    return api.get(`/users/${id}`).then(r => r.data)
  },

  // La creation declenche l'envoi d'une invitation : l'utilisateur definit son
  // mot de passe lui-meme. invitation_url est un repli copiable (sans mot de passe).
  create(data: UserPayload): Promise<{ data: User; invitation_url: string }> {
    return api.post('/users', data).then(r => r.data)
  },

  update(id: number, data: Partial<UserPayload>): Promise<{ data: User }> {
    return api.put(`/users/${id}`, data).then(r => r.data)
  },

  renvoyerInvitation(id: number): Promise<{ message: string; invitation_url: string }> {
    return api.post(`/users/${id}/renvoyer-invitation`).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/users/${id}`)
  },
}
