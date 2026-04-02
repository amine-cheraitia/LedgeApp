import type { Facture } from '@/types'
import api from '../client'

export const creancesApi = {
  index: () => api.get<{ data: Facture[] }>('/creances'),
}
