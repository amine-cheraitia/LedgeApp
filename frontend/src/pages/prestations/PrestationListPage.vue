<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { usePrestations } from '@/composables/usePrestations'
import type { Prestation } from '@/types'
import type { PrestationPayload } from '@/api/modules/prestations'

const confirm = useConfirm()
const { prestations, loading, fetchPrestations, createPrestation, updatePrestation, deletePrestation } = usePrestations()

// ---------- Dialog création ----------
const dialogVisible = ref(false)
const saving = ref(false)
const form = ref<PrestationPayload>({
  code: '',
  designation: '',
  tarif_initial: 0,
  duree_mois: 12,
  description: '',
  actif: true,
})

function openCreate() {
  form.value = { code: '', designation: '', tarif_initial: 0, duree_mois: 12, description: '', actif: true }
  dialogVisible.value = true
}

async function onSubmitCreate() {
  saving.value = true
  try {
    await createPrestation(form.value)
    dialogVisible.value = false
  } catch (err: any) {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

// ---------- Dialog modification ----------
const editDialogVisible = ref(false)
const editSaving = ref(false)
const editPrestation = ref<Prestation | null>(null)
const editForm = ref<Partial<PrestationPayload>>({})

function openEdit(prestation: Prestation) {
  editPrestation.value = prestation
  editForm.value = {
    code: prestation.code,
    designation: prestation.designation,
    tarif_initial: prestation.tarif_initial,
    duree_mois: prestation.duree_mois,
    description: prestation.description ?? '',
    actif: prestation.actif,
  }
  editDialogVisible.value = true
}

async function onSubmitEdit() {
  if (!editPrestation.value) return
  editSaving.value = true
  try {
    await updatePrestation(editPrestation.value.id, editForm.value)
    editDialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    editSaving.value = false
  }
}

// ---------- Suppression ----------
function confirmDelete(prestation: Prestation) {
  confirm.require({
    message: `Supprimer la prestation "${prestation.designation}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deletePrestation(prestation.id),
  })
}

// ---------- Helpers ----------
function formatMontant(v: number) {
  return Number(v).toLocaleString('fr-FR') + ' DA'
}

onMounted(fetchPrestations)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Prestations</h1>
      <Button label="Nouvelle prestation" icon="pi pi-plus" aria-label="Creer une prestation" @click="openCreate" />
    </div>

    <DataTable aria-label="Liste des prestations"
      :value="prestations"
      :loading="loading"
      dataKey="id"
      stripedRows
    >
      <Column field="code" header="Code" style="width: 8rem" />
      <Column field="designation" header="Designation" />
      <Column header="Tarif initial" style="width: 12rem">
        <template #body="{ data }">{{ formatMontant(data.tarif_initial) }}</template>
      </Column>
      <Column field="duree_mois" header="Duree (mois)" style="width: 9rem" />
      <Column header="Actif" style="width: 7rem">
        <template #body="{ data }">
          <Tag :value="data.actif ? 'Actif' : 'Inactif'" :severity="data.actif ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column header="Actions" style="width: 8rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-pencil"
            text
            rounded
            severity="secondary"
            aria-label="Modifier la prestation"
            v-tooltip.top="'Modifier'"
            @click="openEdit(data)"
          />
          <Button
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            aria-label="Supprimer la prestation"
            v-tooltip.top="'Supprimer'"
            @click="confirmDelete(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog création -->
    <Dialog
      v-model:visible="dialogVisible"
      header="Nouvelle prestation"
      :modal="true"
      :style="{ width: '32rem' }"
    >
      <form @submit.prevent="onSubmitCreate" class="dialog-form">
        <div class="form-row">
          <div class="form-field" style="flex: 0 0 8rem">
            <label for="p-code">Code *</label>
            <InputText id="p-code" v-model="form.code" maxlength="20" required fluid />
          </div>
          <div class="form-field">
            <label for="p-designation">Designation *</label>
            <InputText id="p-designation" v-model="form.designation" required fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="p-tarif">Tarif initial (DA) *</label>
            <InputNumber id="p-tarif" v-model="form.tarif_initial" :min="0" mode="decimal" fluid />
          </div>
          <div class="form-field">
            <label for="p-duree">Duree (mois) *</label>
            <InputNumber id="p-duree" v-model="form.duree_mois" :min="1" :max="120" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="p-desc">Description</label>
          <Textarea id="p-desc" v-model="form.description" rows="3" fluid />
        </div>

        <div class="form-field form-field--inline">
          <label for="p-actif">Actif</label>
          <ToggleSwitch id="p-actif" v-model="form.actif" aria-label="Prestation active" />
        </div>

        <div class="form-actions">
          <Button label="Annuler" severity="secondary" @click="dialogVisible = false" type="button" />
          <Button label="Creer" type="submit" :loading="saving" :disabled="!form.code || !form.designation" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog modification -->
    <Dialog
      v-model:visible="editDialogVisible"
      header="Modifier la prestation"
      :modal="true"
      :style="{ width: '32rem' }"
    >
      <form @submit.prevent="onSubmitEdit" class="dialog-form">
        <div class="form-row">
          <div class="form-field" style="flex: 0 0 8rem">
            <label for="e-code">Code *</label>
            <InputText id="e-code" v-model="editForm.code" maxlength="20" required fluid />
          </div>
          <div class="form-field">
            <label for="e-designation">Designation *</label>
            <InputText id="e-designation" v-model="editForm.designation" required fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="e-tarif">Tarif initial (DA) *</label>
            <InputNumber id="e-tarif" v-model="editForm.tarif_initial" :min="0" mode="decimal" fluid />
          </div>
          <div class="form-field">
            <label for="e-duree">Duree (mois) *</label>
            <InputNumber id="e-duree" v-model="editForm.duree_mois" :min="1" :max="120" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="e-desc">Description</label>
          <Textarea id="e-desc" v-model="editForm.description" rows="3" fluid />
        </div>

        <div class="form-field form-field--inline">
          <label for="e-actif">Actif</label>
          <ToggleSwitch id="e-actif" v-model="editForm.actif" aria-label="Prestation active" />
        </div>

        <div class="form-actions">
          <Button label="Annuler" severity="secondary" @click="editDialogVisible = false" type="button" />
          <Button label="Enregistrer" type="submit" :loading="editSaving" />
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
  margin-bottom: 1.5rem;
}
.dialog-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding-top: 0.5rem;
}
.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
}
.form-field--inline {
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
}
.form-row {
  display: flex;
  gap: 0.75rem;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
