<script setup lang="ts">
import { ref, onMounted } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import { useExercices } from '@/composables/useExercices'
import { useAuthStore } from '@/stores/authStore'
import { exercicesApi } from '@/api/modules/exercices'
import { toIsoDate, parseIsoDate } from '@/utils/date'
import type { Exercice } from '@/types'
import type { ExercicePayload } from '@/api/modules/exercices'

const { exercices, loading, fetchExercices, createExercice, updateExercice } = useExercices()
const auth = useAuthStore()

const dialogVisible = ref(false)
const editMode = ref(false)
const saving = ref(false)
const downloadingId = ref<number | null>(null)

const emptyForm = (): ExercicePayload => ({
  annee: new Date().getFullYear(),
  date_ouverture: '',
  date_cloture: '',
  statut: 'ouvert',
})

const form = ref<ExercicePayload>(emptyForm())
const dateOuverture = ref<Date | null>(null)
const dateCloture = ref<Date | null>(null)
const editId = ref<number | null>(null)

const statutOptions = [
  { label: 'Ouvert', value: 'ouvert' },
  { label: 'Cloture', value: 'cloture' },
]

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}

function openCreate() {
  form.value = emptyForm()
  dateOuverture.value = null
  dateCloture.value = null
  editId.value = null
  editMode.value = false
  dialogVisible.value = true
}

function openEdit(exercice: Exercice) {
  form.value = {
    annee: exercice.annee,
    date_ouverture: exercice.date_ouverture,
    date_cloture: exercice.date_cloture,
    statut: exercice.statut,
  }
  dateOuverture.value = parseIsoDate(exercice.date_ouverture)
  dateCloture.value = parseIsoDate(exercice.date_cloture)
  editId.value = exercice.id
  editMode.value = true
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    // toIsoDate (utils/date) formate en LOCAL : pas de bascule J-1 via UTC
    const payload: ExercicePayload = {
      ...form.value,
      date_ouverture: toIsoDate(dateOuverture.value) ?? '',
      date_cloture: toIsoDate(dateCloture.value) ?? '',
    }
    if (editMode.value && editId.value) {
      await updateExercice(editId.value, payload)
    } else {
      await createExercice(payload)
    }
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

async function telechargerRapport(exercice: Exercice) {
  downloadingId.value = exercice.id
  try {
    const blob = await exercicesApi.rapportCloturePdf(exercice.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `rapport-cloture-${exercice.annee}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // erreur silencieuse
  } finally {
    downloadingId.value = null
  }
}

onMounted(fetchExercices)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Exercices fiscaux</h1>
      <Button label="Nouvel exercice" icon="pi pi-plus" @click="openCreate" />
    </div>

    <DataTable aria-label="Liste des exercices fiscaux"
      :value="exercices"
      :loading="loading"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
    >
      <Column field="annee" header="Annee" />
      <Column field="date_ouverture" header="Ouverture">
        <template #body="{ data }">
          {{ formatDate(data.date_ouverture) }}
        </template>
      </Column>
      <Column field="date_cloture" header="Cloture">
        <template #body="{ data }">
          {{ formatDate(data.date_cloture) }}
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="data.statut === 'ouvert' ? 'success' : 'secondary'" />
        </template>
      </Column>
      <Column header="Actions" style="width: 8rem">
        <template #body="{ data }">
          <div style="display: flex; gap: 0.25rem; align-items: center;">
            <Button
              icon="pi pi-pencil"
              text
              severity="info"
              aria-label="Modifier l'exercice"
              @click="openEdit(data)"
            />
            <Button
              v-if="auth.isAdmin"
              icon="pi pi-file-pdf"
              text
              severity="secondary"
              :loading="downloadingId === data.id"
              :aria-label="`Télécharger le rapport de clôture ${data.annee}`"
              :title="`Rapport de clôture ${data.annee}`"
              @click="telechargerRapport(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="dialogVisible"
      :header="editMode ? 'Modifier l\'exercice' : 'Nouvel exercice'"
      :modal="true"
      :style="{ width: '28rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div class="form-field">
          <label for="ex-annee">Annee *</label>
          <InputNumber id="ex-annee" v-model="form.annee" :useGrouping="false" required fluid />
        </div>

        <div class="form-field">
          <label for="ex-ouverture">Date ouverture *</label>
          <DatePicker id="ex-ouverture" v-model="dateOuverture" dateFormat="dd/mm/yy" required fluid />
        </div>

        <div class="form-field">
          <label for="ex-cloture">Date cloture *</label>
          <DatePicker id="ex-cloture" v-model="dateCloture" dateFormat="dd/mm/yy" required fluid />
        </div>

        <div class="form-field">
          <label for="ex-statut">Statut *</label>
          <Select id="ex-statut" v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value" fluid />
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
.dialog-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
</style>
