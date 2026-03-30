import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { usersApi, type UserFilters, type UserPayload } from '@/api/modules/users'
import type { User } from '@/types'

export function useUsers() {
  const toast = useToast()
  const users = ref<User[]>([])
  const loading = ref(false)
  const totalRecords = ref(0)
  const filters = ref<UserFilters>({ page: 1, per_page: 15 })

  async function fetchUsers() {
    loading.value = true
    try {
      const response = await usersApi.getAll({
        ...filters.value,
        search: filters.value.search || undefined,
      })
      users.value = response.data
      totalRecords.value = response.meta?.total ?? response.data.length
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les utilisateurs.', life: 3000 })
    } finally {
      loading.value = false
    }
  }

  async function createUser(data: UserPayload) {
    try {
      const response = await usersApi.create(data)
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Utilisateur cree.', life: 3000 })
      await fetchUsers()
      return response.data
    } catch (error: any) {
      const message = error.response?.data?.message || 'Erreur lors de la creation.'
      toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
      throw error
    }
  }

  async function updateUser(id: number, data: Partial<UserPayload>) {
    try {
      const response = await usersApi.update(id, data)
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Utilisateur mis a jour.', life: 3000 })
      await fetchUsers()
      return response.data
    } catch (error: any) {
      const message = error.response?.data?.message || 'Erreur lors de la mise a jour.'
      toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
      throw error
    }
  }

  async function deleteUser(id: number) {
    try {
      await usersApi.delete(id)
      toast.add({ severity: 'success', summary: 'Succes', detail: 'Utilisateur supprime.', life: 3000 })
      await fetchUsers()
    } catch (error: any) {
      const message = error.response?.data?.message || 'Erreur lors de la suppression.'
      toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
    }
  }

  function onPage(event: { page: number }) {
    filters.value.page = event.page + 1
    fetchUsers()
  }

  function onSearch(search: string) {
    filters.value.search = search
    filters.value.page = 1
    fetchUsers()
  }

  return {
    users,
    loading,
    totalRecords,
    filters,
    fetchUsers,
    createUser,
    updateUser,
    deleteUser,
    onPage,
    onSearch,
  }
}
