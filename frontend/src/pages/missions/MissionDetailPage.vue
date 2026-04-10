<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
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
import { useUsers } from '@/composables/useUsers'
import { useAuthStore } from '@/stores/auth'
import type { Mission, Tache } from '@/types'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const { users, fetchUsers } = useUsers()

const mission = ref<Mission | null>(null)
const taches = ref<Tache[]>([])
const loading = ref(true)
const updatingStatut = ref(false)
const loadingConvention = ref(false)
const loadingMandat = ref(false)
const togglingPortail = ref(false)

// Dialog création tâche
const createDialogVisible = ref(false)
const savingCreate = ref(false)
const createDateEcheance = ref<Date | null>(null)
const createForm = ref<TachePayload>({
  titre: '',
  description: '',
  assigned_to: null,
  statut: 'a_faire',
  date_echeance: null,
  priorite: 1,
})

// Dialog modification tâche
const editDialogVisible = ref(false)
const savingEdit = ref(false)
const editTache = ref<Tache | null>(null)
const editDateEcheance = ref<Date | null>(null)
const editForm = ref<TachePayload>({
  titre: '',
  description: '',
  assigned_to: null,
  statut: 'a_faire',
  priorite: 1,
})

const missionId = computed(() => Number(route.params.id))

const prioriteOptions = [
  { label: 'Faible', value: 1 },
  { label: 'Normale', value: 2 },
  { label: 'Haute', value: 3 },
  { label: 'Urgente', value: 4 },
]

const statutTacheOptions = [
  { label: 'À faire', value: 'a_faire' },
  { label: 'En cours', value: 'en_cours' },
  { label: 'Terminée', value: 'terminee' },
  { label: 'Bloquée', value: 'bloquee' },
]

function toIsoDate(d: Date | null): string | null {
  return d ? d.toISOString().split('T')[0] : null
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

async function changerStatutMission(statut: string) {
  if (!mission.value) return
  updatingStatut.value = true
  try {
    const response = await missionsApi.update(missionId.value, { statut })
    mission.value = response.data
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Statut mis à jour.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour le statut.', life: 3000 })
  } finally {
    updatingStatut.value = false
  }
}

async function toggleVisiblePortail() {
  if (!mission.value) return
  togglingPortail.value = true
  try {
    const response = await missionsApi.update(missionId.value, { visible_portail: !mission.value.visible_portail })
    mission.value = response.data
    const etat = mission.value.visible_portail ? 'visible' : 'masqué'
    toast.add({ severity: 'success', summary: 'Succès', detail: `Documents ${etat} dans le portail.`, life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier la visibilité.', life: 3000 })
  } finally {
    togglingPortail.value = false
  }
}

function telechargerRapport() {
  window.open(missionsApi.rapportPdfUrl(missionId.value), '_blank')
}

async function telechargerConvention() {
  if (!mission.value) return
  loadingConvention.value = true
  try {
    window.open(missionsApi.conventionPdfUrl(missionId.value), '_blank')
    await loadMission()
  } finally {
    loadingConvention.value = false
  }
}

async function telechargerMandat() {
  if (!mission.value) return
  loadingMandat.value = true
  try {
    window.open(missionsApi.mandatPdfUrl(missionId.value), '_blank')
    await loadMission()
  } finally {
    loadingMandat.value = false
  }
}

// ─── Tâches ────────────────────────────────────────────────────────────────

function openCreateDialog() {
  createForm.value = { titre: '', description: '', assigned_to: null, statut: 'a_faire', date_echeance: null, priorite: 1 }
  createDateEcheance.value = null
  createDialogVisible.value = true
}

async function onSubmitCreate() {
  if (!createForm.value.titre) return
  savingCreate.value = true
  try {
    await tachesApi.create(missionId.value, {
      ...createForm.value,
      date_echeance: toIsoDate(createDateEcheance.value),
    })
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Tâche créée.', life: 3000 })
    createDialogVisible.value = false
    await loadTaches()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer la tâche.', life: 3000 })
  } finally {
    savingCreate.value = false
  }
}

function openEditDialog(tache: Tache) {
  editTache.value = tache
  editForm.value = {
    titre: tache.titre,
    description: tache.description ?? '',
    assigned_to: tache.assigned_to,
    statut: tache.statut,
    priorite: tache.priorite,
  }
  editDateEcheance.value = tache.date_echeance ? new Date(tache.date_echeance) : null
  editDialogVisible.value = true
}

async function onSubmitEdit() {
  if (!editTache.value) return
  savingEdit.value = true
  try {
    await tachesApi.update(missionId.value, editTache.value.id, {
      ...editForm.value,
      date_echeance: toIsoDate(editDateEcheance.value),
    })
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Tâche modifiée.', life: 3000 })
    editDialogVisible.value = false
    await loadTaches()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier la tâche.', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

function goToTache(tache: Tache) {
  router.push({ name: 'tache-detail', params: { id: missionId.value, tacheId: tache.id } })
}

function confirmDeleteTache(tache: Tache) {
  confirm.require({
    message: `Supprimer la tâche "${tache.titre}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteTache(tache),
  })
}

async function deleteTache(tache: Tache) {
  try {
    await tachesApi.delete(missionId.value, tache.id)
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Tâche supprimée.', life: 3000 })
    await loadTaches()
  } catch (err: any) {
    const detail = err.response?.data?.message ?? 'Impossible de supprimer la tâche.'
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 4000 })
  }
}

// ─── Utilitaires ──────────────────────────────────────────────────────────

function statutMissionSeverity(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger',
  }
  return map[statut] ?? 'secondary'
}

function statutTacheSeverity(statut: string) {
  const map: Record<string, string> = {
    a_faire: 'secondary', en_cours: 'info', terminee: 'success', bloquee: 'danger',
  }
  return map[statut] ?? 'secondary'
}

function statutTacheLabel(statut: string) {
  const map: Record<string, string> = {
    a_faire: 'À faire', en_cours: 'En cours', terminee: 'Terminée', bloquee: 'Bloquée',
  }
  return map[statut] ?? statut
}

function prioriteLabel(p: number) {
  return ['', 'Faible', 'Normale', 'Haute', 'Urgente'][p] ?? p.toString()
}

function prioriteSeverity(p: number) {
  return ['', 'secondary', 'info', 'warn', 'danger'][p] ?? 'secondary'
}

function formatDate(d: string | null) {
  if (!d) return '—'
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

onMounted(() => {
  loadMission()
  loadTaches()
  if (!auth.isCollaborateur) fetchUsers()
})
</script>

<template>
  <div v-if="!loading && mission">
    <div class="page-header">
      <div class="header-left">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          aria-label="Retour aux missions"
          @click="router.push({ name: 'missions' })"
        />
        <h1 style="display: inline; margin-left: 0.5rem;">{{ mission.reference }}</h1>
        <Tag
          :value="mission.statut"
          :severity="statutMissionSeverity(mission.statut)"
          style="margin-left: 0.75rem;"
        />
      </div>

      <div
        v-if="!auth.isCollaborateur && mission.statut !== 'terminee' && mission.statut !== 'annulee'"
        class="header-actions"
      >
        <Button
          v-if="mission.statut === 'suspendue'"
          label="Reprendre"
          icon="pi pi-play"
          severity="info"
          size="small"
          :loading="updatingStatut"
          @click="changerStatutMission('en_cours')"
        />
        <Button
          v-if="mission.statut === 'en_cours'"
          label="Suspendre"
          icon="pi pi-pause"
          severity="warn"
          size="small"
          :loading="updatingStatut"
          @click="changerStatutMission('suspendue')"
        />
        <Button
          v-if="mission.statut === 'en_cours'"
          label="Terminer"
          icon="pi pi-check"
          severity="success"
          size="small"
          :loading="updatingStatut"
          @click="changerStatutMission('terminee')"
        />
        <Button
          label="Annuler"
          icon="pi pi-times"
          severity="danger"
          size="small"
          outlined
          :loading="updatingStatut"
          @click="changerStatutMission('annulee')"
        />
      </div>
    </div>

    <!-- Info mission -->
    <div class="mission-info">
      <div class="info-grid">
        <div><strong>Entreprise</strong><br>{{ mission.entreprise?.raison_sociale ?? '—' }}</div>
        <div><strong>Prestation</strong><br>{{ mission.prestation?.designation ?? '—' }}</div>
        <div><strong>Prix HT</strong><br>{{ formatMontant(mission.prix_ht) }}</div>
        <div><strong>Début</strong><br>{{ formatDate(mission.date_debut) }}</div>
        <div><strong>Fin</strong><br>{{ formatDate(mission.date_fin) }}</div>
      </div>

      <div v-if="mission.collaborateurs && mission.collaborateurs.length > 0" class="collaborateurs">
        <strong>Collaborateurs :</strong>
        <span v-for="c in mission.collaborateurs" :key="c.id" class="collab-chip">{{ c.name }}</span>
      </div>

      <div v-if="mission.notes" class="mission-notes">
        <strong>Notes :</strong> {{ mission.notes }}
      </div>
    </div>

    <!-- Documents -->
    <div v-if="!auth.isCollaborateur" class="section">
      <div class="section-header">
        <h2>Documents</h2>
        <Button
          v-if="auth.isAdmin"
          :label="mission.visible_portail ? 'Visible portail' : 'Masqué portail'"
          :icon="mission.visible_portail ? 'pi pi-eye' : 'pi pi-eye-slash'"
          :severity="mission.visible_portail ? 'success' : 'secondary'"
          size="small"
          outlined
          :loading="togglingPortail"
          :aria-label="mission.visible_portail ? 'Masquer les documents du portail client' : 'Rendre les documents visibles dans le portail client'"
          :disabled="!mission.convention_numero && !mission.mandat_numero"
          @click="toggleVisiblePortail"
        />
      </div>
      <div class="documents-grid">
        <div class="doc-card">
          <div class="doc-info">
            <i class="pi pi-file-pdf" style="font-size: 1.4rem; color: #1e3a5f;" aria-hidden="true"></i>
            <div>
              <div class="doc-label">Convention de prestation</div>
              <div class="doc-ref" v-if="mission.convention_numero">{{ mission.convention_numero }}</div>
              <div class="doc-ref" v-else style="color: #94a3b8;">Non générée</div>
            </div>
          </div>
          <Button
            :label="mission.convention_numero ? 'Imprimer' : 'Générer'"
            :icon="mission.convention_numero ? 'pi pi-print' : 'pi pi-cog'"
            severity="secondary"
            size="small"
            :loading="loadingConvention"
            aria-label="Télécharger la convention de prestation"
            @click="telechargerConvention"
          />
        </div>

        <div class="doc-card">
          <div class="doc-info">
            <i class="pi pi-file-pdf" style="font-size: 1.4rem; color: #1e3a5f;" aria-hidden="true"></i>
            <div>
              <div class="doc-label">Mandat d'acceptation</div>
              <div class="doc-ref" v-if="mission.mandat_numero">{{ mission.mandat_numero }}</div>
              <div class="doc-ref" v-else style="color: #94a3b8;">Non généré</div>
            </div>
          </div>
          <Button
            :label="mission.mandat_numero ? 'Imprimer' : 'Générer'"
            :icon="mission.mandat_numero ? 'pi pi-print' : 'pi pi-cog'"
            size="small"
            :loading="loadingMandat"
            aria-label="Télécharger le mandat d'acceptation"
            @click="telechargerMandat"
          />
        </div>

        <div class="doc-card">
          <div class="doc-info">
            <i class="pi pi-file-pdf" style="font-size: 1.4rem; color: #c0392b;" aria-hidden="true"></i>
            <div>
              <div class="doc-label">Rapport de fin de mission</div>
              <div class="doc-ref">Tâches · Commentaires · Facturation</div>
            </div>
          </div>
          <Button
            label="Générer"
            icon="pi pi-download"
            severity="secondary"
            size="small"
            aria-label="Télécharger le rapport de fin de mission en PDF"
            @click="telechargerRapport"
          />
        </div>
      </div>
    </div>

    <!-- Tranches -->
    <div v-if="!auth.isCollaborateur" class="section">
      <h2>Tranches de facturation suggérées</h2>
      <div class="tranches-grid">
        <div v-for="t in tranches" :key="t.label" class="tranche-card">
          <span>{{ t.label }}</span>
          <strong>{{ formatMontant(t.montant) }}</strong>
        </div>
      </div>
      <Button
        label="Créer une facture"
        icon="pi pi-receipt"
        severity="secondary"
        @click="router.push({ name: 'factures' })"
        class="mt-action"
      />
    </div>

    <!-- Factures liées -->
    <div v-if="!auth.isCollaborateur && mission.factures && mission.factures.length > 0" class="section">
      <h2>Factures liées</h2>
      <DataTable :value="mission.factures" dataKey="id" stripedRows>
        <Column field="numero" header="Numéro" />
        <Column header="Montant TTC">
          <template #body="{ data }">{{ formatMontant(data.montant_ttc) }}</template>
        </Column>
        <Column header="Statut paiement">
          <template #body="{ data }">
            <Tag
              :value="data.statut_paiement"
              :severity="data.statut_paiement === 'solde' ? 'success' : data.statut_paiement === 'partiel' ? 'warn' : 'secondary'"
            />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Tâches -->
    <section aria-labelledby="taches-title" class="section">
      <div class="section-header">
        <h2 id="taches-title">Tâches <span class="taches-count">{{ taches.length }}</span></h2>
        <Button
          v-if="!auth.isCollaborateur"
          label="Ajouter une tâche"
          icon="pi pi-plus"
          size="small"
          aria-label="Ajouter une tâche à cette mission"
          @click="openCreateDialog"
        />
      </div>

      <DataTable :value="taches" dataKey="id" stripedRows>
        <Column field="titre" header="Titre" />
        <Column header="Assignée à">
          <template #body="{ data }">
            <div v-if="data.assignee" class="assignee-cell">
              <span class="assignee-avatar-sm" aria-hidden="true">
                {{ data.assignee.name.split(' ').map((p: string) => p[0]).join('').toUpperCase().slice(0, 2) }}
              </span>
              {{ data.assignee.name }}
            </div>
            <span v-else class="text-muted">Non assignée</span>
          </template>
        </Column>
        <Column header="Échéance" style="width: 8rem;">
          <template #body="{ data }">{{ formatDate(data.date_echeance) }}</template>
        </Column>
        <Column header="Priorité" style="width: 7rem;">
          <template #body="{ data }">
            <Tag :value="prioriteLabel(data.priorite)" :severity="prioriteSeverity(data.priorite)" />
          </template>
        </Column>
        <Column header="Statut" style="width: 8rem;">
          <template #body="{ data }">
            <Tag :value="statutTacheLabel(data.statut)" :severity="statutTacheSeverity(data.statut)" />
          </template>
        </Column>
        <Column header="Actions" style="width: 9rem;">
          <template #body="{ data }">
            <div class="actions-cell">
              <Button
                icon="pi pi-eye"
                text
                rounded
                size="small"
                :aria-label="`Voir la tâche ${data.titre}`"
                v-tooltip.top="'Voir'"
                @click="goToTache(data)"
              />
              <Button
                v-if="!auth.isCollaborateur"
                icon="pi pi-pencil"
                text
                rounded
                size="small"
                severity="secondary"
                :aria-label="`Modifier la tâche ${data.titre}`"
                v-tooltip.top="'Modifier'"
                @click="openEditDialog(data)"
              />
              <Button
                v-if="!auth.isCollaborateur"
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="danger"
                :aria-label="`Supprimer la tâche ${data.titre}`"
                v-tooltip.top="'Supprimer'"
                @click="confirmDeleteTache(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </section>

    <!-- Dialog création tâche -->
    <Dialog v-model:visible="createDialogVisible" header="Nouvelle tâche" :modal="true" :style="{ width: '30rem' }">
      <form @submit.prevent="onSubmitCreate" class="dialog-form">
        <div class="form-field">
          <label for="c-titre">Titre *</label>
          <InputText id="c-titre" v-model="createForm.titre" required fluid />
        </div>
        <div class="form-field">
          <label for="c-assigne">Assignée à</label>
          <Select id="c-assigne" v-model="createForm.assigned_to" :options="users" optionLabel="name" optionValue="id" placeholder="Non assignée" showClear fluid />
        </div>
        <div class="form-field">
          <label for="c-echeance">Échéance</label>
          <DatePicker id="c-echeance" v-model="createDateEcheance" dateFormat="dd/mm/yy" fluid />
        </div>
        <div class="form-field">
          <label for="c-priorite">Priorité</label>
          <Select id="c-priorite" v-model="createForm.priorite" :options="prioriteOptions" optionLabel="label" optionValue="value" fluid />
        </div>
        <div class="form-field">
          <label for="c-desc">Description</label>
          <Textarea id="c-desc" v-model="createForm.description" rows="3" fluid />
        </div>
        <div class="form-actions">
          <Button label="Annuler" severity="secondary" type="button" @click="createDialogVisible = false" />
          <Button label="Créer" type="submit" icon="pi pi-check" :loading="savingCreate" :disabled="!createForm.titre" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog modification tâche -->
    <Dialog v-model:visible="editDialogVisible" header="Modifier la tâche" :modal="true" :style="{ width: '30rem' }">
      <form @submit.prevent="onSubmitEdit" class="dialog-form">
        <div class="form-field">
          <label for="e-titre">Titre *</label>
          <InputText id="e-titre" v-model="editForm.titre" required fluid />
        </div>
        <div class="form-field">
          <label for="e-assigne">Assignée à</label>
          <Select id="e-assigne" v-model="editForm.assigned_to" :options="users" optionLabel="name" optionValue="id" placeholder="Non assignée" showClear fluid />
        </div>
        <div class="form-field">
          <label for="e-echeance">Échéance</label>
          <DatePicker id="e-echeance" v-model="editDateEcheance" dateFormat="dd/mm/yy" fluid />
        </div>
        <div class="form-field">
          <label for="e-priorite">Priorité</label>
          <Select id="e-priorite" v-model="editForm.priorite" :options="prioriteOptions" optionLabel="label" optionValue="value" fluid />
        </div>
        <div class="form-field">
          <label for="e-statut">Statut</label>
          <Select id="e-statut" v-model="editForm.statut" :options="statutTacheOptions" optionLabel="label" optionValue="value" fluid />
        </div>
        <div class="form-field">
          <label for="e-desc">Description</label>
          <Textarea id="e-desc" v-model="editForm.description" rows="3" fluid />
        </div>
        <div class="form-actions">
          <Button label="Annuler" severity="secondary" type="button" @click="editDialogVisible = false" />
          <Button label="Enregistrer" type="submit" icon="pi pi-check" :loading="savingEdit" :disabled="!editForm.titre" />
        </div>
      </form>
    </Dialog>
  </div>

  <div v-else-if="loading" class="loading-center" aria-busy="true" aria-label="Chargement de la mission">
    <i class="pi pi-spin pi-spinner" style="font-size: 2rem;" aria-hidden="true"></i>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.header-left { display: flex; align-items: center; }
.header-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
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
.collaborateurs {
  margin-top: 1rem;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.collab-chip {
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  border-radius: 1rem;
  padding: 0.2rem 0.75rem;
  font-size: 0.875rem;
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
.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
}
.doc-card {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.doc-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.doc-label { font-weight: 600; font-size: 0.9rem; }
.doc-ref { font-size: 0.8rem; color: var(--p-text-muted-color); margin-top: 2px; }
.loading-center { text-align: center; padding: 3rem; }
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

/* Tâches */
.taches-count {
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 1rem;
  padding: 0.1rem 0.55rem;
  margin-left: 0.4rem;
  vertical-align: middle;
}
.actions-cell {
  display: flex;
  gap: 0.1rem;
}
.assignee-cell {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.assignee-avatar-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.65rem;
  font-weight: 700;
  flex-shrink: 0;
}
.text-muted { color: var(--p-text-muted-color); font-size: 0.875rem; }
</style>
