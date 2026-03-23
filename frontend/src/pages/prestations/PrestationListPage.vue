<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import api from '@/api/client'
import type { Prestation } from '@/types'

const toast = useToast()
const prestations = ref<Prestation[]>([])
const loading = ref(false)

async function fetchPrestations() {
  loading.value = true
  try {
    const { data } = await api.get('/prestations')
    prestations.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les prestations.', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(fetchPrestations)
</script>

<template>
  <div>
    <h2>Prestations</h2>

    <DataTable
      :value="prestations"
      :loading="loading"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
      class="mt-1"
    >
      <Column field="code" header="Code" />
      <Column field="designation" header="Désignation" />
      <Column field="tarif_initial" header="Tarif initial (DA)">
        <template #body="{ data }">
          {{ Number(data.tarif_initial).toLocaleString('fr-FR') }}
        </template>
      </Column>
      <Column field="duree_mois" header="Durée (mois)" />
      <Column header="Actif">
        <template #body="{ data }">
          <Tag :value="data.actif ? 'Oui' : 'Non'" :severity="data.actif ? 'success' : 'secondary'" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.mt-1 { margin-top: 1rem; }
</style>
