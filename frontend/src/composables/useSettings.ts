import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { settingsApi } from '@/api/modules/settings'
import type { Setting } from '@/types'

export function useSettings() {
  const toast = useToast()
  const settings = ref<Setting[]>([])
  const loading = ref(false)
  const saving = ref(false)

  async function fetchSettings() {
    loading.value = true
    try {
      const response = await settingsApi.getAll()
      settings.value = response.data
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les parametres.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function saveSettings() {
    saving.value = true
    try {
      await settingsApi.update(settings.value.map(s => ({ key: s.key, value: s.value })))
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Parametres enregistres.', life: 3000 })
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Echec de la sauvegarde.', life: 3000 })
    } finally {
      saving.value = false
    }
  }

  return {
    settings,
    loading,
    saving,
    fetchSettings,
    saveSettings,
  }
}
