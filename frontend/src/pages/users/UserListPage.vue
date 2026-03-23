<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import api from '@/api/client'
import type { User } from '@/types'

const toast = useToast()
const users = ref<User[]>([])
const loading = ref(false)
const totalRecords = ref(0)
const search = ref('')

const lazyParams = ref({
  page: 1,
  per_page: 15,
})

async function fetchUsers() {
  loading.value = true
  try {
    const { data } = await api.get('/users', {
      params: { ...lazyParams.value, search: search.value || undefined },
    })
    users.value = data.data
    totalRecords.value = data.meta?.total ?? data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les utilisateurs.', life: 3000 })
  } finally {
    loading.value = false
  }
}

function onPage(event: any) {
  lazyParams.value.page = event.page + 1
  fetchUsers()
}

function onSearch() {
  lazyParams.value.page = 1
  fetchUsers()
}

function roleColor(role: string) {
  const colors: Record<string, string> = {
    admin: 'danger',
    collaborateur: 'info',
    secretaire: 'warn',
    client: 'success',
  }
  return (colors[role] ?? 'secondary') as any
}

onMounted(fetchUsers)
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Utilisateurs</h2>
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="onSearch" role="search" class="search-form">
        <label for="search-users" class="sr-only">Rechercher un utilisateur</label>
        <InputText
          id="search-users"
          v-model="search"
          placeholder="Rechercher..."
          @keyup.enter="onSearch"
        />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="onSearch" />
      </form>
    </div>

    <DataTable
      :value="users"
      :loading="loading"
      :paginator="true"
      :rows="lazyParams.per_page"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
    >
      <Column field="name" header="Nom" />
      <Column field="email" header="Email" />
      <Column header="Rôle">
        <template #body="{ data }">
          <Tag v-for="role in data.roles" :key="role" :value="role" :severity="roleColor(role)" />
        </template>
      </Column>
      <Column field="created_at" header="Créé le">
        <template #body="{ data }">
          {{ new Date(data.created_at).toLocaleDateString('fr-FR') }}
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 1rem;
}

.page-toolbar {
  margin-bottom: 1rem;
}

.search-form {
  display: flex;
  gap: 0.5rem;
  max-width: 20rem;
}
</style>
