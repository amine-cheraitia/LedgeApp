<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Select from 'primevue/select'
import { portailApi } from '@/api/modules/portail'
import { formatDA } from '@/utils/currency'
import type { Facture } from '@/types'

const toast = useToast()

const factures = ref<Facture[]>([])
const loading = ref(false)
const totalRecords = ref(0)
const page = ref(1)
const filtreStatut = ref<string | null>(null)
const telecharging = ref<number | null>(null)

const statutOptions = [
  { label: 'Tous les statuts', value: null },
  { label: 'En attente', value: 'en_attente' },
  { label: 'Partiel', value: 'partiel' },
  { label: 'Soldé', value: 'solde' },
]

async function fetchFactures() {
  loading.value = true
  try {
    const res = await portailApi.getFactures({
      page: page.value,
      per_page: 15,
      statut_paiement: filtreStatut.value ?? undefined,
    })
    factures.value = res.data
    totalRecords.value = res.meta.total
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les factures.', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function telechargerPdf(facture: Facture) {
  telecharging.value = facture.id
  try {
    await portailApi.telechargerFacturePdf(facture.id, facture.numero)
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de télécharger le PDF.', life: 3000 })
  } finally {
    telecharging.value = null
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchFactures()
}

function statutSeverity(statut: string) {
  const map: Record<string, string> = {
    en_attente: 'warn',
    partiel: 'info',
    solde: 'success',
  }
  return (map[statut] ?? 'secondary') as 'warn' | 'info' | 'success' | 'secondary'
}

function statutLabel(statut: string) {
  const map: Record<string, string> = {
    en_attente: 'En attente',
    partiel: 'Partiel',
    solde: 'Soldé',
  }
  return map[statut] ?? statut
}

onMounted(fetchFactures)
</script>

<template>
  <div>
    <header class="page-header">
      <h1 id="page-title">Mes factures</h1>
    </header>

    <div class="page-toolbar">
      <label for="filtre-statut" class="sr-only">Filtrer par statut</label>
      <Select
        id="filtre-statut"
        v-model="filtreStatut"
        :options="statutOptions"
        option-label="label"
        option-value="value"
        aria-label="Filtrer par statut de paiement"
        @change="fetchFactures"
      />
    </div>

    <DataTable aria-label="Mes factures"
      :value="factures"
      :loading="loading"
      :paginator="true"
      :rows="15"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
      aria-labelledby="page-title"
    >
      <Column header="Numéro">
        <template #body="{ data }">
          <span class="cell-mono">{{ data.numero }}</span>
        </template>
      </Column>
      <Column field="date_facture" header="Date" />
      <Column field="date_echeance" header="Échéance" />
      <Column header="Montant TTC">
        <template #body="{ data }">
          <span class="cell-mono">{{ formatDA(data.montant_ttc) }}</span>
        </template>
      </Column>
      <Column header="Restant dû">
        <template #body="{ data }">
          <span class="cell-mono" :class="{ 'text-danger': data.montant_restant > 0 }">
            {{ formatDA(data.montant_restant) }}
          </span>
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="statutLabel(data.statut_paiement)" :severity="statutSeverity(data.statut_paiement)" />
        </template>
      </Column>
      <Column header="PDF" style="width: 5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-file-pdf"
            text
            severity="danger"
            :loading="telecharging === data.id"
            :aria-label="`Télécharger la facture ${data.numero}`"
            @click="telechargerPdf(data)"
          />
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 1.25rem;
}

/* Taille/graisse : base h1 globale (main.css) */
.page-header h1 {
  margin: 0;
}

.page-toolbar {
  margin-bottom: 1rem;
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.text-danger {
  color: var(--p-red-500, #ef4444);
  font-weight: 600;
}

/* Chiffres en mono, regle charte */
.cell-mono {
  font-family: var(--ledge-ff-mono);
  letter-spacing: var(--ledge-letter-spacing-mono);
}
</style>
