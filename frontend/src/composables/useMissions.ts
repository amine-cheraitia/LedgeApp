import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { missionsApi, type MissionFilters, type MissionPayload, type MissionUpdatePayload } from '@/api/modules/missions'
import type { Mission } from '@/types'

export function useMissions() {
  const toast = useToast()
  const missions = ref<Mission[]>([])
  const loading = ref(false)
  const totalRecords = ref(0)
  const filters = ref<MissionFilters>({ page: 1, per_page: 15 })

  async function fetchMissions() {
    loading.value = true
    try {
      const response = await missionsApi.getAll({
        ...filters.value,
        search: filters.value.search || undefined,
      })
      missions.value = response.data
      totalRecords.value = response.meta?.total ?? response.data.length
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les missions.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createMission(data: MissionPayload) {
    const response = await missionsApi.create(data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Mission creee.', life: 3000 })
    await fetchMissions()
    return response.data
  }

  async function updateMission(id: number, data: MissionUpdatePayload) {
    const response = await missionsApi.update(id, data)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Mission mise a jour.', life: 3000 })
    await fetchMissions()
    return response.data
  }

  async function deleteMission(id: number) {
    await missionsApi.delete(id)
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Mission supprimee.', life: 3000 })
    await fetchMissions()
  }

  function onPage(event: { page: number }) {
    filters.value.page = event.page + 1
    fetchMissions()
  }

  function onSearch(search: string) {
    filters.value.search = search
    filters.value.page = 1
    fetchMissions()
  }

  return {
    missions,
    loading,
    totalRecords,
    filters,
    fetchMissions,
    createMission,
    updateMission,
    deleteMission,
    onPage,
    onSearch,
  }
}
