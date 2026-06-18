<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'
import { useTvaTaux } from '@/composables/useTvaTaux'
import type { TvaTaux } from '@/types'
import type { TvaTauxPayload } from '@/api/modules/referentiels'

const confirm = useConfirm()
const { taux, loading, fetchTaux, createTaux, updateTaux, deleteTaux } = useTvaTaux()

const typeOptions = [
  { label: 'Standard (19%)', value: 'standard' },
  { label: 'Exonere (0%)', value: 'exonere' },
]

interface FormState {
  taux: number
  designation: string
  type: 'standard' | 'exonere'
  date_debut: Date | null
  date_fin: Date | null
  actif: boolean
}

function emptyForm(): FormState {
  return { taux: 19, designation: '', type: 'standard', date_debut: new Date(), date_fin: null, actif: true }
}

function toIso(d: Date | null): string | null {
  if (!d) return null
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function buildPayload(f: FormState): TvaTauxPayload {
  return {
    taux: f.taux,
    designation: f.designation,
    type: f.type,
    date_debut: toIso(f.date_debut) ?? '',
    date_fin: toIso(f.date_fin),
    actif: f.actif,
  }
}

// ---------- Dialog (creation / modification) ----------
const dialogVisible = ref(false)
const saving = ref(false)
const mode = ref<'create' | 'edit'>('create')
const editId = ref<number | null>(null)
const form = ref<FormState>(emptyForm())

const dialogTitle = computed(() => (mode.value === 'create' ? 'Nouveau taux de TVA' : 'Modifier le taux de TVA'))

function openCreate() {
  mode.value = 'create'
  editId.value = null
  form.value = emptyForm()
  dialogVisible.value = true
}

function openEdit(t: TvaTaux) {
  mode.value = 'edit'
  editId.value = t.id
  form.value = {
    taux: Number(t.taux),
    designation: t.designation,
    type: t.type,
    date_debut: t.date_debut ? new Date(t.date_debut) : null,
    date_fin: t.date_fin ? new Date(t.date_fin) : null,
    actif: t.actif,
  }
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    if (mode.value === 'create') {
      await createTaux(buildPayload(form.value))
    } else if (editId.value !== null) {
      await updateTaux(editId.value, buildPayload(form.value))
    }
    dialogVisible.value = false
  } catch {
    // erreurs de validation gerees par l'API (toast)
  } finally {
    saving.value = false
  }
}

// ---------- Suppression ----------
function confirmDelete(t: TvaTaux) {
  confirm.require({
    message: `Supprimer le taux "${t.designation}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteTaux(t.id),
  })
}

function formatDate(s: string | null) {
  return s ? new Date(s).toLocaleDateString('fr-FR') : '—'
}

// Statut reel : en vigueur / cloture / a venir / inactif (selon les dates, pas seulement le flag actif)
function statutTaux(t: TvaTaux): { label: string; severity: string } {
  const today = toIso(new Date()) ?? ''
  if (!t.actif) return { label: 'Inactif', severity: 'secondary' }
  if (t.date_debut > today) return { label: 'A venir', severity: 'warn' }
  if (t.date_fin && t.date_fin < today) return { label: 'Cloture', severity: 'secondary' }
  return { label: 'En vigueur', severity: 'success' }
}

onMounted(fetchTaux)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Taux de TVA</h1>
      <Button label="Nouveau taux" icon="pi pi-plus" aria-label="Creer un taux de TVA" @click="openCreate" />
    </div>

    <p class="hint" role="note">
      La TVA est historisee : la valeur d'une facture est fixee par sa date. Pour changer un taux, creez-en un nouveau
      avec sa date de debut et cloturez l'ancien (date de fin).
    </p>

    <DataTable :value="taux" :loading="loading" dataKey="id" stripedRows>
      <Column header="Categorie" style="width: 11rem">
        <template #body="{ data }">
          <Tag :value="data.type === 'exonere' ? 'Exonere' : 'Standard'" :severity="data.type === 'exonere' ? 'info' : 'success'" />
        </template>
      </Column>
      <Column header="Taux" style="width: 7rem">
        <template #body="{ data }">{{ Number(data.taux) }} %</template>
      </Column>
      <Column field="designation" header="Designation" />
      <Column header="Periode" style="width: 16rem">
        <template #body="{ data }">{{ formatDate(data.date_debut) }} &rarr; {{ data.date_fin ? formatDate(data.date_fin) : 'en cours' }}</template>
      </Column>
      <Column header="Statut" style="width: 9rem">
        <template #body="{ data }">
          <Tag :value="statutTaux(data).label" :severity="statutTaux(data).severity" />
        </template>
      </Column>
      <Column header="Actions" style="width: 8rem">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded severity="secondary" aria-label="Modifier le taux" v-tooltip.top="'Modifier'" @click="openEdit(data)" />
          <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Supprimer le taux" v-tooltip.top="'Supprimer'" @click="confirmDelete(data)" />
        </template>
      </Column>
    </DataTable>

    <Dialog v-model:visible="dialogVisible" :header="dialogTitle" :modal="true" :style="{ width: '34rem' }">
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div class="form-row">
          <div class="form-field">
            <label for="t-type">Categorie *</label>
            <Select id="t-type" v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" fluid />
          </div>
          <div class="form-field" style="flex: 0 0 9rem">
            <label for="t-taux">Taux (%) *</label>
            <InputNumber id="t-taux" v-model="form.taux" :min="0" :max="100" :maxFractionDigits="2" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="t-designation">Designation *</label>
          <InputText id="t-designation" v-model="form.designation" maxlength="255" required fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="t-debut">Date de debut *</label>
            <DatePicker id="t-debut" v-model="form.date_debut" dateFormat="yy-mm-dd" showIcon fluid />
          </div>
          <div class="form-field">
            <label for="t-fin">Date de fin</label>
            <DatePicker id="t-fin" v-model="form.date_fin" dateFormat="yy-mm-dd" showIcon showButtonBar fluid />
          </div>
        </div>

        <div class="form-field form-field--inline">
          <label for="t-actif">Actif</label>
          <ToggleSwitch id="t-actif" v-model="form.actif" aria-label="Taux actif" />
        </div>

        <div class="form-actions">
          <Button label="Annuler" severity="secondary" type="button" @click="dialogVisible = false" />
          <Button :label="mode === 'create' ? 'Creer' : 'Enregistrer'" type="submit" :loading="saving" :disabled="!form.designation || !form.date_debut" />
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
.hint {
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
  margin: 0 0 1.25rem;
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
