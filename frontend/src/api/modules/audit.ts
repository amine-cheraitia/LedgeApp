import api from '@/api/client'
import type { Activity, AuditEvent, PaginatedResponse } from '@/types'

export interface AuditFilters {
  page?: number
  per_page?: number
  event?: AuditEvent
  causer_id?: number
  subject_type?: string
  date_debut?: string
  date_fin?: string
}

export const auditApi = {
  getAll(params?: AuditFilters): Promise<PaginatedResponse<Activity>> {
    return api.get('/audit-logs', { params }).then(r => r.data)
  },
}
