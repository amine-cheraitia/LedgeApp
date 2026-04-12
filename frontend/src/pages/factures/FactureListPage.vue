<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import { useFactures } from '@/composables/useFactures'
import { useMissions } from '@/composables/useMissions'
import { useExercices } from '@/composables/useExercices'
import { useAuthStore } from '@/stores/auth'
import { avoirsApi } from '@/api/modules/avoirs'
import type { Facture, Avoir } from '@/types'
import type { PaiementPayload } from '@/api/modules/factures'

const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()
const { exercices, exerciceCourant, fetchExercices, fetchExerciceCourant } = useExercices()
const exercicesOuverts = computed(() => exercices.value.filter((e: { statut: string }) => e.statut === 'ouvert'))

const {
  factures, loading, totalRecords, filters,
  fetchFactures, createFacture, deleteFacture, addPaiement, telechargerPdf,
  onPage, onSearch, onSort, setExercice,
} = useFactures()

const { missions, fetchMissions } = useMissions()

// Filtres partagés
const exerciceSelectionne = ref<number | undefined>(undefined)
const search = ref('')

watch(search, (val) => onSearch(val))
watch(exerciceSelectionne, (val) => {
  setExercice(val)
  avoirsPage.value = 1
  fetchAvoirs()
})

// ---------- Tab Avoirs ----------
const avoirs = ref<Avoir[]>([])
const avoirsLoading = ref(false)
const avoirsTotalRecords = ref(0)
const avoirsPage = ref(1)
const avoirsSearch = ref('')
let _avoirsDebounce: ReturnType<typeof setTimeout> | null = null

watch(avoirsSearch, () => {
  if (_avoirsDebounce) clearTimeout(_avoirsDebounce)
  _avoirsDebounce = setTimeout(() => {
    avoirsPage.value = 1
    fetchAvoirs()
  }, 300)
})

async function fetchAvoirs() {
  avoirsLoading.value = true
  try {
    const res = await avoirsApi.getAll({
      page: avoirsPage.value,
      per_page: 15,
      exercice_id: exerciceSelectionne.value,
      search: avoirsSearch.value || undefined,
    })
    avoirs.value = res.data
    avoirsTotalRecords.value = res.meta?.total ?? res.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les avoirs.', life: 3000 })
  } finally {
    avoirsLoading.value = false
  }
}

function onAvoirsPage(event: { page: number }) {
  avoirsPage.value = event.page + 1
  fetchAvoirs()
}

function confirmDeleteAvoir(avoir: Avoir) {
  confirm.require({
    message: `Supprimer l'avoir "${avoir.numero}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await avoirsApi.delete(avoir.id)
        toast.add({ severity: 'success', summary: 'Succes', detail: 'Avoir supprime.', life: 3000 })
        fetchAvoirs()
      } catch {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de supprimer l'avoir.", life: 3000 })
      }
    },
  })
}

// ---------- Dialog création facture ----------
const dialogVisible = ref(false)
const saving = ref(false)
const form = reactive({
  mission_id: null as number | null,
  exercice_id: null as number | null,
  date_facture: new Date() as Date,
  notes: '',
})

const dateEcheancePreview = computed(() => {
  if (!form.date_facture) return ''
  const d = new Date(form.date_facture)
  d.setDate(d.getDate() + 45)
  return d.toLocaleDateString('fr-FR')
})

function trancheLabel(mission_id: number | null): string {
  if (!mission_id) return '—'
  const nb = factures.value.filter((f: Facture) => f.mission_id === mission_id).length
  if (nb === 0) return 'T1 — 30%'
  if (nb === 1) return 'T2 — 30%'
  if (nb === 2) return 'T3 — 40% (solde)'
  return 'Complet'
}

function openCreate() {
  form.mission_id = null
  form.exercice_id = exerciceCourant.value?.id ?? null
  form.date_facture = new Date()
  form.notes = ''
  dialogVisible.value = true
}

async function onSubmitFacture() {
  if (!form.mission_id || !form.date_facture) return
  saving.value = true
  try {
    await createFacture({
      mission_id: form.mission_id,
      exercice_id: form.exercice_id ?? undefined,
      date_facture: toIsoDate(form.date_facture),
      notes: form.notes || null,
    })
    dialogVisible.value = false
  } catch (err: any) {
    const detail = err.response?.data?.message ?? 'Erreur lors de la creation.'
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 })
  } finally {
    saving.value = false
  }
}

// ---------- Dialog paiement ----------
const paiementDialogVisible = ref(false)
const paiementSaving = ref(false)
const paiementFacture = ref<Facture | null>(null)
const paiementForm = reactive<PaiementPayload>({
  montant: 0,
  date_paiement: '',
  mode_paiement: 'virement',
  reference: null,
  notes: null,
})
const datePaiement = ref<Date | null>(null)

const modeOptions = [
  { label: 'Virement', value: 'virement' },
  { label: 'Cheque', value: 'cheque' },
  { label: 'Autre', value: 'autre' },
]

function openPaiement(facture: Facture) {
  paiementFacture.value = facture
  paiementForm.montant = facture.montant_ttc - facture.montant_paye
  paiementForm.mode_paiement = 'virement'
  paiementForm.reference = null
  paiementForm.notes = null
  datePaiement.value = new Date()
  paiementDialogVisible.value = true
}

async function onSubmitPaiement() {
  if (!paiementFacture.value || !datePaiement.value) return
  paiementSaving.value = true
  try {
    await addPaiement(paiementFacture.value.id, {
      ...paiementForm,
      date_paiement: toIsoDate(datePaiement.value),
    })
    paiementDialogVisible.value = false
  } catch (err: any) {
    const detail = err.response?.data?.message ?? 'Erreur lors du paiement.'
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 })
  } finally {
    paiementSaving.value = false
  }
}

// ---------- Dialog avoir ----------
const avoirDialogVisible = ref(false)
const avoirSaving = ref(false)
const avoirFacture = ref<Facture | null>(null)
const avoirForm = reactive({
  montant_ht: 0,
  date_avoir: new Date() as Date,
  motif: '',
})

function openAvoir(facture: Facture) {
  avoirFacture.value = facture
  avoirForm.montant_ht = facture.montant_ht
  avoirForm.date_avoir = new Date()
  avoirForm.motif = ''
  avoirDialogVisible.value = true
}

async function onSubmitAvoir() {
  if (!avoirFacture.value || !avoirForm.montant_ht || !avoirForm.date_avoir) return
  avoirSaving.value = true
  try {
    await avoirsApi.store(avoirFacture.value.id, {
      montant_ht: avoirForm.montant_ht,
      date_avoir: toIsoDate(avoirForm.date_avoir),
      motif: avoirForm.motif,
    })
    toast.add({ severity: 'success', summary: 'Avoir cree', detail: "L'avoir a ete emis avec succes.", life: 4000 })
    avoirDialogVisible.value = false
    fetchFactures()
    fetchAvoirs()
  } catch (err: any) {
    const detail = err.response?.data?.message ?? "Erreur lors de la creation de l'avoir."
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 })
  } finally {
    avoirSaving.value = false
  }
}

// ---------- Helpers ----------
function confirmDelete(facture: Facture) {
  confirm.require({
    message: `Supprimer la facture "${facture.numero}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteFacture(facture.id),
  })
}

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  return d.toISOString().split('T')[0]
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}

function formatMontant(v: number) {
  return Number(v).toLocaleString('fr-FR') + ' DA'
}

function statutPaiementColor(statut: string) {
  const map: Record<string, 'warn' | 'info' | 'success' | 'secondary'> = {
    en_attente: 'warn',
    partiel: 'info',
    solde: 'success',
  }
  return map[statut] ?? 'secondary'
}

function modePaiementLabel(mode: string) {
  const map: Record<string, string> = {
    virement: 'Virement',
    cheque: 'Cheque',
    autre: 'Autre',
    non_defini: '—',
  }
  return map[mode] ?? mode
}

onMounted(async () => {
  await fetchExerciceCourant()
  await fetchExercices()
  exerciceSelectionne.value = exerciceCourant.value?.id
  fetchFactures()
  fetchMissions()
  fetchAvoirs()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Factures &amp; Avoirs</h1>
      <Button label="Nouvelle facture" icon="pi pi-plus" aria-label="Creer une facture" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <div class="toolbar-right">
        <label for="f-exercice" class="sr-only">Filtrer par exercice</label>
        <Select
          id="f-exercice"
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

    <Tabs value="factures">
      <TabList>
        <Tab value="factures">
          Factures
          <span v-if="totalRecords > 0" class="tab-badge">{{ totalRecords }}</span>
        </Tab>
        <Tab value="avoirs">
          Avoirs
          <span v-if="avoirsTotalRecords > 0" class="tab-badge tab-badge--secondary">{{ avoirsTotalRecords }}</span>
        </Tab>
      </TabList>

      <TabPanels>
        <!-- ===== Tab Factures ===== -->
        <TabPanel value="factures">
          <div class="tab-toolbar">
            <label for="search-factures" class="sr-only">Rechercher une facture</label>
            <InputText
              id="search-factures"
              v-model="search"
              placeholder="Rechercher par numero ou client..."
              style="width: 22rem"
            />
          </div>

          <DataTable
            :value="factures"
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
            <Column field="numero" header="Numero" sortable />
            <Column header="Entreprise">
              <template #body="{ data }">{{ data.entreprise?.raison_sociale ?? '-' }}</template>
            </Column>
            <Column header="Mission">
              <template #body="{ data }">{{ data.mission?.reference ?? '-' }}</template>
            </Column>
            <Column field="date_facture" header="Date" sortable>
              <template #body="{ data }">{{ formatDate(data.date_facture) }}</template>
            </Column>
            <Column field="date_echeance" header="Echeance" sortable>
              <template #body="{ data }">{{ formatDate(data.date_echeance) }}</template>
            </Column>
            <Column field="montant_ttc" header="Montant TTC" sortable>
              <template #body="{ data }">{{ formatMontant(data.montant_ttc) }}</template>
            </Column>
            <Column header="Paye">
              <template #body="{ data }">{{ formatMontant(data.montant_paye) }}</template>
            </Column>
            <Column header="Mode">
              <template #body="{ data }">{{ modePaiementLabel(data.mode_paiement) }}</template>
            </Column>
            <Column field="statut_paiement" header="Statut" sortable>
              <template #body="{ data }">
                <Tag :value="data.statut_paiement" :severity="statutPaiementColor(data.statut_paiement)" />
              </template>
            </Column>
            <Column header="Actions" style="width: 11rem">
              <template #body="{ data }">
                <Button
                  icon="pi pi-file-pdf"
                  text
                  rounded
                  severity="secondary"
                  aria-label="Telecharger le PDF"
                  v-tooltip.top="'Telecharger PDF'"
                  @click="telechargerPdf(data.id, data.numero)"
                />
                <Button
                  v-if="data.statut_paiement !== 'solde'"
                  icon="pi pi-wallet"
                  text
                  rounded
                  severity="success"
                  aria-label="Enregistrer un paiement"
                  v-tooltip.top="'Paiement'"
                  @click="openPaiement(data)"
                />
                <Button
                  icon="pi pi-file-edit"
                  text
                  rounded
                  severity="secondary"
                  aria-label="Emettre un avoir"
                  v-tooltip.top="'Emettre un avoir'"
                  @click="openAvoir(data)"
                />
                <Button
                  v-if="!data.paiements || data.paiements.length === 0"
                  icon="pi pi-trash"
                  text
                  rounded
                  severity="danger"
                  aria-label="Supprimer"
                  v-tooltip.top="'Supprimer'"
                  @click="confirmDelete(data)"
                />
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- ===== Tab Avoirs ===== -->
        <TabPanel value="avoirs">
          <div class="tab-toolbar">
            <label for="search-avoirs" class="sr-only">Rechercher un avoir</label>
            <InputText
              id="search-avoirs"
              v-model="avoirsSearch"
              placeholder="Rechercher par numero ou client..."
              style="width: 22rem"
            />
          </div>

          <DataTable
            :value="avoirs"
            :loading="avoirsLoading"
            :paginator="true"
            :rows="15"
            :totalRecords="avoirsTotalRecords"
            :lazy="true"
            @page="onAvoirsPage"
            dataKey="id"
            stripedRows
          >
            <Column field="numero" header="Numero" />
            <Column header="Facture d'origine">
              <template #body="{ data }">{{ data.facture_origine?.numero ?? '-' }}</template>
            </Column>
            <Column header="Client">
              <template #body="{ data }">{{ data.facture_origine?.entreprise?.raison_sociale ?? '-' }}</template>
            </Column>
            <Column field="date_avoir" header="Date avoir">
              <template #body="{ data }">{{ formatDate(data.date_avoir) }}</template>
            </Column>
            <Column header="Montant HT">
              <template #body="{ data }">{{ formatMontant(data.montant_ht) }}</template>
            </Column>
            <Column header="Montant TTC">
              <template #body="{ data }">{{ formatMontant(data.montant_ttc) }}</template>
            </Column>
            <Column header="Motif">
              <template #body="{ data }">
                <span class="motif-cell" :title="data.motif">{{ data.motif }}</span>
              </template>
            </Column>
            <Column header="Actions" style="width: 6rem">
              <template #body="{ data }">
                <Button
                  icon="pi pi-file-pdf"
                  text
                  rounded
                  severity="secondary"
                  aria-label="Telecharger PDF avoir"
                  v-tooltip.top="'Telecharger PDF'"
                  @click="avoirsApi.telechargerPdf(data.facture_origine_id, data.id, data.numero)"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  severity="danger"
                  aria-label="Supprimer l'avoir"
                  v-tooltip.top="'Supprimer'"
                  @click="confirmDeleteAvoir(data)"
                />
              </template>
            </Column>
          </DataTable>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Dialog création facture -->
    <Dialog
      v-model:visible="dialogVisible"
      header="Nouvelle facture"
      :modal="true"
      :style="{ width: '34rem' }"
    >
      <form @submit.prevent="onSubmitFacture" class="dialog-form">
        <div v-if="auth.isAdmin" class="form-field">
          <label for="f-exercice-create">Exercice *</label>
          <Select
            id="f-exercice-create"
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
          <label for="f-mission">Mission *</label>
          <Select
            id="f-mission"
            v-model="form.mission_id"
            :options="missions"
            optionLabel="reference"
            optionValue="id"
            placeholder="Selectionner une mission..."
            filter
            fluid
          />
          <small v-if="form.mission_id" class="tranche-hint">
            Prochaine tranche : <strong>{{ trancheLabel(form.mission_id) }}</strong>
          </small>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="f-date">Date facture *</label>
            <DatePicker id="f-date" v-model="form.date_facture" dateFormat="dd/mm/yy" fluid />
          </div>
          <div class="form-field">
            <label>Echeance (auto)</label>
            <div class="echeance-preview">{{ dateEcheancePreview }}</div>
            <small class="hint">Date facture + 45 jours</small>
          </div>
        </div>

        <div class="form-field">
          <label for="f-notes">Notes</label>
          <Textarea id="f-notes" v-model="form.notes" rows="2" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" type="button" />
          <Button
            type="submit"
            label="Creer"
            icon="pi pi-check"
            :loading="saving"
            :disabled="!form.mission_id"
          />
        </div>
      </form>
    </Dialog>

    <!-- Dialog avoir -->
    <Dialog
      v-model:visible="avoirDialogVisible"
      :header="`Emettre un avoir — ${avoirFacture?.numero}`"
      :modal="true"
      :style="{ width: '32rem', maxWidth: '95vw' }"
      :breakpoints="{ '640px': '95vw' }"
      :draggable="false"
    >
      <form v-if="avoirFacture" @submit.prevent="onSubmitAvoir" class="dialog-form">
        <div class="avoir-recap">
          <div class="recap-row">
            <span class="recap-label">Facture</span>
            <span>{{ avoirFacture.numero }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Montant HT</span>
            <span>{{ formatMontant(avoirFacture.montant_ht) }}</span>
          </div>
        </div>

        <div class="form-field">
          <label for="a-montant">Montant HT de l'avoir (DA) *</label>
          <InputNumber
            id="a-montant"
            v-model="avoirForm.montant_ht"
            :min="0.01"
            mode="decimal"
            :minFractionDigits="2"
            fluid
            aria-label="Montant HT de l'avoir"
          />
          <small class="hint">TVA reprise de la facture d'origine ({{ avoirFacture.taux_tva }}%)</small>
        </div>

        <div class="form-field">
          <label for="a-date">Date de l'avoir *</label>
          <DatePicker id="a-date" v-model="avoirForm.date_avoir" dateFormat="dd/mm/yy" fluid aria-label="Date de l'avoir" />
        </div>

        <div class="form-field">
          <label for="a-motif">Motif *</label>
          <Textarea
            id="a-motif"
            v-model="avoirForm.motif"
            rows="3"
            fluid
            placeholder="Motif de l'avoir..."
            aria-label="Motif de l'avoir"
          />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="avoirDialogVisible = false" type="button" />
          <Button
            type="submit"
            label="Emettre l'avoir"
            icon="pi pi-check"
            :loading="avoirSaving"
            :disabled="!avoirForm.montant_ht || !avoirForm.motif || !avoirForm.date_avoir"
          />
        </div>
      </form>
    </Dialog>

    <!-- Dialog paiement -->
    <Dialog
      v-model:visible="paiementDialogVisible"
      :header="`Paiement — ${paiementFacture?.numero}`"
      :modal="true"
      :style="{ width: '28rem' }"
    >
      <form @submit.prevent="onSubmitPaiement" class="dialog-form">
        <div class="form-field">
          <label for="p-montant">Montant (DA) *</label>
          <InputNumber
            id="p-montant"
            v-model="paiementForm.montant"
            :min="0.01"
            mode="decimal"
            :minFractionDigits="2"
            fluid
          />
          <small v-if="paiementFacture" class="hint">
            Restant : {{ formatMontant(paiementFacture.montant_ttc - paiementFacture.montant_paye) }}
          </small>
        </div>

        <div class="form-field">
          <label for="p-date">Date paiement *</label>
          <DatePicker id="p-date" v-model="datePaiement" dateFormat="dd/mm/yy" fluid />
        </div>

        <div class="form-field">
          <label for="p-mode">Mode de paiement *</label>
          <Select
            id="p-mode"
            v-model="paiementForm.mode_paiement"
            :options="modeOptions"
            optionLabel="label"
            optionValue="value"
            fluid
          />
        </div>

        <div class="form-field">
          <label for="p-ref">Reference</label>
          <InputText id="p-ref" v-model="paiementForm.reference" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="paiementDialogVisible = false" type="button" />
          <Button
            type="submit"
            label="Enregistrer"
            icon="pi pi-check"
            :loading="paiementSaving"
            :disabled="!datePaiement || !paiementForm.montant"
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
.page-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}
.tab-toolbar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  margin-top: 1rem;
}
.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3rem;
  margin-left: 0.4rem;
  vertical-align: middle;
}
.tab-badge--secondary {
  background: var(--p-surface-400);
  color: var(--p-surface-0);
}
.motif-cell {
  display: block;
  max-width: 16rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dialog-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.form-row { display: flex; gap: 0.75rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
.hint { color: var(--p-text-muted-color); font-size: 0.75rem; }
.tranche-hint { color: var(--p-primary-color); font-size: 0.8rem; }
.echeance-preview {
  padding: 0.5rem 0.75rem;
  background: var(--p-surface-ground);
  border: 1px solid var(--p-surface-border);
  border-radius: var(--p-border-radius);
  font-size: 0.9rem;
  color: var(--p-text-color);
  min-height: 2.5rem;
  display: flex;
  align-items: center;
}
.avoir-recap {
  background: var(--p-surface-ground);
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.recap-row { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.recap-label { font-size: 0.8rem; color: var(--p-text-muted-color); }

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
