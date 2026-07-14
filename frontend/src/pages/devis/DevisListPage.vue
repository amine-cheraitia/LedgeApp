<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
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
import { useDevis } from '@/composables/useDevis'
import { useEntreprises } from '@/composables/useEntreprises'
import { usePrestations } from '@/composables/usePrestations'
import { useUsers } from '@/composables/useUsers'
import { useExercices } from '@/composables/useExercices'
import { useTvaTaux } from '@/composables/useTvaTaux'
import { useAuthStore } from '@/stores/authStore'
import type { Devis } from '@/types'

const confirm = useConfirm()
const auth = useAuthStore()
const {
  devisList, loading, totalRecords, filters,
  fetchDevis, createDevis, updateDevis, envoyerDevis, accepterDevis, refuserDevis,
  convertirDevisEnMission, telechargerPdf, deleteDevis, onPage, onSearch, onSort, setExercice,
} = useDevis()

const { entreprises, fetchEntreprises } = useEntreprises()
const { prestations, fetchPrestations } = usePrestations()
const { users, fetchUsers } = useUsers()
const { exercices, exerciceCourant, fetchExercices, fetchExerciceCourant } = useExercices()
const { fetchTaux: fetchTvaTaux, tauxEnVigueur } = useTvaTaux()

const search = ref('')
const exerciceSelectionne = ref<number | undefined>(undefined)

watch(search, (val) => onSearch(val))
watch(exerciceSelectionne, (val) => setExercice(val))

// Dialog creation devis
const exercicesOuverts = computed(() => exercices.value.filter((e: { statut: string }) => e.statut === 'ouvert'))

const dialogVisible = ref(false)
const saving = ref(false)
const form = reactive({
  entreprise_id: null as number | null,
  prestation_id: null as number | null,
  exercice_id: null as number | null,
  date_devis: null as Date | null,
  date_validite: null as Date | null,
  type_tva: 'standard' as 'standard' | 'exonere',
})

const tvaOptions = computed(() => {
  const std = tauxEnVigueur('standard', form.date_devis)
  const exo = tauxEnVigueur('exonere', form.date_devis)
  return [
    { label: std !== null ? `Standard (${std} %)` : 'Standard', value: 'standard' },
    { label: exo !== null ? `Exonere (${exo} %)` : 'Exonere (0 %)', value: 'exonere' },
  ]
})

// Dialog modification devis
const editDevisVisible = ref(false)
const editDevisSaving = ref(false)
const editDevisId = ref<number | null>(null)
const editDevisNumero = ref('')
const editDevisForm = reactive({
  entreprise_id: null as number | null,
  prestation_id: null as number | null,
  date_devis: null as Date | null,
  date_validite: null as Date | null,
})

// Dialog conversion en mission
const conversionVisible = ref(false)
const converting = ref(false)
const conversionDevisId = ref<number | null>(null)
const conversionDevisNumero = ref('')
const conversionForm = reactive({
  date_debut: null as Date | null,
  date_fin: null as Date | null,
  collaborateur_ids: [] as number[],
})

// Auto-fill date_validite = date_devis + 2 mois (création)
watch(() => form.date_devis, (newDate) => {
  if (newDate) {
    const d = new Date(newDate)
    d.setMonth(d.getMonth() + 2)
    form.date_validite = d
  }
})

// Auto-fill date_validite = date_devis + 2 mois (modification)
watch(() => editDevisForm.date_devis, (newDate) => {
  if (newDate) {
    const d = new Date(newDate)
    d.setMonth(d.getMonth() + 2)
    editDevisForm.date_validite = d
  }
})

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  // Date LOCALE (pas toISOString/UTC) : evite le decalage d'un jour en UTC+1
  // et garantit que la date envoyee == la date affichee == la date du taux calcule.
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
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
  form.exercice_id = exerciceCourant.value?.id ?? null
  // Date du jour par defaut (comme la facture) : le taux courant s'affiche d'emblee
  // et l'echeance (date_validite) est auto-remplie par le watcher.
  form.date_devis = new Date()
  form.date_validite = null
  form.type_tva = 'standard'
  dialogVisible.value = true
}

async function onSubmit() {
  if (!form.entreprise_id || !form.prestation_id || !form.date_devis || !form.date_validite) return
  saving.value = true
  try {
    await createDevis({
      entreprise_id: form.entreprise_id,
      prestation_id: form.prestation_id,
      exercice_id: form.exercice_id ?? undefined,
      date_devis: toIsoDate(form.date_devis),
      date_validite: toIsoDate(form.date_validite),
      type_tva: form.type_tva,
    })
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

function openEditDevis(devis: Devis) {
  editDevisId.value = devis.id
  editDevisNumero.value = devis.numero
  editDevisForm.entreprise_id = devis.entreprise?.id ?? devis.entreprise_id ?? null
  editDevisForm.prestation_id = devis.prestation?.id ?? devis.prestation_id ?? null
  editDevisForm.date_devis = devis.date_devis ? new Date(devis.date_devis) : null
  editDevisForm.date_validite = devis.date_validite ? new Date(devis.date_validite) : null
  editDevisVisible.value = true
}

async function onSubmitEditDevis() {
  if (!editDevisId.value) return
  editDevisSaving.value = true
  try {
    await updateDevis(editDevisId.value, {
      entreprise_id: editDevisForm.entreprise_id ?? undefined,
      prestation_id: editDevisForm.prestation_id ?? undefined,
      date_devis: editDevisForm.date_devis ? toIsoDate(editDevisForm.date_devis) : undefined,
      date_validite: editDevisForm.date_validite ? toIsoDate(editDevisForm.date_validite) : undefined,
    })
    editDevisVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    editDevisSaving.value = false
  }
}

function openConversion(devis: Devis) {
  conversionDevisId.value = devis.id
  conversionDevisNumero.value = devis.numero
  conversionForm.date_debut = null
  conversionForm.date_fin = null
  conversionForm.collaborateur_ids = []
  conversionVisible.value = true
}

async function onConvertir() {
  if (!conversionDevisId.value || !conversionForm.date_debut) return
  converting.value = true
  try {
    await convertirDevisEnMission(conversionDevisId.value, {
      date_debut: toIsoDate(conversionForm.date_debut),
      date_fin: conversionForm.date_fin ? toIsoDate(conversionForm.date_fin) : null,
      collaborateur_ids: conversionForm.collaborateur_ids,
    })
    conversionVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    converting.value = false
  }
}

function confirmDelete(devis: Devis) {
  confirm.require({
    message: `Supprimer le devis "${devis.numero}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => { void deleteDevis(devis.id).catch(() => {}) },
  })
}

function confirmEnvoyer(devis: Devis) {
  confirm.require({
    message: `Envoyer le devis "${devis.numero}" par mail au client (PDF joint) ?`,
    header: 'Envoi du devis',
    icon: 'pi pi-send',
    acceptLabel: 'Envoyer',
    rejectLabel: 'Annuler',
    accept: () => envoyerDevis(devis.id),
  })
}

onMounted(async () => {
  await fetchExerciceCourant()
  await fetchExercices()
  exerciceSelectionne.value = exerciceCourant.value?.id
  fetchDevis()
  fetchEntreprises()
  fetchPrestations()
  fetchUsers()
  // Select TVA du dialog « Nouveau devis » (admin uniquement) : le referentiel
  // est interdit a la secretaire cote API (403) -> charge pour l'admin seul.
  if (auth.isAdmin) fetchTvaTaux()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Devis</h1>
      <Button v-if="auth.isAdmin" label="Nouveau devis" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <div class="toolbar-left">
        <label for="search-devis" class="sr-only">Rechercher un devis</label>
        <InputText id="search-devis" v-model="search" placeholder="Rechercher par numero ou client..." style="width: 22rem" />
      </div>
      <div class="toolbar-right">
        <label for="dv-exercice" class="sr-only">Filtrer par exercice</label>
        <Select
          id="dv-exercice"
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

    <DataTable aria-label="Liste des devis"
      :value="devisList"
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
      responsiveLayout="scroll"
      stripedRows
      removableSort
    >
      <Column field="numero" header="Numero" sortable />
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
      <Column field="date_devis" header="Date" sortable />
      <Column field="date_validite" header="Validite" sortable />
      <Column field="prix_ht" header="Prix HT (DA)" sortable>
        <template #body="{ data }">
          {{ formatMontant(data.prix_ht) }}
        </template>
      </Column>
      <Column field="montant_ttc" header="Montant TTC (DA)" sortable>
        <template #body="{ data }">
          {{ formatMontant(data.montant_ttc) }}
        </template>
      </Column>
      <Column field="statut" header="Statut" sortable>
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="statutColor(data.statut)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 14rem">
        <template #body="{ data }">
          <!-- PDF (tous statuts sauf brouillon) -->
          <Button
            v-if="data.statut !== 'brouillon'"
            icon="pi pi-file-pdf"
            text
            severity="secondary"
            aria-label="Telecharger le PDF"
            v-tooltip.top="'Telecharger PDF'"
            @click="telechargerPdf(data.id, data.numero)"
          />
          <!-- Brouillon : modifier + envoyer + supprimer -->
          <Button
            v-if="data.statut === 'brouillon' && auth.isAdmin"
            icon="pi pi-pencil"
            text
            severity="secondary"
            aria-label="Modifier le devis"
            v-tooltip.top="'Modifier'"
            @click="openEditDevis(data)"
          />
          <Button
            v-if="data.statut === 'brouillon'"
            icon="pi pi-send"
            text
            severity="info"
            aria-label="Envoyer le devis par mail au client"
            v-tooltip.top="'Envoyer par mail'"
            @click="confirmEnvoyer(data)"
          />
          <Button
            v-if="data.statut === 'brouillon' && auth.isAdmin"
            icon="pi pi-trash"
            text
            severity="danger"
            aria-label="Supprimer"
            v-tooltip.top="'Supprimer'"
            @click="confirmDelete(data)"
          />
          <!-- Envoye : accepter + refuser -->
          <Button
            v-if="data.statut === 'envoye' && auth.isAdmin"
            icon="pi pi-check-circle"
            text
            severity="success"
            aria-label="Accepter"
            v-tooltip.top="'Accepter'"
            @click="accepterDevis(data.id)"
          />
          <Button
            v-if="data.statut === 'envoye' && auth.isAdmin"
            icon="pi pi-times-circle"
            text
            severity="danger"
            aria-label="Refuser"
            v-tooltip.top="'Refuser'"
            @click="refuserDevis(data.id)"
          />
          <!-- Accepte : convertir en mission -->
          <Button
            v-if="data.statut === 'accepte' && auth.isAdmin"
            icon="pi pi-arrow-right"
            text
            severity="warn"
            aria-label="Convertir en mission"
            v-tooltip.top="'Convertir en mission'"
            @click="openConversion(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog creation devis -->
    <Dialog
      v-model:visible="dialogVisible"
      header="Nouveau devis"
      :modal="true"
      :style="{ width: '36rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div v-if="auth.isAdmin" class="form-field">
          <label for="dv-exercice-create">Exercice *</label>
          <Select
            id="dv-exercice-create"
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
            <DatePicker id="dv-validite" v-model="form.date_validite" dateFormat="dd/mm/yy" :minDate="form.date_devis ?? undefined" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="dv-tva">Categorie TVA *</label>
          <Select
            id="dv-tva"
            v-model="form.type_tva"
            :options="tvaOptions"
            optionLabel="label"
            optionValue="value"
            fluid
          />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" label="Creer" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog modification devis -->
    <Dialog
      v-model:visible="editDevisVisible"
      :header="`Modifier le devis ${editDevisNumero}`"
      :modal="true"
      :style="{ width: '36rem' }"
    >
      <form @submit.prevent="onSubmitEditDevis" class="dialog-form">
        <div class="form-field">
          <label for="ed-entreprise">Entreprise *</label>
          <Select
            id="ed-entreprise"
            v-model="editDevisForm.entreprise_id"
            :options="entreprises"
            optionLabel="raison_sociale"
            optionValue="id"
            placeholder="Selectionner..."
            filter
            fluid
          />
        </div>

        <div class="form-field">
          <label for="ed-prestation">Prestation *</label>
          <Select
            id="ed-prestation"
            v-model="editDevisForm.prestation_id"
            :options="prestations"
            optionLabel="designation"
            optionValue="id"
            placeholder="Selectionner..."
            fluid
          />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ed-date">Date devis *</label>
            <DatePicker id="ed-date" v-model="editDevisForm.date_devis" dateFormat="dd/mm/yy" fluid />
          </div>
          <div class="form-field">
            <label for="ed-validite">Date validite *</label>
            <DatePicker id="ed-validite" v-model="editDevisForm.date_validite" dateFormat="dd/mm/yy" :minDate="editDevisForm.date_devis ?? undefined" fluid />
          </div>
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="editDevisVisible = false" type="button" />
          <Button type="submit" label="Enregistrer" icon="pi pi-check" :loading="editDevisSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog conversion en mission -->
    <Dialog
      v-model:visible="conversionVisible"
      :header="`Convertir ${conversionDevisNumero} en mission`"
      :modal="true"
      :style="{ width: '36rem' }"
    >
      <form @submit.prevent="onConvertir" class="dialog-form">
        <div class="form-row">
          <div class="form-field">
            <label for="cv-debut">Date debut *</label>
            <DatePicker id="cv-debut" v-model="conversionForm.date_debut" dateFormat="dd/mm/yy" fluid />
          </div>
          <div class="form-field">
            <label for="cv-fin">Date fin</label>
            <DatePicker id="cv-fin" v-model="conversionForm.date_fin" dateFormat="dd/mm/yy" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="cv-collabs">Collaborateurs</label>
          <MultiSelect
            id="cv-collabs"
            v-model="conversionForm.collaborateur_ids"
            :options="users"
            optionLabel="name"
            optionValue="id"
            placeholder="Selectionner des collaborateurs..."
            fluid
          />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="conversionVisible = false" />
          <Button
            type="submit"
            label="Creer la mission"
            icon="pi pi-arrow-right"
            :loading="converting"
            :disabled="!conversionForm.date_debut"
          />
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
.page-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.toolbar-left { display: flex; gap: 0.5rem; align-items: center; }
.toolbar-right { display: flex; gap: 0.5rem; align-items: center; }
.dialog-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.form-row { display: flex; gap: 0.75rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
