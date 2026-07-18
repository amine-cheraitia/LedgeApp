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
import FactureDetailDrawer from '@/components/facturation/FactureDetailDrawer.vue'
import { useFactures } from '@/composables/useFactures'
import { useMissions } from '@/composables/useMissions'
import { useExercices } from '@/composables/useExercices'
import { useTvaTaux } from '@/composables/useTvaTaux'
import { useAuthStore } from '@/stores/authStore'
import { avoirsApi } from '@/api/modules/avoirs'
import type { Facture, Avoir, Exercice } from '@/types'

const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()
const { exercices, exerciceCourant, fetchExercices, fetchExerciceCourant } = useExercices()
const exercicesOuverts = computed(() => exercices.value.filter((e: { statut: string }) => e.statut === 'ouvert'))

const {
  factures, loading, totalRecords, filters,
  fetchFactures, createFacture, deleteFacture, transmettreFacture, telechargerPdf,
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
const { fetchTaux: fetchTvaTaux, tauxEnVigueur } = useTvaTaux()

const form = reactive({
  mission_id: null as number | null,
  exercice_id: null as number | null,
  date_facture: new Date() as Date,
  type_tva: 'standard' as 'standard' | 'exonere',
})

const tvaOptions = computed(() => {
  const std = tauxEnVigueur('standard', form.date_facture)
  const exo = tauxEnVigueur('exonere', form.date_facture)
  return [
    { label: std !== null ? `Standard (${std} %)` : 'Standard', value: 'standard' },
    { label: exo !== null ? `Exonere (${exo} %)` : 'Exonere (0 %)', value: 'exonere' },
  ]
})

const dateEcheancePreview = computed(() => {
  if (!form.date_facture) return ''
  const d = new Date(form.date_facture)
  d.setDate(d.getDate() + 45)
  return d.toLocaleDateString('fr-FR')
})

// Bornes du DatePicker : la date de facture doit rester dans l'exercice choisi.
const exerciceObj = computed(() => exercices.value.find((e: Exercice) => e.id === form.exercice_id) ?? null)
const dateMin = computed(() => (exerciceObj.value ? new Date(exerciceObj.value.date_ouverture) : undefined))
const dateMax = computed(() => (exerciceObj.value ? new Date(exerciceObj.value.date_cloture) : undefined))

// Au changement d'exercice : si la date sort des bornes, on la ramene dedans
// (aujourd'hui si dans la plage, sinon le 1er jour de l'exercice).
watch(() => form.exercice_id, () => {
  const min = dateMin.value
  const max = dateMax.value
  if (!min || !max) return
  const d = form.date_facture
  if (!d || d < min || d > max) {
    const today = new Date()
    form.date_facture = today >= min && today <= max ? today : min
  }
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
  form.type_tva = 'standard'
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
      type_tva: form.type_tva,
    })
    dialogVisible.value = false
  } catch (err: any) {
    const detail = err.response?.data?.message ?? 'Erreur lors de la creation.'
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 })
  } finally {
    saving.value = false
  }
}

// ---------- Drawer paiement ----------
const drawerVisible = ref(false)
const drawerFacture = ref<Facture | null>(null)

function openDrawer(facture: Facture) {
  drawerFacture.value = facture
  drawerVisible.value = true
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
// Popup d'erreur de suppression (ex. facture non-derniere) : un modal laisse le
// temps de lire le message (suggestion d'avoir), contrairement a un toast fugace.
const deleteErrorVisible = ref(false)
const deleteErrorMessage = ref('')

function confirmDelete(facture: Facture) {
  confirm.require({
    message: `Supprimer la facture "${facture.numero}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await deleteFacture(facture.id)
      } catch (err: any) {
        deleteErrorMessage.value = err.response?.data?.message ?? 'Suppression impossible.'
        deleteErrorVisible.value = true
      }
    },
  })
}

function confirmTransmettre(facture: Facture) {
  confirm.require({
    message: `Transmettre la facture "${facture.numero}" par mail au client (PDF joint) ?`,
    header: 'Transmission de la facture',
    icon: 'pi pi-envelope',
    acceptLabel: 'Transmettre',
    rejectLabel: 'Annuler',
    accept: () => transmettreFacture(facture.id),
  })
}

function toIsoDate(d: Date | null): string {
  if (!d) return ''
  // Date LOCALE (pas toISOString/UTC) : evite le decalage d'un jour en UTC+1
  // et garantit que la date envoyee == la date affichee == la date du taux calcule.
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
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
  fetchAvoirs()
  // Selects du dialog « Nouvelle facture » (admin uniquement) : /missions et
  // /referentiels/tva-taux sont interdits a la secretaire cote API (403).
  // On ne les charge que pour l'admin — sinon toast d'erreur a chaque visite.
  if (auth.isAdmin) {
    fetchMissions()
    fetchTvaTaux()
  }
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1>Factures &amp; Avoirs</h1>
      </div>
      <Button v-if="auth.isAdmin" label="Nouvelle facture" icon="pi pi-plus" aria-label="Creer une facture" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <div class="toolbar-filters">
        <label for="f-exercice" class="sr-only">Filtrer par exercice</label>
        <Select
          id="f-exercice"
          v-model="exerciceSelectionne"
          :options="exercices"
          optionLabel="annee"
          optionValue="id"
          placeholder="Tous les exercices"
          showClear
          class="toolbar-select"
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
            <div class="search-wrapper">
              <label for="search-factures" class="sr-only">Rechercher une facture</label>
              <i class="pi pi-search search-icon" aria-hidden="true" />
              <InputText
                id="search-factures"
                v-model="search"
                class="search-input"
                placeholder="Rechercher par numero ou client..."
              />
            </div>
          </div>

          <div class="table-card">
          <DataTable aria-label="Liste des factures"
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
            paginatorTemplate="CurrentPageReport PrevPageLink PageLinks NextPageLink"
            currentPageReportTemplate="{totalRecords} résultat(s) · Page {currentPage} sur {totalPages}"
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
                  v-if="auth.isAdmin || auth.isSecretaire"
                  icon="pi pi-envelope"
                  text
                  rounded
                  severity="info"
                  aria-label="Transmettre la facture par mail au client"
                  v-tooltip.top="'Transmettre par mail'"
                  @click="confirmTransmettre(data)"
                />
                <Button
                  v-if="auth.isAdmin || auth.isSecretaire"
                  icon="pi pi-wallet"
                  text
                  rounded
                  severity="success"
                  aria-label="Voir les encaissements"
                  v-tooltip.top="'Encaissements'"
                  @click="openDrawer(data)"
                />
                <Button
                  v-if="auth.isAdmin"
                  icon="pi pi-file-edit"
                  text
                  rounded
                  severity="secondary"
                  aria-label="Emettre un avoir"
                  v-tooltip.top="'Emettre un avoir'"
                  @click="openAvoir(data)"
                />
                <Button
                  v-if="auth.isAdmin && (!data.paiements || data.paiements.length === 0)"
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
          </div>
        </TabPanel>

        <!-- ===== Tab Avoirs ===== -->
        <TabPanel value="avoirs">
          <div class="tab-toolbar">
            <div class="search-wrapper">
              <label for="search-avoirs" class="sr-only">Rechercher un avoir</label>
              <i class="pi pi-search search-icon" aria-hidden="true" />
              <InputText
                id="search-avoirs"
                v-model="avoirsSearch"
                class="search-input"
                placeholder="Rechercher par numero ou client..."
              />
            </div>
          </div>

          <div class="table-card">
          <DataTable aria-label="Liste des avoirs"
            :value="avoirs"
            :loading="avoirsLoading"
            :paginator="true"
            :rows="15"
            :totalRecords="avoirsTotalRecords"
            :lazy="true"
            @page="onAvoirsPage"
            dataKey="id"
            stripedRows
            paginatorTemplate="CurrentPageReport PrevPageLink PageLinks NextPageLink"
            currentPageReportTemplate="{totalRecords} résultat(s) · Page {currentPage} sur {totalPages}"
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
                  v-if="auth.isAdmin"
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
          </div>
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
            <DatePicker id="f-date" v-model="form.date_facture" :minDate="dateMin" :maxDate="dateMax" dateFormat="dd/mm/yy" fluid />
          </div>
          <div class="form-field">
            <label>Echeance (auto)</label>
            <div class="echeance-preview">{{ dateEcheancePreview }}</div>
            <small class="hint">Date facture + 45 jours</small>
          </div>
        </div>

        <div class="form-field">
          <label for="f-tva">Categorie TVA *</label>
          <Select
            id="f-tva"
            v-model="form.type_tva"
            :options="tvaOptions"
            optionLabel="label"
            optionValue="value"
            fluid
          />
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

    <!-- Drawer encaissements -->
    <FactureDetailDrawer
      v-model="drawerVisible"
      :facture="drawerFacture"
      @paiement-changed="fetchFactures"
    />

    <!-- Popup : suppression refusee (facture non-derniere, paiement ou avoir lie) -->
    <Dialog
      v-model:visible="deleteErrorVisible"
      header="Suppression impossible"
      :modal="true"
      :style="{ width: 'min(100vw - 2rem, 30rem)' }"
      :closable="true"
    >
      <div class="delete-error-body" role="alert" aria-live="assertive">
        <i class="pi pi-exclamation-triangle delete-error-icon" aria-hidden="true" />
        <p class="delete-error-text">{{ deleteErrorMessage }}</p>
      </div>
      <template #footer>
        <Button label="Fermer" icon="pi pi-check" @click="deleteErrorVisible = false" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.delete-error-body {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}
.delete-error-icon {
  font-size: 1.5rem;
  color: var(--p-orange-500, #f97316);
  flex-shrink: 0;
  margin-top: 0.1rem;
}
.delete-error-text {
  margin: 0;
  line-height: 1.55;
  color: var(--p-text-color);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.page-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.toolbar-filters {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  flex: 1;
}
.tab-toolbar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  margin-top: 1rem;
}

/* ── Barre de recherche façon maquette : large, arrondie, pleine largeur ── */
.search-wrapper {
  position: relative;
  flex: 1;
  min-width: 16rem;
}
.search-icon {
  position: absolute;
  left: 0.95rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--p-text-muted-color);
  pointer-events: none;
}
.search-input {
  width: 100%;
  height: 2.9rem;
  padding-left: 2.6rem;
  border-radius: 10px;
}
/* Filtres harmonises sur la meme hauteur/arrondi que la recherche */
.toolbar-select {
  min-width: 11rem;
  height: 2.9rem;
  border-radius: 10px;
  align-items: center;
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

/* ── Carte du tableau (maquette : bloc arrondi legerement eleve) ────────── */
.table-card {
  border: 1px solid var(--p-surface-200);
  border-radius: 12px;
  overflow: hidden;
  background: var(--p-surface-0);
}
.app-dark .table-card {
  border-color: color-mix(in srgb, var(--p-surface-700) 55%, transparent);
  background: color-mix(in srgb, var(--p-surface-800) 62%, var(--p-surface-900));
}

/* En-tetes de colonnes : petites capitales espacees, fond distinct (maquette) */
.table-card :deep(.p-datatable-thead > tr > th) {
  text-transform: uppercase;
  font-size: 0.72rem;
  letter-spacing: 0.06em;
  color: var(--p-text-muted-color);
  background: var(--p-surface-100);
}
.app-dark .table-card :deep(.p-datatable-thead > tr > th) {
  background: color-mix(in srgb, var(--p-surface-700) 45%, var(--p-surface-900));
}

/* Transition douce du survol des lignes (150ms, colors only) */
.table-card :deep(.p-datatable-tbody > tr) {
  transition: background-color 0.15s ease;
}
@media (prefers-reduced-motion: reduce) {
  .table-card :deep(.p-datatable-tbody > tr) { transition: none; }
}

/* ── Zebrage charte : alternance « un peu clair / plus fonce » ─────────── */
/* Point cle : la DataTable PrimeVue peint ses propres fonds OPAQUES (lignes,
   paginator) qui masquaient la carte -> on rend la table transparente dans
   .table-card et la charte peint tout (carte, zebrage, survol). */
.table-card :deep(.p-datatable),
.table-card :deep(.p-datatable-table),
.table-card :deep(.p-datatable-tbody > tr),
.table-card :deep(.p-paginator) {
  background: transparent;
}

.table-card :deep(.p-datatable-tbody > tr.p-row-odd) {
  background: color-mix(in srgb, var(--p-surface-100) 65%, transparent);
}
.app-dark .table-card :deep(.p-datatable-tbody > tr.p-row-odd) {
  background: color-mix(in srgb, var(--p-surface-700) 28%, transparent);
}
/* Le survol doit rester lisible par-dessus le zebrage (les deux modes) */
.table-card :deep(.p-datatable-tbody > tr:hover) {
  background: color-mix(in srgb, var(--p-surface-200) 60%, transparent);
}
.app-dark .table-card :deep(.p-datatable-tbody > tr:hover) {
  background: color-mix(in srgb, var(--p-surface-600) 30%, transparent);
}

/* ── Pagination : rapport + numeros de page centres ─────────────────────── */
.table-card :deep(.p-paginator) {
  justify-content: center;
  gap: 0.75rem;
  border-top: 1px solid color-mix(in srgb, var(--p-surface-500) 25%, transparent);
}
.table-card :deep(.p-paginator-current) {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .form-row { flex-direction: column; }
  .page-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .toolbar-filters {
    width: 100%;
  }
  .tab-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-wrapper {
    min-width: 0;
    width: 100%;
  }
  .toolbar-select {
    width: 100%;
  }
}
</style>
