<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import { useMissions } from '@/composables/useMissions'
import { useEntreprises } from '@/composables/useEntreprises'
import { usePrestations } from '@/composables/usePrestations'
import { useUsers } from '@/composables/useUsers'
import { useExercices } from '@/composables/useExercices'
import { useAuthStore } from '@/stores/auth'
import type { Mission } from '@/types'
import type { MissionPayload } from '@/api/modules/missions'

const router = useRouter()
const confirm = useConfirm()
const auth = useAuthStore()
const {
  missions, loading, totalRecords, filters,
  fetchMissions, createMission, updateMission, deleteMission,
  onPage, onSearch, onSort, setExercice,
} = useMissions()

const { entreprises, fetchEntreprises } = useEntreprises()
const { prestations, fetchPrestations } = usePrestations()
const { users, fetchUsers } = useUsers()
const { exercices, exerciceCourant, fetchExercices, fetchExerciceCourant } = useExercices()

const search = ref('')
const exerciceSelectionne = ref<number | undefined>(undefined)

watch(search, (val) => onSearch(val))
watch(exerciceSelectionne, (val) => setExercice(val))
const dialogVisible = ref(false)
const saving = ref(false)
const dateDebut = ref<Date | null>(null)
const dateFin = ref<Date | null>(null)

// Edit dialog
const editDialogVisible = ref(false)
const editSaving = ref(false)
const editMission = ref<Mission | null>(null)
const editDateDebut = ref<Date | null>(null)
const editDateFin = ref<Date | null>(null)
const editForm = ref<{ collaborateur_ids: number[]; notes: string }>({
  collaborateur_ids: [],
  notes: '',
})

const exercicesOuverts = computed(() => exercices.value.filter((e: { statut: string }) => e.statut === 'ouvert'))

const form = ref<MissionPayload>({
  entreprise_id: 0,
  prestation_id: 0,
  exercice_id: undefined,
  date_debut: '',
  date_fin: '',
  collaborateur_ids: [],
  notes: '',
})

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  return d.toISOString().split('T')[0]
}

function openEdit(mission: Mission) {
  editMission.value = mission
  editDateDebut.value = new Date(mission.date_debut)
  editDateFin.value = mission.date_fin ? new Date(mission.date_fin) : null
  editForm.value.collaborateur_ids = mission.collaborateurs?.map((c: any) => c.id) ?? []
  editForm.value.notes = mission.notes ?? ''
  editDialogVisible.value = true
}

async function onSubmitEdit() {
  if (!editMission.value || !editDateDebut.value) return
  editSaving.value = true
  try {
    await updateMission(editMission.value.id, {
      date_debut: toIsoDate(editDateDebut.value),
      date_fin: toIsoDate(editDateFin.value),
      collaborateur_ids: editForm.value.collaborateur_ids,
      notes: editForm.value.notes,
    })
    editDialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    editSaving.value = false
  }
}

function openCreate() {
  form.value = {
    entreprise_id: 0,
    prestation_id: 0,
    exercice_id: exerciceCourant.value?.id,
    date_debut: '',
    date_fin: '',
    collaborateur_ids: [],
    notes: '',
  }
  dateDebut.value = null
  dateFin.value = null
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    await createMission({
      ...form.value,
      date_debut: toIsoDate(dateDebut.value),
      date_fin: toIsoDate(dateFin.value),
    })
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

function confirmDelete(mission: Mission) {
  confirm.require({
    message: `Supprimer la mission ${mission.reference} ?`,
    header: 'Confirmation',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteMission(mission.id),
  })
}

function goToDetail(mission: Mission) {
  router.push({ name: 'mission-detail', params: { id: mission.id } })
}

function statutSeverity(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger',
  }
  return map[statut] ?? 'secondary'
}

function statutLabel(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'En cours', terminee: 'Terminée', suspendue: 'Suspendue', annulee: 'Annulée',
  }
  return map[statut] ?? statut
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}


onMounted(async () => {
  // Collaborateur : pas d'accès aux référentiels — on charge uniquement ses missions
  if (!auth.isCollaborateur) {
    await fetchExerciceCourant()
    await fetchExercices()
    exerciceSelectionne.value = exerciceCourant.value?.id
    fetchEntreprises()
    fetchPrestations()
    fetchUsers()
  }
  fetchMissions()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Missions</h1>
      <Button v-if="!auth.isCollaborateur" label="Nouvelle mission" icon="pi pi-plus" aria-label="Créer une nouvelle mission" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <div class="toolbar-left">
        <label for="m-search" class="sr-only">Rechercher une mission</label>
        <InputText
          id="m-search"
          v-model="search"
          placeholder="Rechercher par reference ou client..."
          style="width: 22rem"
        />
      </div>
      <div v-if="!auth.isCollaborateur" class="toolbar-right">
        <label for="m-exercice" class="sr-only">Filtrer par exercice</label>
        <Select
          id="m-exercice"
          v-model="exerciceSelectionne"
          :options="exercices"
          optionLabel="annee"
          optionValue="id"
          placeholder="Tous les exercices"
          showClear
          style="width: 14rem"
        />
      </div>
    </div>

    <DataTable
      :value="missions"
      :loading="loading"
      :paginator="true"
      :rows="filters.per_page"
      :totalRecords="totalRecords"
      :lazy="true"
      :sortField="filters.sort_field"
      :sortOrder="filters.sort_direction === 'asc' ? 1 : -1"
      @page="onPage"
      @sort="onSort"
      dataKey="id"
      stripedRows
      removableSort
    >
      <Column header="#" style="width: 3rem; min-width: 3rem">
        <template #body="{ index }">
          <span class="row-number">{{ (filters.page - 1) * (filters.per_page ?? 15) + index + 1 }}</span>
        </template>
      </Column>
      <Column field="reference" header="N° de mission" sortable style="min-width: 10rem">
        <template #body="{ data }">
          <span class="mission-ref">{{ data.reference }}</span>
        </template>
      </Column>
      <Column header="Raison sociale" sortField="entreprise_id" sortable style="min-width: 11rem">
        <template #body="{ data }">{{ data.entreprise?.raison_sociale ?? '—' }}</template>
      </Column>
      <Column header="Prestation" style="min-width: 10rem">
        <template #body="{ data }">{{ data.prestation?.designation ?? '—' }}</template>
      </Column>
      <Column field="date_debut" header="Date de début" sortable style="min-width: 9rem">
        <template #body="{ data }">{{ formatDate(data.date_debut) }}</template>
      </Column>
      <Column field="date_fin" header="Date de fin" sortable class="col-date-fin" style="min-width: 9rem">
        <template #body="{ data }">{{ data.date_fin ? formatDate(data.date_fin) : '—' }}</template>
      </Column>
      <Column field="statut" header="Statut" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <Tag :value="statutLabel(data.statut)" :severity="statutSeverity(data.statut)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 9rem; min-width: 9rem">
        <template #body="{ data }">
          <div class="actions-cell">
            <Button
              icon="pi pi-arrow-right"
              text rounded severity="info" size="small"
              aria-label="Voir le détail de la mission"
              v-tooltip.top="'Voir le détail'"
              @click="goToDetail(data)"
            />
            <Button
              v-if="!auth.isCollaborateur"
              icon="pi pi-pencil"
              text rounded severity="secondary" size="small"
              aria-label="Modifier la mission"
              v-tooltip.top="'Modifier'"
              @click="openEdit(data)"
            />
            <Button
              v-if="!auth.isCollaborateur"
              icon="pi pi-times"
              text rounded severity="danger" size="small"
              aria-label="Supprimer la mission"
              v-tooltip.top="'Supprimer'"
              @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="dialogVisible"
      header="Nouvelle mission"
      :modal="true"
      :style="{ width: '32rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div v-if="auth.isAdmin" class="form-field">
          <label for="m-exercice-create">Exercice *</label>
          <Select
            id="m-exercice-create"
            v-model="form.exercice_id"
            :options="exercicesOuverts"
            optionLabel="annee"
            optionValue="id"
            placeholder="Exercice ouvert..."
            required
            fluid
            aria-label="Sélectionner l'exercice fiscal"
          />
        </div>

        <div class="form-field">
          <label for="m-entreprise">Entreprise *</label>
          <Select
            id="m-entreprise"
            v-model="form.entreprise_id"
            :options="entreprises"
            optionLabel="raison_sociale"
            optionValue="id"
            placeholder="Selectionner"
            filter
            required
            fluid
          />
        </div>

        <div class="form-field">
          <label for="m-prestation">Prestation *</label>
          <Select
            id="m-prestation"
            v-model="form.prestation_id"
            :options="prestations"
            optionLabel="designation"
            optionValue="id"
            placeholder="Selectionner"
            required
            fluid
          />
        </div>

        <div class="form-field">
          <label for="m-debut">Date debut *</label>
          <DatePicker id="m-debut" v-model="dateDebut" dateFormat="dd/mm/yy" required fluid />
        </div>

        <div class="form-field">
          <label for="m-fin">Date fin *</label>
          <DatePicker id="m-fin" v-model="dateFin" dateFormat="dd/mm/yy" required fluid />
        </div>

        <div class="form-field">
          <label for="m-collabs">Collaborateurs</label>
          <MultiSelect
            id="m-collabs"
            v-model="form.collaborateur_ids"
            :options="users"
            optionLabel="name"
            optionValue="id"
            placeholder="Selectionner des collaborateurs..."
            fluid
          />
        </div>

        <div class="form-field">
          <label for="m-notes">Notes</label>
          <Textarea id="m-notes" v-model="form.notes" rows="3" fluid />
        </div>

        <div class="form-actions">
          <Button label="Annuler" severity="secondary" @click="dialogVisible = false" type="button" />
          <Button label="Creer" type="submit" :loading="saving" />
        </div>
      </form>
    </Dialog>
    <!-- Dialog modifier mission -->
    <Dialog
      v-model:visible="editDialogVisible"
      header="Modifier la mission"
      :modal="true"
      :style="{ width: '32rem' }"
    >
      <form @submit.prevent="onSubmitEdit" class="dialog-form">
        <div class="form-field">
          <label for="e-debut">Date debut *</label>
          <DatePicker id="e-debut" v-model="editDateDebut" dateFormat="dd/mm/yy" required fluid />
        </div>

        <div class="form-field">
          <label for="e-fin">Date fin</label>
          <DatePicker id="e-fin" v-model="editDateFin" dateFormat="dd/mm/yy" fluid />
        </div>

        <div class="form-field">
          <label for="e-collabs">Collaborateurs</label>
          <MultiSelect
            id="e-collabs"
            v-model="editForm.collaborateur_ids"
            :options="users"
            optionLabel="name"
            optionValue="id"
            placeholder="Selectionner des collaborateurs..."
            fluid
          />
        </div>

        <div class="form-field">
          <label for="e-notes">Notes</label>
          <Textarea id="e-notes" v-model="editForm.notes" rows="3" fluid />
        </div>

        <div class="form-actions">
          <Button label="Annuler" severity="secondary" @click="editDialogVisible = false" type="button" />
          <Button label="Enregistrer" type="submit" :loading="editSaving" :disabled="!editDateDebut" />
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
.page-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.toolbar-left { display: flex; gap: 0.5rem; align-items: center; }
.toolbar-right { display: flex; gap: 0.5rem; align-items: center; }
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
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

/* ── Table ──────────────────────────────────────────────────────────────────── */
.row-number {
  font-size: 0.78rem;
  color: var(--p-text-muted-color);
  font-weight: 500;
}

.mission-ref {
  font-weight: 600;
  font-size: 0.875rem;
  font-variant-numeric: tabular-nums;
}

.actions-cell {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.15rem;
  align-items: center;
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
  :deep(.col-date-fin) { display: none; }
}

@media (max-width: 640px) {
  .page-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .toolbar-left,
  .toolbar-right {
    width: 100%;
  }
  .toolbar-left :deep(input),
  .toolbar-right :deep(.p-select) {
    width: 100% !important;
  }
}
</style>
