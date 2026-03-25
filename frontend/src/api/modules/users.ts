import api from '@/api/client'
import type { User, PaginatedResponse } from '@/types'

export interface UserFilters {
  page?: number
  per_page?: number
  search?: string
  role?: string
}

export interface UserPayload {
  name: string
  email: string
  password?: string
  role: string
  entreprise_id?: number | null
  portail_actif?: boolean
}

export const usersApi = {
  getAll(params?: UserFilters): Promise<PaginatedResponse<User>> {
    return api.get('/users', { params }).then(r => r.data)
  },

  getOne(id: number): Promise<{ data: User }> {
    return api.get(`/users/${id}`).then(r => r.data)
  },

  create(data: UserPayload): Promise<{ data: User }> {
    return api.post('/users', data).then(r => r.data)
  },

  update(id: number, data: Partial<UserPayload>): Promise<{ data: User }> {
    return api.put(`/users/${id}`, data).then(r => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/users/${id}`)
  },
}
