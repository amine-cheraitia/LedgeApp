import api from '@/api/client'
import type { Setting } from '@/types'

export const settingsApi = {
  getAll(): Promise<{ data: Setting[] }> {
    return api.get('/settings').then(r => r.data)
  },

  update(settings: { key: string; value: string }[]): Promise<void> {
    return api.put('/settings', { settings })
  },
}
