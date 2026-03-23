<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import api from '@/api/client'
import type { Exercice } from '@/types'

const toast = useToast()
const exercices = ref<Exercice[]>([])
const loading = ref(false)

async function fetchExercices() {
  loading.value = true
  try {
    const { data } = await api.get('/exercices')
    exercices.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les exercices.', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(fetchExercices)
</script>

<template>
  <div>
    <h2>Exercices fiscaux</h2>

    <DataTable
      :value="exercices"
      :loading="loading"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
      class="mt-1"
    >
      <Column field="annee" header="Année" />
      <Column field="date_ouverture" header="Ouverture">
        <template #body="{ data }">
          {{ new Date(data.date_ouverture).toLocaleDateString('fr-FR') }}
        </template>
      </Column>
      <Column field="date_cloture" header="Clôture">
        <template #body="{ data }">
          {{ new Date(data.date_cloture).toLocaleDateString('fr-FR') }}
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="data.statut === 'ouvert' ? 'success' : 'secondary'" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.mt-1 { margin-top: 1rem; }
</style>
