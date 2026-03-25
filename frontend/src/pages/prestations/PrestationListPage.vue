<script setup lang="ts">
import { onMounted } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { usePrestations } from '@/composables/usePrestations'

const { prestations, loading, fetchPrestations } = usePrestations()

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
      <Column field="designation" header="Designation" />
      <Column field="tarif_initial" header="Tarif initial (DA)">
        <template #body="{ data }">
          {{ Number(data.tarif_initial).toLocaleString('fr-FR') }}
        </template>
      </Column>
      <Column field="duree_mois" header="Duree (mois)" />
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
