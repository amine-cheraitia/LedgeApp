import { ref } from 'vue'
import {
  statsApi,
  type CollaborateurStats,
  type DashboardStats,
  type SecretaireStats,
} from '@/api/modules/stats'

export function useDashboardStats() {
  const loading = ref(false)
  const adminStats = ref<DashboardStats | null>(null)
  const collaborateurStats = ref<CollaborateurStats | null>(null)
  const secretaireStats = ref<SecretaireStats | null>(null)

  async function fetchAdminStats(exerciceId?: number | null): Promise<void> {
    loading.value = true
    try {
      const res = await statsApi.getDashboard(exerciceId)
      adminStats.value = res.data
    } catch {
      adminStats.value = null
    } finally {
      loading.value = false
    }
  }

  async function fetchCollaborateurStats(): Promise<void> {
    loading.value = true
    try {
      const res = await statsApi.getCollaborateurDashboard()
      collaborateurStats.value = res.data
    } catch {
      collaborateurStats.value = null
    } finally {
      loading.value = false
    }
  }

  async function fetchSecretaireStats(): Promise<void> {
    loading.value = true
    try {
      const res = await statsApi.getSecretaireDashboard()
      secretaireStats.value = res.data
    } catch {
      secretaireStats.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    adminStats,
    collaborateurStats,
    secretaireStats,
    fetchAdminStats,
    fetchCollaborateurStats,
    fetchSecretaireStats,
  }
}
