<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import api from '@/api/client'
import type { Entreprise } from '@/types'

const toast = useToast()
const entreprises = ref<Entreprise[]>([])
const loading = ref(false)
const totalRecords = ref(0)
const search = ref('')

const lazyParams = ref({ page: 1, per_page: 15 })

async function fetchEntreprises() {
  loading.value = true
  try {
    const { data } = await api.get('/entreprises', {
      params: { ...lazyParams.value, search: search.value || undefined },
    })
    entreprises.value = data.data
    totalRecords.value = data.meta?.total ?? data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les entreprises.', life: 3000 })
  } finally {
    loading.value = false
  }
}

function onPage(event: any) {
  lazyParams.value.page = event.page + 1
  fetchEntreprises()
}

function onSearch() {
  lazyParams.value.page = 1
  fetchEntreprises()
}

function statutColor(statut: string) {
  const colors: Record<string, string> = {
    prospect: 'warn',
    client: 'success',
    ancien_client: 'secondary',
  }
  return (colors[statut] ?? 'secondary') as any
}

onMounted(fetchEntreprises)
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Entreprises</h2>
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="onSearch" role="search" class="search-form">
        <label for="search-entreprises" class="sr-only">Rechercher une entreprise</label>
        <InputText
          id="search-entreprises"
          v-model="search"
          placeholder="Rechercher..."
        />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="onSearch" />
      </form>
    </div>

    <DataTable
      :value="entreprises"
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
      <Column field="raison_sociale" header="Raison sociale" />
      <Column field="nif" header="NIF" />
      <Column field="regime_fiscal" header="Régime fiscal" />
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="statutColor(data.statut)" />
        </template>
      </Column>
      <Column field="wilaya" header="Wilaya" />
      <Column field="telephone" header="Téléphone" />
    </DataTable>
  </div>
</template>

<style scoped>
.page-header { margin-bottom: 1rem; }
.page-toolbar { margin-bottom: 1rem; }
.search-form { display: flex; gap: 0.5rem; max-width: 20rem; }
</style>
