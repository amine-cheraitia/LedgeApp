<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import ConfirmDialog from 'primevue/confirmdialog'
import { useDevis } from '@/composables/useDevis'
import { useEntreprises } from '@/composables/useEntreprises'
import { usePrestations } from '@/composables/usePrestations'
import type { Devis } from '@/types'

const confirm = useConfirm()
const {
  devisList, loading, totalRecords, filters,
  fetchDevis, createDevis, updateDevisStatut, deleteDevis,
  onPage, onSearch,
} = useDevis()

const { entreprises, fetchEntreprises } = useEntreprises()
const { prestations, fetchPrestations } = usePrestations()

const search = ref('')
const dialogVisible = ref(false)
const saving = ref(false)

const form = reactive({
  entreprise_id: null as number | null,
  prestation_id: null as number | null,
  date_devis: null as Date | null,
  date_validite: null as Date | null,
  notes: '',
})

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  return d.toISOString().split('T')[0]
}

function formatMontant(v: number) {
  return Number(v).toLocaleString('fr-FR')
}

function statutColor(statut: string) {
  const map: Record<string, string> = {
    brouillon: 'secondary',
    envoye: 'info',
    accepte: 'success',
    refuse: 'danger',
    expire: 'warn',
  }
  return (map[statut] ?? 'secondary') as 'secondary' | 'info' | 'success' | 'danger' | 'warn'
}

function openCreate() {
  form.entreprise_id = null
  form.prestation_id = null
  form.date_devis = null
  form.date_validite = null
  form.notes = ''
  dialogVisible.value = true
}

async function onSubmit() {
  if (!form.entreprise_id || !form.prestation_id || !form.date_devis || !form.date_validite) return
  saving.value = true
  try {
    await createDevis({
      entreprise_id: form.entreprise_id,
      prestation_id: form.prestation_id,
      date_devis: toIsoDate(form.date_devis),
      date_validite: toIsoDate(form.date_validite),
      notes: form.notes || null,
    })
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

function confirmDelete(devis: Devis) {
  confirm.require({
    message: `Supprimer le devis "${devis.numero}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteDevis(devis.id),
  })
}

function handleSearch() {
  onSearch(search.value)
}

onMounted(() => {
  fetchDevis()
  fetchEntreprises()
  fetchPrestations()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Devis</h2>
      <Button label="Nouveau devis" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="handleSearch" role="search" class="search-form">
        <label for="search-devis" class="sr-only">Rechercher un devis</label>
        <InputText id="search-devis" v-model="search" placeholder="Rechercher par numero..." />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="handleSearch" />
      </form>
    </div>

    <DataTable
      :value="devisList"
      :loading="loading"
      :paginator="true"
      :rows="filters.per_page"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
    >
      <Column field="numero" header="Numero" />
      <Column header="Entreprise">
        <template #body="{ data }">
          {{ data.entreprise?.raison_sociale ?? '-' }}
        </template>
      </Column>
      <Column header="Prestation">
        <template #body="{ data }">
          {{ data.prestation?.designation ?? '-' }}
        </template>
      </Column>
      <Column field="date_devis" header="Date" />
      <Column header="Prix HT (DA)">
        <template #body="{ data }">
          {{ formatMontant(data.prix_ht) }}
        </template>
      </Column>
      <Column header="Montant TTC (DA)">
        <template #body="{ data }">
          {{ formatMontant(data.montant_ttc) }}
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="statutColor(data.statut)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 10rem">
        <template #body="{ data }">
          <Button
            v-if="data.statut === 'brouillon'"
            icon="pi pi-send"
            text
            severity="info"
            aria-label="Envoyer"
            @click="updateDevisStatut(data.id, 'envoye')"
          />
          <Button
            v-if="data.statut === 'brouillon'"
            icon="pi pi-trash"
            text
            severity="danger"
            aria-label="Supprimer"
            @click="confirmDelete(data)"
          />
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />

    <Dialog
      v-model:visible="dialogVisible"
      header="Nouveau devis"
      :modal="true"
      :style="{ width: '36rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div class="form-field">
          <label for="dv-entreprise">Entreprise *</label>
          <Select
            id="dv-entreprise"
            v-model="form.entreprise_id"
            :options="entreprises"
            optionLabel="raison_sociale"
            optionValue="id"
            placeholder="Selectionner..."
            filter
            fluid
          />
        </div>

        <div class="form-field">
          <label for="dv-prestation">Prestation *</label>
          <Select
            id="dv-prestation"
            v-model="form.prestation_id"
            :options="prestations"
            optionLabel="designation"
            optionValue="id"
            placeholder="Selectionner..."
            fluid
          />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="dv-date">Date devis *</label>
            <DatePicker id="dv-date" v-model="form.date_devis" dateFormat="dd/mm/yy" fluid />
          </div>
          <div class="form-field">
            <label for="dv-validite">Date validite *</label>
            <DatePicker id="dv-validite" v-model="form.date_validite" dateFormat="dd/mm/yy" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="dv-notes">Notes</label>
          <Textarea id="dv-notes" v-model="form.notes" rows="2" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" label="Creer" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.page-toolbar { margin-bottom: 1rem; }
.search-form { display: flex; gap: 0.5rem; max-width: 20rem; }
.dialog-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.form-row { display: flex; gap: 0.75rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
