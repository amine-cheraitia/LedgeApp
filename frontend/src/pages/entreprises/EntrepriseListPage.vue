<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ConfirmDialog from 'primevue/confirmdialog'
import { useEntreprises } from '@/composables/useEntreprises'
import type { Entreprise } from '@/types'

const confirm = useConfirm()
const {
  entreprises, loading, totalRecords, filters,
  fetchEntreprises, createEntreprise, updateEntreprise, deleteEntreprise,
  onPage, onSearch,
} = useEntreprises()

const search = ref('')
const dialogVisible = ref(false)
const editMode = ref(false)
const saving = ref(false)

const emptyForm = (): Partial<Entreprise> => ({
  raison_sociale: '',
  nif: null,
  nis: null,
  num_rc: null,
  article_imposition: null,
  regime_fiscal: 'forfait',
  categorie: 'TPE',
  secteur_activite: null,
  adresse: null,
  ville: null,
  wilaya: null,
  telephone: null,
  email: null,
  contact_principal: null,
  statut: 'prospect',
  notes: null,
})

const form = ref<Partial<Entreprise>>(emptyForm())
const editId = ref<number | null>(null)

const regimeOptions = [
  { label: 'Forfait', value: 'forfait' },
  { label: 'Reel', value: 'reel' },
]

const categorieOptions = [
  { label: 'TPE', value: 'TPE' },
  { label: 'PME', value: 'PME' },
  { label: 'GE', value: 'GE' },
]

const statutOptions = [
  { label: 'Prospect', value: 'prospect' },
  { label: 'Client', value: 'client' },
  { label: 'Ancien client', value: 'ancien_client' },
]

function statutColor(statut: string) {
  const colors: Record<string, string> = {
    prospect: 'warn',
    client: 'success',
    ancien_client: 'secondary',
  }
  return (colors[statut] ?? 'secondary') as 'warn' | 'success' | 'secondary'
}

function openCreate() {
  form.value = emptyForm()
  editId.value = null
  editMode.value = false
  dialogVisible.value = true
}

function openEdit(entreprise: Entreprise) {
  form.value = { ...entreprise }
  editId.value = entreprise.id
  editMode.value = true
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    if (editMode.value && editId.value) {
      await updateEntreprise(editId.value, form.value)
    } else {
      await createEntreprise(form.value)
    }
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

function confirmDelete(entreprise: Entreprise) {
  confirm.require({
    message: `Supprimer "${entreprise.raison_sociale}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteEntreprise(entreprise.id),
  })
}

function handleSearch() {
  onSearch(search.value)
}

onMounted(fetchEntreprises)
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Entreprises</h2>
      <Button label="Nouvelle entreprise" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="handleSearch" role="search" class="search-form">
        <label for="search-entreprises" class="sr-only">Rechercher une entreprise</label>
        <InputText
          id="search-entreprises"
          v-model="search"
          placeholder="Rechercher..."
        />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="handleSearch" />
      </form>
    </div>

    <DataTable
      :value="entreprises"
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
      <Column field="raison_sociale" header="Raison sociale" />
      <Column field="nif" header="NIF" />
      <Column field="regime_fiscal" header="Regime fiscal" />
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="statutColor(data.statut)" />
        </template>
      </Column>
      <Column field="wilaya" header="Wilaya" />
      <Column field="telephone" header="Telephone" />
      <Column header="Actions" style="width: 8rem">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text severity="info" aria-label="Modifier" @click="openEdit(data)" />
          <Button icon="pi pi-trash" text severity="danger" aria-label="Supprimer" @click="confirmDelete(data)" />
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />

    <Dialog
      v-model:visible="dialogVisible"
      :header="editMode ? 'Modifier l\'entreprise' : 'Nouvelle entreprise'"
      :modal="true"
      :style="{ width: '36rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div class="form-field">
          <label for="ent-raison">Raison sociale *</label>
          <InputText id="ent-raison" v-model="form.raison_sociale" required fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-nif">NIF</label>
            <InputText id="ent-nif" v-model="form.nif" fluid />
          </div>
          <div class="form-field">
            <label for="ent-nis">NIS</label>
            <InputText id="ent-nis" v-model="form.nis" fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-rc">Num RC</label>
            <InputText id="ent-rc" v-model="form.num_rc" fluid />
          </div>
          <div class="form-field">
            <label for="ent-art">Article imposition</label>
            <InputText id="ent-art" v-model="form.article_imposition" fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-regime">Regime fiscal *</label>
            <Select id="ent-regime" v-model="form.regime_fiscal" :options="regimeOptions" optionLabel="label" optionValue="value" fluid />
          </div>
          <div class="form-field">
            <label for="ent-categorie">Categorie *</label>
            <Select id="ent-categorie" v-model="form.categorie" :options="categorieOptions" optionLabel="label" optionValue="value" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="ent-statut">Statut *</label>
          <Select id="ent-statut" v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value" fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-email">Email</label>
            <InputText id="ent-email" v-model="form.email" type="email" fluid />
          </div>
          <div class="form-field">
            <label for="ent-tel">Telephone</label>
            <InputText id="ent-tel" v-model="form.telephone" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="ent-adresse">Adresse</label>
          <InputText id="ent-adresse" v-model="form.adresse" fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-ville">Ville</label>
            <InputText id="ent-ville" v-model="form.ville" fluid />
          </div>
          <div class="form-field">
            <label for="ent-wilaya">Wilaya</label>
            <InputText id="ent-wilaya" v-model="form.wilaya" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="ent-contact">Contact principal</label>
          <InputText id="ent-contact" v-model="form.contact_principal" fluid />
        </div>

        <div class="form-field">
          <label for="ent-notes">Notes</label>
          <Textarea id="ent-notes" v-model="form.notes" rows="3" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" :label="editMode ? 'Modifier' : 'Creer'" icon="pi pi-check" :loading="saving" />
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
