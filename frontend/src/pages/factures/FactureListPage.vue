<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
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
import ConfirmDialog from 'primevue/confirmdialog'
import { useFactures } from '@/composables/useFactures'
import { useMissions } from '@/composables/useMissions'
import { avoirsApi } from '@/api/modules/avoirs'
import type { Facture } from '@/types'
import type { PaiementPayload } from '@/api/modules/factures'

const toast = useToast()
const confirm = useConfirm()

const {
  factures, loading, totalRecords, filters,
  fetchFactures, createFacture, deleteFacture, addPaiement, telechargerPdf,
  onPage, onSearch,
} = useFactures()

const { missions, fetchMissions } = useMissions()

const search = ref('')

// Dialog création facture
const dialogVisible = ref(false)
const saving = ref(false)
const form = reactive({
  mission_id: null as number | null,
  date_facture: new Date() as Date,
  notes: '',
})

const dateEcheancePreview = computed(() => {
  if (!form.date_facture) return ''
  const d = new Date(form.date_facture)
  d.setDate(d.getDate() + 45)
  return d.toLocaleDateString('fr-FR')
})

// Dialog paiement
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

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  return d.toISOString().split('T')[0]
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

// Tranche suivante pour une mission donnée
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

function handleSearch() {
  onSearch(search.value)
}

// Dialog avoir
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
  avoirForm.montant_ht = 0
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
    toast.add({ severity: 'success', summary: 'Avoir créé', detail: 'L\'avoir a été émis avec succès.', life: 4000 })
    avoirDialogVisible.value = false
    fetchFactures()
  } catch (err: any) {
    const detail = err.response?.data?.message ?? 'Erreur lors de la création de l\'avoir.'
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 })
  } finally {
    avoirSaving.value = false
  }
}

async function telechargerAvoirPdf(factureId: number, avoirId: number, numero: string) {
  try {
    await avoirsApi.telechargerPdf(factureId, avoirId, numero)
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de télécharger le PDF.', life: 3000 })
  }
}

onMounted(() => {
  fetchFactures()
  fetchMissions()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Factures</h2>
      <Button label="Nouvelle facture" icon="pi pi-plus" aria-label="Creer une facture" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="handleSearch" role="search" class="search-form">
        <label for="search-factures" class="sr-only">Rechercher une facture</label>
        <InputText id="search-factures" v-model="search" placeholder="Rechercher par numero..." />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="handleSearch" />
      </form>
    </div>

    <DataTable
      :value="factures"
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
      <Column header="Mission">
        <template #body="{ data }">
          {{ data.mission?.reference ?? '-' }}
        </template>
      </Column>
      <Column header="Entreprise">
        <template #body="{ data }">
          {{ data.entreprise?.raison_sociale ?? '-' }}
        </template>
      </Column>
      <Column field="date_facture" header="Date" />
      <Column field="date_echeance" header="Echeance" />
      <Column header="Montant TTC">
        <template #body="{ data }">
          {{ formatMontant(data.montant_ttc) }}
        </template>
      </Column>
      <Column header="Paye">
        <template #body="{ data }">
          {{ formatMontant(data.montant_paye) }}
        </template>
      </Column>
      <Column header="Mode">
        <template #body="{ data }">
          {{ modePaiementLabel(data.mode_paiement) }}
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut_paiement" :severity="statutPaiementColor(data.statut_paiement)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 10rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-file-pdf"
            text
            severity="secondary"
            aria-label="Telecharger le PDF"
            v-tooltip.top="'Telecharger PDF'"
            @click="telechargerPdf(data.id, data.numero)"
          />
          <Button
            v-if="data.statut_paiement !== 'solde'"
            icon="pi pi-wallet"
            text
            severity="success"
            aria-label="Enregistrer un paiement"
            v-tooltip.top="'Paiement'"
            @click="openPaiement(data)"
          />
          <Button
            icon="pi pi-file-edit"
            text
            severity="secondary"
            aria-label="Émettre un avoir"
            v-tooltip.top="'Émettre un avoir'"
            @click="openAvoir(data)"
          />
          <Button
            v-if="!data.paiements || data.paiements.length === 0"
            icon="pi pi-trash"
            text
            severity="danger"
            aria-label="Supprimer"
            v-tooltip.top="'Supprimer'"
            @click="confirmDelete(data)"
          />
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />

    <!-- Dialog création facture -->
    <Dialog
      v-model:visible="dialogVisible"
      header="Nouvelle facture"
      :modal="true"
      :style="{ width: '34rem' }"
    >
      <form @submit.prevent="onSubmitFacture" class="dialog-form">
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
      :header="`Émettre un avoir — ${avoirFacture?.numero}`"
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
            <span class="recap-label">Restant dû</span>
            <span class="montant-restant">{{ formatMontant(avoirFacture.montant_restant) }}</span>
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
            label="Émettre l'avoir"
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
.page-toolbar { margin-bottom: 1rem; }
.search-form { display: flex; gap: 0.5rem; max-width: 20rem; }
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
.montant-restant { font-weight: 700; color: var(--p-red-600); }

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
