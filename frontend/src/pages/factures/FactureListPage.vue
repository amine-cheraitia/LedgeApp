<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
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
import { useEntreprises } from '@/composables/useEntreprises'
import type { Facture } from '@/types'
import type { FactureLignePayload, PaiementPayload } from '@/api/modules/factures'

const confirm = useConfirm()
const {
  factures, loading, totalRecords, filters,
  fetchFactures, createFacture, deleteFacture, addPaiement,
  onPage, onSearch,
} = useFactures()

const { entreprises, fetchEntreprises } = useEntreprises()

const search = ref('')

// Dialog creation facture
const dialogVisible = ref(false)
const saving = ref(false)
const form = reactive({
  entreprise_id: null as number | null,
  type: 'FF' as 'FF' | 'FA',
  date_facture: null as Date | null,
  date_echeance: null as Date | null,
  notes: '',
  lignes: [{ designation: '', quantite: 1, prix_unitaire_ht: 0, prestation_id: null }] as (FactureLignePayload & { prestation_id: number | null })[],
})

// Dialog paiement
const paiementDialogVisible = ref(false)
const paiementSaving = ref(false)
const paiementFactureId = ref<number | null>(null)
const paiementForm = reactive<PaiementPayload>({
  montant: 0,
  date_paiement: '',
  mode_paiement: 'virement',
  reference: null,
  notes: null,
})
const datePaiement = ref<Date | null>(null)

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  return d.toISOString().split('T')[0]
}

function formatMontant(v: number) {
  return Number(v).toLocaleString('fr-FR')
}

const typeOptions = [
  { label: 'Facture (FF)', value: 'FF' },
  { label: 'Avoir (FA)', value: 'FA' },
]

const modeOptions = [
  { label: 'Virement', value: 'virement' },
  { label: 'Cheque', value: 'cheque' },
  { label: 'Espece', value: 'espece' },
  { label: 'Autre', value: 'autre' },
]

function statutColor(statut: string) {
  const map: Record<string, string> = {
    en_attente: 'warn',
    partiel: 'info',
    solde: 'success',
  }
  return (map[statut] ?? 'secondary') as 'warn' | 'info' | 'success' | 'secondary'
}

// Creation facture
function openCreate() {
  form.entreprise_id = null
  form.type = 'FF'
  form.date_facture = null
  form.date_echeance = null
  form.notes = ''
  form.lignes = [{ designation: '', quantite: 1, prix_unitaire_ht: 0, prestation_id: null }]
  dialogVisible.value = true
}

function addLigne() {
  form.lignes.push({ designation: '', quantite: 1, prix_unitaire_ht: 0, prestation_id: null })
}

function removeLigne(index: number) {
  if (form.lignes.length > 1) form.lignes.splice(index, 1)
}

async function onSubmitFacture() {
  if (!form.entreprise_id || !form.date_facture || !form.date_echeance) return
  saving.value = true
  try {
    await createFacture({
      entreprise_id: form.entreprise_id,
      type: form.type,
      date_facture: toIsoDate(form.date_facture),
      date_echeance: toIsoDate(form.date_echeance),
      notes: form.notes || null,
      lignes: form.lignes,
    })
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

// Paiement
function openPaiement(facture: Facture) {
  paiementFactureId.value = facture.id
  paiementForm.montant = 0
  paiementForm.mode_paiement = 'virement'
  paiementForm.reference = null
  paiementForm.notes = null
  datePaiement.value = null
  paiementDialogVisible.value = true
}

async function onSubmitPaiement() {
  if (!paiementFactureId.value || !datePaiement.value) return
  paiementSaving.value = true
  try {
    await addPaiement(paiementFactureId.value, {
      ...paiementForm,
      date_paiement: toIsoDate(datePaiement.value),
    })
    paiementDialogVisible.value = false
  } catch {
    // erreur geree par le composable
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

onMounted(() => {
  fetchFactures()
  fetchEntreprises()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Factures</h2>
      <Button label="Nouvelle facture" icon="pi pi-plus" @click="openCreate" />
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
      <Column field="type" header="Type">
        <template #body="{ data }">
          <Tag :value="data.type" :severity="data.type === 'FF' ? 'info' : 'warn'" />
        </template>
      </Column>
      <Column header="Entreprise">
        <template #body="{ data }">
          {{ data.entreprise?.raison_sociale ?? '-' }}
        </template>
      </Column>
      <Column field="date_facture" header="Date" />
      <Column header="TTC (DA)">
        <template #body="{ data }">
          {{ formatMontant(data.montant_ttc) }}
        </template>
      </Column>
      <Column header="Paye (DA)">
        <template #body="{ data }">
          {{ formatMontant(data.montant_paye) }}
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut_paiement" :severity="statutColor(data.statut_paiement)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 10rem">
        <template #body="{ data }">
          <Button
            v-if="data.statut_paiement !== 'solde'"
            icon="pi pi-wallet"
            text
            severity="success"
            aria-label="Ajouter un paiement"
            @click="openPaiement(data)"
          />
          <Button
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

    <!-- Dialog creation facture -->
    <Dialog
      v-model:visible="dialogVisible"
      header="Nouvelle facture"
      :modal="true"
      :style="{ width: '44rem' }"
    >
      <form @submit.prevent="onSubmitFacture" class="dialog-form">
        <div class="form-row">
          <div class="form-field">
            <label for="f-entreprise">Entreprise *</label>
            <Select
              id="f-entreprise"
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
            <label for="f-type">Type *</label>
            <Select id="f-type" v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="f-date">Date facture *</label>
            <DatePicker id="f-date" v-model="form.date_facture" dateFormat="dd/mm/yy" fluid />
          </div>
          <div class="form-field">
            <label for="f-echeance">Date echeance *</label>
            <DatePicker id="f-echeance" v-model="form.date_echeance" dateFormat="dd/mm/yy" fluid />
          </div>
        </div>

        <div class="lignes-section">
          <h4>Lignes</h4>
          <div v-for="(ligne, i) in form.lignes" :key="i" class="ligne-row">
            <div class="form-field" style="flex: 2">
              <label :for="'fl-des-' + i" class="sr-only">Designation</label>
              <InputText :id="'fl-des-' + i" v-model="ligne.designation" placeholder="Designation" fluid />
            </div>
            <div class="form-field" style="flex: 0.5">
              <label :for="'fl-qty-' + i" class="sr-only">Quantite</label>
              <InputNumber :id="'fl-qty-' + i" v-model="ligne.quantite" :min="0.01" :minFractionDigits="0" :maxFractionDigits="2" placeholder="Qte" fluid />
            </div>
            <div class="form-field" style="flex: 1">
              <label :for="'fl-prix-' + i" class="sr-only">Prix unitaire HT</label>
              <InputNumber :id="'fl-prix-' + i" v-model="ligne.prix_unitaire_ht" :min="0" mode="decimal" :minFractionDigits="2" placeholder="Prix HT" fluid />
            </div>
            <Button
              icon="pi pi-times"
              text
              severity="danger"
              aria-label="Supprimer la ligne"
              @click="removeLigne(i)"
              :disabled="form.lignes.length <= 1"
            />
          </div>
          <Button label="Ajouter une ligne" icon="pi pi-plus" text severity="info" @click="addLigne" />
        </div>

        <div class="form-field">
          <label for="f-notes">Notes</label>
          <Textarea id="f-notes" v-model="form.notes" rows="2" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" label="Creer" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog paiement -->
    <Dialog
      v-model:visible="paiementDialogVisible"
      header="Enregistrer un paiement"
      :modal="true"
      :style="{ width: '28rem' }"
    >
      <form @submit.prevent="onSubmitPaiement" class="dialog-form">
        <div class="form-field">
          <label for="p-montant">Montant (DA) *</label>
          <InputNumber id="p-montant" v-model="paiementForm.montant" :min="0.01" mode="decimal" :minFractionDigits="2" fluid />
        </div>

        <div class="form-field">
          <label for="p-date">Date paiement *</label>
          <DatePicker id="p-date" v-model="datePaiement" dateFormat="dd/mm/yy" fluid />
        </div>

        <div class="form-field">
          <label for="p-mode">Mode *</label>
          <Select id="p-mode" v-model="paiementForm.mode_paiement" :options="modeOptions" optionLabel="label" optionValue="value" fluid />
        </div>

        <div class="form-field">
          <label for="p-ref">Reference</label>
          <InputText id="p-ref" v-model="paiementForm.reference" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="paiementDialogVisible = false" />
          <Button type="submit" label="Enregistrer" icon="pi pi-check" :loading="paiementSaving" />
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
.lignes-section { display: flex; flex-direction: column; gap: 0.5rem; }
.lignes-section h4 { margin: 0; font-size: 0.95rem; }
.ligne-row { display: flex; gap: 0.5rem; align-items: flex-end; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }

@media (max-width: 640px) {
  .form-row, .ligne-row { flex-direction: column; }
}
</style>
