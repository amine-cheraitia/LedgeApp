import { ref } from 'vue'
import {
  statsApi,
  type CollaborateurStats,
  type DashboardStats,
  type SecretaireStats,
} from '@/api/modules/stats'

export function useDashboardStats() {
  const loading = ref(false)
  // Etat d'erreur expose aux pages : sans lui, un echec API laissait une page
  // quasi vide (v-if="stats" jamais satisfait) sans message ni bouton Reessayer.
  const error = ref(false)
  const adminStats = ref<DashboardStats | null>(null)
  const collaborateurStats = ref<CollaborateurStats | null>(null)
  const secretaireStats = ref<SecretaireStats | null>(null)

  async function fetchAdminStats(exerciceId?: number | null): Promise<void> {
    loading.value = true
    error.value = false
    try {
      const res = await statsApi.getDashboard(exerciceId)
      adminStats.value = res.data
    } catch {
      adminStats.value = null
      error.value = true
    } finally {
      loading.value = false
    }
  }

  async function fetchCollaborateurStats(): Promise<void> {
    loading.value = true
    error.value = false
    try {
      const res = await statsApi.getCollaborateurDashboard()
      collaborateurStats.value = res.data
    } catch {
      collaborateurStats.value = null
      error.value = true
    } finally {
      loading.value = false
    }
  }

  async function fetchSecretaireStats(): Promise<void> {
    loading.value = true
    error.value = false
    try {
      const res = await statsApi.getSecretaireDashboard()
      secretaireStats.value = res.data
    } catch {
      secretaireStats.value = null
      error.value = true
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    adminStats,
    collaborateurStats,
    secretaireStats,
    fetchAdminStats,
    fetchCollaborateurStats,
    fetchSecretaireStats,
  }
}
