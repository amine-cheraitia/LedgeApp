<script setup lang="ts">
import { ref, onMounted } from 'vue'
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
import ConfirmDialog from 'primevue/confirmdialog'
import { useMissions } from '@/composables/useMissions'
import { useEntreprises } from '@/composables/useEntreprises'
import { usePrestations } from '@/composables/usePrestations'
import { useUsers } from '@/composables/useUsers'
import type { Mission } from '@/types'
import type { MissionPayload } from '@/api/modules/missions'

const router = useRouter()
const confirm = useConfirm()
const {
  missions, loading, totalRecords,
  fetchMissions, createMission, deleteMission,
  onPage, onSearch,
} = useMissions()

const { entreprises, fetchEntreprises } = useEntreprises()
const { prestations, fetchPrestations } = usePrestations()
const { users, fetchUsers } = useUsers()

const search = ref('')
const dialogVisible = ref(false)
const saving = ref(false)
const dateDebut = ref<Date | null>(null)
const dateFin = ref<Date | null>(null)

const form = ref<MissionPayload>({
  entreprise_id: 0,
  prestation_id: 0,
  date_debut: '',
  date_fin: '',
  collaborateur_ids: [],
  notes: '',
})

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  return d.toISOString().split('T')[0]
}

function openCreate() {
  form.value = { entreprise_id: 0, prestation_id: 0, date_debut: '', date_fin: '', collaborateur_ids: [], notes: '' }
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

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}

function formatMontant(v: number) {
  return new Intl.NumberFormat('fr-DZ').format(v) + ' DA'
}

function doSearch() {
  onSearch(search.value)
}

onMounted(() => {
  fetchMissions()
  fetchEntreprises()
  fetchPrestations()
  fetchUsers()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Missions</h1>
      <Button label="Nouvelle mission" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="search-bar">
      <InputText v-model="search" placeholder="Rechercher par reference..." @keyup.enter="doSearch" fluid />
      <Button icon="pi pi-search" @click="doSearch" aria-label="Rechercher" />
    </div>

    <DataTable
      :value="missions"
      :loading="loading"
      :paginator="true"
      :rows="15"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      stripedRows
    >
      <Column field="reference" header="Reference" />
      <Column header="Entreprise">
        <template #body="{ data }">{{ data.entreprise?.raison_sociale ?? '-' }}</template>
      </Column>
      <Column header="Prestation">
        <template #body="{ data }">{{ data.prestation?.designation ?? '-' }}</template>
      </Column>
      <Column header="Prix HT">
        <template #body="{ data }">{{ formatMontant(data.prix_ht) }}</template>
      </Column>
      <Column header="Debut">
        <template #body="{ data }">{{ formatDate(data.date_debut) }}</template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="statutSeverity(data.statut)" />
        </template>
      </Column>
      <Column header="Actions">
        <template #body="{ data }">
          <Button icon="pi pi-eye" text rounded @click="goToDetail(data)" aria-label="Voir detail" />
          <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" aria-label="Supprimer" />
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />

    <Dialog
      v-model:visible="dialogVisible"
      header="Nouvelle mission"
      :modal="true"
      :style="{ width: '32rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
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
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.search-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  max-width: 400px;
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
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
</style>
