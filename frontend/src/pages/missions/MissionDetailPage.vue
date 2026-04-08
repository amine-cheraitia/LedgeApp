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
import { useCommentaires } from '@/composables/useCommentaires'
import { useAuthStore } from '@/stores/auth'
import type { Mission, Tache, TacheCommentaire } from '@/types'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const { users, fetchUsers } = useUsers()
const { commentaires, loading: loadingCommentaires, fetchCommentaires, createCommentaire, updateCommentaire, deleteCommentaire } = useCommentaires()

const mission = ref<Mission | null>(null)
const taches = ref<Tache[]>([])
const loading = ref(true)
const updatingStatut = ref(false)
const loadingConvention = ref(false)
const loadingMandat = ref(false)
const togglingPortail = ref(false)
const tacheDialogVisible = ref(false)
const savingTache = ref(false)
const dateEcheance = ref<Date | null>(null)

// Commentaires — état par tâche
const expandedTacheIds = ref<Set<number>>(new Set())
const newCommentaire = ref<Record<number, string>>({})
const sendingCommentaire = ref<Record<number, boolean>>({})
const editingCommentaire = ref<{ tacheId: number; commentaire: TacheCommentaire } | null>(null)
const editContenu = ref('')

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

async function changerStatutMission(statut: string) {
  if (!mission.value) return
  updatingStatut.value = true
  try {
    const response = await missionsApi.update(missionId.value, { statut })
    mission.value = response.data
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Statut mis a jour.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre a jour le statut.', life: 3000 })
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
    toast.add({ severity: 'success', summary: 'Succes', detail: `Documents ${etat} dans le portail.`, life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier la visibilité.', life: 3000 })
  } finally {
    togglingPortail.value = false
  }
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

function openTacheDialog() {
  tacheForm.value = { titre: '', description: '', assigned_to: null, statut: 'a_faire', date_echeance: null, priorite: 1 }
  dateEcheance.value = null
  tacheDialogVisible.value = true
}

async function onSubmitTache() {
  if (!tacheForm.value.titre) return
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
    await tachesApi.update(missionId.value, tache.id, { statut: newStatut })
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Statut mis a jour.', life: 3000 })
    await loadTaches()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur mise a jour.', life: 3000 })
  }
}

function confirmDeleteTache(tache: Tache) {
  confirm.require({
    message: `Supprimer la tache "${tache.titre}" ?`,
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
    toast.add({ severity: 'success', summary: 'Succes', detail: 'Tache supprimee.', life: 3000 })
    await loadTaches()
  } catch (err: any) {
    const detail = err.response?.data?.message ?? 'Impossible de supprimer la tache.'
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 4000 })
  }
}

// ─── Commentaires ──────────────────────────────────────────────────────────

function isTacheStatutEditable(tache: Tache): boolean {
  if (auth.isAdmin || auth.isSecretaire) return true
  return auth.isCollaborateur && tache.assigned_to === auth.user?.id
}

async function toggleCommentaires(tache: Tache) {
  if (expandedTacheIds.value.has(tache.id)) {
    expandedTacheIds.value.delete(tache.id)
  } else {
    expandedTacheIds.value.add(tache.id)
    await fetchCommentaires(tache.id)
  }
}

function commentairesDeTache(tacheId: number): TacheCommentaire[] {
  return commentaires.value.filter((c: TacheCommentaire) => c.tache_id === tacheId)
}

async function envoyerCommentaire(tache: Tache) {
  const contenu = (newCommentaire.value[tache.id] ?? '').trim()
  if (!contenu) return
  sendingCommentaire.value[tache.id] = true
  try {
    await createCommentaire(tache.id, contenu)
    newCommentaire.value[tache.id] = ''
    await fetchCommentaires(tache.id)
  } finally {
    sendingCommentaire.value[tache.id] = false
  }
}

function ouvrirEditionCommentaire(tache: Tache, commentaire: TacheCommentaire) {
  editingCommentaire.value = { tacheId: tache.id, commentaire }
  editContenu.value = commentaire.contenu
}

async function sauvegarderEditionCommentaire() {
  if (!editingCommentaire.value) return
  const { tacheId, commentaire } = editingCommentaire.value
  await updateCommentaire(tacheId, commentaire.id, editContenu.value.trim())
  editingCommentaire.value = null
  editContenu.value = ''
}

function annulerEditionCommentaire() {
  editingCommentaire.value = null
  editContenu.value = ''
}

function peutModifierCommentaire(commentaire: TacheCommentaire): boolean {
  return auth.isAdmin || commentaire.user_id === auth.user?.id
}

function confirmDeleteCommentaire(tache: Tache, commentaire: TacheCommentaire) {
  confirm.require({
    message: 'Supprimer ce commentaire ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => supprimerCommentaire(tache, commentaire),
  })
}

async function supprimerCommentaire(tache: Tache, commentaire: TacheCommentaire) {
  await deleteCommentaire(tache.id, commentaire.id)
  await fetchCommentaires(tache.id)
}

function initiales(name: string | undefined): string {
  if (!name) return '?'
  return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
}

function formatDateRelative(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ──────────────────────────────────────────────────────────────────────────

function statutMissionSeverity(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger',
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

      <!-- Actions statut mission — admin et secretaire uniquement -->
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
        <div><strong>Entreprise</strong><br>{{ mission.entreprise?.raison_sociale ?? '-' }}</div>
        <div><strong>Prestation</strong><br>{{ mission.prestation?.designation ?? '-' }}</div>
        <div><strong>Prix HT</strong><br>{{ formatMontant(mission.prix_ht) }}</div>
        <div><strong>Debut</strong><br>{{ formatDate(mission.date_debut) }}</div>
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

    <!-- Documents mission — admin et secretaire uniquement -->
    <div v-if="!auth.isCollaborateur" class="section">
      <div class="section-header">
        <h2>Documents</h2>
        <!-- Toggle visible portail — admin uniquement -->
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
            :label="mission.convention_numero ? 'Imprimer la convention' : 'Générer la convention'"
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
            :label="mission.mandat_numero ? 'Imprimer le mandat' : 'Générer le mandat'"
            :icon="mission.mandat_numero ? 'pi pi-print' : 'pi pi-cog'"
            size="small"
            :loading="loadingMandat"
            aria-label="Télécharger le mandat d'acceptation"
            @click="telechargerMandat"
          />
        </div>
      </div>
    </div>

    <!-- Tranches suggérées — admin et secretaire uniquement -->
    <div v-if="!auth.isCollaborateur" class="section">
      <h2>Tranches de facturation suggerees</h2>
      <div class="tranches-grid">
        <div v-for="t in tranches" :key="t.label" class="tranche-card">
          <span>{{ t.label }}</span>
          <strong>{{ formatMontant(t.montant) }}</strong>
        </div>
      </div>
      <Button
        label="Creer une facture"
        icon="pi pi-receipt"
        severity="secondary"
        @click="router.push({ name: 'factures' })"
        class="mt-action"
      />
    </div>

    <!-- Factures liées — admin et secretaire uniquement -->
    <div v-if="!auth.isCollaborateur && mission.factures && mission.factures.length > 0" class="section">
      <h2>Factures liees</h2>
      <DataTable :value="mission.factures" dataKey="id" stripedRows>
        <Column field="numero" header="Numero" />
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
        <h2 id="taches-title">Taches</h2>
        <!-- Ajouter une tâche — admin et secretaire uniquement -->
        <Button
          v-if="!auth.isCollaborateur"
          label="Ajouter une tache"
          icon="pi pi-plus"
          size="small"
          aria-label="Ajouter une tache a cette mission"
          @click="openTacheDialog"
        />
      </div>

      <DataTable :value="taches" dataKey="id" stripedRows>
        <!-- Chevron expand commentaires -->
        <Column style="width: 3rem; padding-right: 0;">
          <template #body="{ data }">
            <Button
              :icon="expandedTacheIds.has(data.id) ? 'pi pi-chevron-down' : 'pi pi-chevron-right'"
              text
              rounded
              size="small"
              :aria-label="expandedTacheIds.has(data.id) ? `Masquer les commentaires de ${data.titre}` : `Voir les commentaires de ${data.titre}`"
              :aria-expanded="expandedTacheIds.has(data.id)"
              @click="toggleCommentaires(data)"
            />
          </template>
        </Column>

        <Column field="titre" header="Titre" />
        <Column header="Assignee">
          <template #body="{ data }">{{ data.assignee?.name ?? 'Non assigne' }}</template>
        </Column>
        <Column header="Echeance">
          <template #body="{ data }">{{ formatDate(data.date_echeance) }}</template>
        </Column>
        <Column header="Priorite" style="width: 6rem">
          <template #body="{ data }">{{ data.priorite }}</template>
        </Column>
        <Column header="Statut" style="width: 11rem">
          <template #body="{ data }">
            <Select
              :modelValue="data.statut"
              :options="statutOptions"
              optionLabel="label"
              optionValue="value"
              :disabled="!isTacheStatutEditable(data)"
              :aria-label="`Statut de la tache ${data.titre}`"
              style="min-width: 8rem;"
              @update:modelValue="(v) => updateTacheStatut(data, v)"
            />
          </template>
        </Column>
        <!-- Actions — admin et secretaire uniquement -->
        <Column v-if="!auth.isCollaborateur" header="Actions" style="width: 5rem">
          <template #body="{ data }">
            <Button
              icon="pi pi-trash"
              text
              rounded
              severity="danger"
              :aria-label="`Supprimer la tache ${data.titre}`"
              v-tooltip.top="'Supprimer'"
              @click="confirmDeleteTache(data)"
            />
          </template>
        </Column>

        <!-- Ligne expandable — commentaires -->
        <template #expansion="{ data: tache }">
          <div v-if="expandedTacheIds.has(tache.id)" class="commentaires-panel" role="region" :aria-labelledby="`commentaires-title-${tache.id}`">
            <h3 :id="`commentaires-title-${tache.id}`" class="commentaires-titre">
              Commentaires
              <span
                role="status"
                :aria-label="`${commentairesDeTache(tache.id).length} commentaire(s)`"
                class="commentaires-count"
              >{{ commentairesDeTache(tache.id).length }}</span>
            </h3>

            <!-- Liste des commentaires -->
            <div v-if="loadingCommentaires" class="commentaires-loading" aria-live="polite">
              <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
            </div>

            <ul v-else class="commentaires-liste" aria-live="polite">
              <li
                v-for="c in commentairesDeTache(tache.id)"
                :key="c.id"
                class="commentaire-item"
              >
                <!-- Mode lecture -->
                <template v-if="!editingCommentaire || editingCommentaire.commentaire.id !== c.id">
                  <div class="commentaire-avatar" aria-hidden="true">{{ initiales(c.user?.name) }}</div>
                  <div class="commentaire-body">
                    <div class="commentaire-meta">
                      <span class="commentaire-auteur">{{ c.user?.name ?? 'Inconnu' }}</span>
                      <span class="commentaire-date">{{ formatDateRelative(c.created_at) }}</span>
                      <span v-if="c.visible_portail && auth.isAdmin" class="commentaire-portail-badge">portail</span>
                    </div>
                    <p class="commentaire-contenu">{{ c.contenu }}</p>
                    <div v-if="peutModifierCommentaire(c)" class="commentaire-actions">
                      <Button
                        icon="pi pi-pencil"
                        text
                        rounded
                        size="small"
                        :aria-label="`Modifier le commentaire de ${c.user?.name}`"
                        v-tooltip.top="'Modifier'"
                        @click="ouvrirEditionCommentaire(tache, c)"
                      />
                      <Button
                        icon="pi pi-trash"
                        text
                        rounded
                        size="small"
                        severity="danger"
                        :aria-label="`Supprimer le commentaire de ${c.user?.name}`"
                        v-tooltip.top="'Supprimer'"
                        @click="confirmDeleteCommentaire(tache, c)"
                      />
                    </div>
                  </div>
                </template>

                <!-- Mode edition -->
                <template v-else>
                  <div class="commentaire-avatar" aria-hidden="true">{{ initiales(c.user?.name) }}</div>
                  <div class="commentaire-body commentaire-edit">
                    <label :for="`edit-commentaire-${c.id}`" class="sr-only">Modifier le commentaire</label>
                    <Textarea
                      :id="`edit-commentaire-${c.id}`"
                      v-model="editContenu"
                      rows="3"
                      fluid
                      aria-required="true"
                    />
                    <div class="commentaire-edit-actions">
                      <Button
                        label="Sauvegarder"
                        icon="pi pi-check"
                        size="small"
                        :disabled="!editContenu.trim()"
                        @click="sauvegarderEditionCommentaire"
                      />
                      <Button
                        label="Annuler"
                        severity="secondary"
                        size="small"
                        @click="annulerEditionCommentaire"
                      />
                    </div>
                  </div>
                </template>
              </li>

              <li v-if="commentairesDeTache(tache.id).length === 0" class="commentaire-vide">
                Aucun commentaire pour cette tache.
              </li>
            </ul>

            <!-- Formulaire nouveau commentaire -->
            <form class="commentaire-form" @submit.prevent="envoyerCommentaire(tache)">
              <label :for="`nouveau-commentaire-${tache.id}`" class="sr-only">Nouveau commentaire pour la tache {{ tache.titre }}</label>
              <Textarea
                :id="`nouveau-commentaire-${tache.id}`"
                v-model="newCommentaire[tache.id]"
                :placeholder="`Ajouter un commentaire sur «\u00a0${tache.titre}\u00a0»…`"
                rows="2"
                fluid
                aria-required="false"
              />
              <Button
                type="submit"
                label="Envoyer"
                icon="pi pi-send"
                size="small"
                :loading="sendingCommentaire[tache.id]"
                :disabled="!(newCommentaire[tache.id] ?? '').trim()"
                :aria-label="`Envoyer un commentaire sur la tache ${tache.titre}`"
              />
            </form>
          </div>
        </template>
      </DataTable>
    </section>

    <!-- Dialog ajout tâche -->
    <Dialog
      v-model:visible="tacheDialogVisible"
      header="Nouvelle tache"
      :modal="true"
      :style="{ width: '30rem' }"
    >
      <form @submit.prevent="onSubmitTache" class="dialog-form">
        <div class="form-field">
          <label for="t-titre">Titre *</label>
          <InputText id="t-titre" v-model="tacheForm.titre" required fluid />
        </div>

        <div class="form-field">
          <label for="t-assigne">Assigne a</label>
          <Select
            id="t-assigne"
            v-model="tacheForm.assigned_to"
            :options="users"
            optionLabel="name"
            optionValue="id"
            placeholder="Non assigne"
            showClear
            fluid
          />
        </div>

        <div class="form-field">
          <label for="t-echeance">Echeance</label>
          <DatePicker id="t-echeance" v-model="dateEcheance" dateFormat="dd/mm/yy" fluid />
        </div>

        <div class="form-field">
          <label for="t-desc">Description</label>
          <Textarea id="t-desc" v-model="tacheForm.description" rows="3" fluid />
        </div>

        <div class="form-actions">
          <Button
            label="Annuler"
            severity="secondary"
            type="button"
            @click="tacheDialogVisible = false"
          />
          <Button
            label="Creer"
            type="submit"
            icon="pi pi-check"
            :loading="savingTache"
            :disabled="!tacheForm.titre"
          />
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

/* ─── Commentaires ─────────────────────────────────────────────────────── */
.commentaires-panel {
  padding: 1rem 1.5rem 1rem 3rem;
  background: var(--p-surface-ground);
  border-top: 1px solid var(--p-surface-border);
}
.commentaires-titre {
  font-size: 0.9rem;
  font-weight: 600;
  margin: 0 0 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--p-text-color);
}
.commentaires-count {
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 1rem;
  padding: 0.1rem 0.5rem;
  min-width: 1.5rem;
  text-align: center;
}
.commentaires-loading {
  text-align: center;
  padding: 1rem;
  color: var(--p-text-muted-color);
}
.commentaires-liste {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.commentaire-item {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}
.commentaire-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: var(--p-primary-200);
  color: var(--p-primary-800);
  font-size: 0.7rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.commentaire-body {
  flex: 1;
  min-width: 0;
}
.commentaire-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
  flex-wrap: wrap;
}
.commentaire-auteur {
  font-weight: 600;
  font-size: 0.85rem;
}
.commentaire-date {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.commentaire-portail-badge {
  font-size: 0.65rem;
  background: var(--p-green-100);
  color: var(--p-green-700);
  border-radius: 0.25rem;
  padding: 0.1rem 0.4rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.commentaire-contenu {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.5;
  word-break: break-word;
  white-space: pre-wrap;
}
.commentaire-actions {
  display: flex;
  gap: 0.25rem;
  margin-top: 0.25rem;
}
.commentaire-edit {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.commentaire-edit-actions {
  display: flex;
  gap: 0.5rem;
}
.commentaire-vide {
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
  padding: 0.5rem 0;
}
.commentaire-form {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
}

/* Accessibilité — classe utilitaire screen-reader only */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 640px) {
  .tranches-grid { grid-template-columns: 1fr; }
  .header-actions { width: 100%; }
  .commentaires-panel { padding: 0.75rem 0.75rem 0.75rem 1rem; }
  .commentaire-form { flex-direction: column; }
}
</style>
