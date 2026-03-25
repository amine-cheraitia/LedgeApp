<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import { missionsApi } from '@/api/modules/missions'
import { tachesApi, type TachePayload } from '@/api/modules/taches'
import type { Mission, Tache } from '@/types'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const mission = ref<Mission | null>(null)
const taches = ref<Tache[]>([])
const loading = ref(true)
const tacheDialogVisible = ref(false)
const savingTache = ref(false)
const dateEcheance = ref<Date | null>(null)

const missionId = computed(() => Number(route.params.id))

const tacheForm = ref<TachePayload>({
  titre: '',
  description: '',
  assigned_to: null,
  statut: 'a_faire',
  date_echeance: null,
  priorite: 1,
})

const statutOptions = [
  { label: 'A faire', value: 'a_faire' },
  { label: 'En cours', value: 'en_cours' },
  { label: 'Terminee', value: 'terminee' },
  { label: 'Bloquee', value: 'bloquee' },
]

function toIsoDate(d: Date | null): string | null {
  if (!d) return null
  return d.toISOString().split('T')[0]
}

async function loadMission() {
  loading.value = true
  try {
    const response = await missionsApi.getOne(missionId.value)
    mission.value = response.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Mission introuvable.', life: 3000 })
    router.push({ name: 'missions' })
  } finally {
    loading.value = false
  }
}

async function loadTaches() {
  try {
    const response = await tachesApi.getAll(missionId.value)
    taches.value = response.data
  } catch {
    // silencieux
  }
}

function openTacheDialog() {
  tacheForm.value = { titre: '', description: '', assigned_to: null, statut: 'a_faire', date_echeance: null, priorite: 1 }
  dateEcheance.value = null
  tacheDialogVisible.value = true
}

async function onSubmitTache() {
  savingTache.value = true
  try {
    await tachesApi.create(missionId.value, {
      ...tacheForm.value,
      date_echeance: toIsoDate(dateEcheance.value),
    })
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Tache creee.', life: 3000 })
    tacheDialogVisible.value = false
    await loadTaches()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de creer la tache.', life: 3000 })
  } finally {
    savingTache.value = false
  }
}

async function updateTacheStatut(tache: Tache, newStatut: string) {
  try {
    await tachesApi.update(missionId.value, tache.id, { titre: tache.titre, statut: newStatut })
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Statut mis a jour.', life: 3000 })
    await loadTaches()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur mise a jour.', life: 3000 })
  }
}

function statutSeverity(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger',
    a_faire: 'secondary', bloquee: 'danger',
  }
  return map[statut] ?? 'secondary'
}

function formatDate(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('fr-FR')
}

function formatMontant(v: number) {
  return new Intl.NumberFormat('fr-DZ').format(v) + ' DA'
}

const tranches = computed(() => {
  if (!mission.value) return []
  const total = mission.value.prix_ht
  return [
    { label: 'Tranche 1 (30%)', montant: Math.round(total * 0.3) },
    { label: 'Tranche 2 (30%)', montant: Math.round(total * 0.3) },
    { label: 'Tranche 3 — solde (40%)', montant: Math.round(total * 0.4) },
  ]
})

function goToFactures() {
  router.push({ name: 'factures' })
}

onMounted(() => {
  loadMission()
  loadTaches()
})
</script>

<template>
  <div v-if="!loading && mission">
    <div class="page-header">
      <div>
        <Button icon="pi pi-arrow-left" text rounded @click="router.push({ name: 'missions' })" aria-label="Retour" />
        <h1 style="display: inline; margin-left: 0.5rem;">{{ mission.reference }}</h1>
        <Tag :value="mission.statut" :severity="statutSeverity(mission.statut)" style="margin-left: 0.75rem;" />
      </div>
    </div>

    <!-- Info mission -->
    <div class="mission-info">
      <div class="info-grid">
        <div><strong>Entreprise</strong><br>{{ mission.entreprise?.raison_sociale ?? '-' }}</div>
        <div><strong>Prestation</strong><br>{{ mission.prestation?.designation ?? '-' }}</div>
        <div><strong>Prix HT</strong><br>{{ formatMontant(mission.prix_ht) }}</div>
        <div><strong>Debut</strong><br>{{ formatDate(mission.date_debut) }}</div>
        <div><strong>Fin</strong><br>{{ formatDate(mission.date_fin) }}</div>
      </div>
      <div v-if="mission.notes" class="mission-notes">
        <strong>Notes :</strong> {{ mission.notes }}
      </div>
    </div>

    <!-- Tranches suggerees -->
    <div class="section">
      <h2>Tranches de facturation suggerees</h2>
      <div class="tranches-grid">
        <div v-for="t in tranches" :key="t.label" class="tranche-card">
          <span>{{ t.label }}</span>
          <strong>{{ formatMontant(t.montant) }}</strong>
        </div>
      </div>
      <Button label="Creer une facture" icon="pi pi-receipt" severity="secondary" @click="goToFactures" class="mt-action" />
    </div>

    <!-- Factures liees -->
    <div class="section" v-if="mission.factures && mission.factures.length > 0">
      <h2>Factures liees</h2>
      <DataTable :value="mission.factures" dataKey="id" stripedRows>
        <Column field="numero" header="Numero" />
        <Column header="Montant TTC">
          <template #body="{ data }">{{ formatMontant(data.montant_ttc) }}</template>
        </Column>
        <Column header="Statut">
          <template #body="{ data }">
            <Tag :value="data.statut_paiement" :severity="statutSeverity(data.statut_paiement === 'solde' ? 'terminee' : data.statut_paiement === 'partiel' ? 'en_cours' : 'a_faire')" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Taches -->
    <div class="section">
      <div class="section-header">
        <h2>Taches</h2>
        <Button label="Ajouter" icon="pi pi-plus" size="small" @click="openTacheDialog" />
      </div>

      <DataTable :value="taches" dataKey="id" stripedRows>
        <Column field="titre" header="Titre" />
        <Column header="Assignee">
          <template #body="{ data }">{{ data.assignee?.name ?? 'Non assigne' }}</template>
        </Column>
        <Column header="Echeance">
          <template #body="{ data }">{{ formatDate(data.date_echeance) }}</template>
        </Column>
        <Column header="Statut">
          <template #body="{ data }">
            <Select
              :modelValue="data.statut"
              :options="statutOptions"
              optionLabel="label"
              optionValue="value"
              @update:modelValue="(v: string) => updateTacheStatut(data, v)"
              style="min-width: 8rem;"
            />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Dialog ajout tache -->
    <Dialog v-model:visible="tacheDialogVisible" header="Nouvelle tache" :modal="true" :style="{ width: '28rem' }">
      <form @submit.prevent="onSubmitTache" class="dialog-form">
        <div class="form-field">
          <label for="t-titre">Titre *</label>
          <InputText id="t-titre" v-model="tacheForm.titre" required fluid />
        </div>
        <div class="form-field">
          <label for="t-desc">Description</label>
          <Textarea id="t-desc" v-model="tacheForm.description" rows="3" fluid />
        </div>
        <div class="form-field">
          <label for="t-echeance">Echeance</label>
          <DatePicker id="t-echeance" v-model="dateEcheance" dateFormat="dd/mm/yy" fluid />
        </div>
        <div class="form-actions">
          <Button label="Annuler" severity="secondary" @click="tacheDialogVisible = false" type="button" />
          <Button label="Creer" type="submit" :loading="savingTache" />
        </div>
      </form>
    </Dialog>
  </div>
  <div v-else-if="loading" style="text-align: center; padding: 3rem;">
    <i class="pi pi-spin pi-spinner" style="font-size: 2rem;"></i>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 1.5rem;
}
.mission-info {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}
.mission-notes {
  margin-top: 1rem;
  color: var(--p-text-muted-color);
}
.section {
  margin-bottom: 2rem;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}
.tranches-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}
.tranche-card {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.mt-action { margin-top: 0.5rem; }
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
