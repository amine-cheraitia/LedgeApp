<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import { tachesApi, type TachePayload } from '@/api/modules/taches'
import { useCommentaires } from '@/composables/useCommentaires'
import { useTacheConflits } from '@/composables/useTacheConflits'
import { useUsers } from '@/composables/useUsers'
import { useAuthStore } from '@/stores/authStore'
import type { Tache, TacheCommentaire } from '@/types'
import { PRIORITE_OPTIONS, prioriteLabel, prioriteSeverity } from '@/utils/priorite'
import { toIsoDate, parseIsoDate } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()
const { users, fetchUsers } = useUsers()
const {
  commentaires, loading: loadingCommentaires,
  fetchCommentaires, createCommentaire, updateCommentaire, deleteCommentaire,
} = useCommentaires()

const missionId = computed(() => Number(route.params.id))
const tacheId = computed(() => Number(route.params.tacheId))

const tache = ref<Tache | null>(null)
const loading = ref(true)

// Edit tache dialog
const editDialogVisible = ref(false)
const savingEdit = ref(false)
const editDateDebut = ref<Date | null>(null)
const editDateEcheance = ref<Date | null>(null)
const editForm = ref<TachePayload>({
  titre: '',
  description: '',
  assigned_to: null,
  statut: 'a_faire',
  priorite: 1,
})

// Détection réactive de conflit d'affectation (avertissement non bloquant à l'édition).
const { conflits: conflitsEdit } = useTacheConflits(
  computed(() => editForm.value.assigned_to),
  editDateDebut,
  editDateEcheance,
  computed(() => tache.value?.id ?? null),
)

// Comments
const newContenu = ref('')
const sending = ref(false)
const editingCommentaire = ref<TacheCommentaire | null>(null)
const editContenu = ref('')

const statutOptions = [
  { label: 'À faire', value: 'a_faire' },
  { label: 'En cours', value: 'en_cours' },
  { label: 'Terminée', value: 'terminee' },
  { label: 'Bloquée', value: 'bloquee' },
]

function statutSeverity(s: string) {
  const map: Record<string, string> = {
    a_faire: 'secondary', en_cours: 'info', terminee: 'success', bloquee: 'danger',
  }
  return map[s] ?? 'secondary'
}

function statutLabel(s: string) {
  const map: Record<string, string> = {
    a_faire: 'À faire', en_cours: 'En cours', terminee: 'Terminée', bloquee: 'Bloquée',
  }
  return map[s] ?? s
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function formatDateRelative(d: string) {
  return new Date(d).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function initiales(name: string | undefined) {
  if (!name) return '?'
  return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
}

const isStatutEditable = computed(() => {
  if (!tache.value) return false
  if (auth.isAdmin || auth.isSecretaire) return true
  return auth.isCollaborateur && tache.value.assigned_to === auth.user?.id
})

// Un collaborateur ne peut commenter qu'une tâche qui lui est affectée (l'admin commente partout).
const peutCommenter = computed(() =>
  auth.isAdmin || (auth.isCollaborateur && tache.value?.assigned_to === auth.user?.id),
)

function peutModifierCommentaire(c: TacheCommentaire) {
  const auteurId = c.user_id ?? c.user?.id
  return auth.isAdmin || auteurId === auth.user?.id
}

async function loadTache() {
  loading.value = true
  try {
    const res = await tachesApi.getOne(missionId.value, tacheId.value)
    tache.value = res.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Tâche introuvable.', life: 3000 })
    router.push({ name: 'mission-detail', params: { id: missionId.value } })
  } finally {
    loading.value = false
  }
}

function openEdit() {
  if (!tache.value) return
  editForm.value = {
    titre: tache.value.titre,
    description: tache.value.description ?? '',
    assigned_to: tache.value.assigned_to,
    statut: tache.value.statut,
    priorite: tache.value.priorite,
  }
  editDateDebut.value = parseIsoDate(tache.value.date_debut)
  editDateEcheance.value = parseIsoDate(tache.value.date_echeance)
  editDialogVisible.value = true
}

async function onSubmitEdit() {
  if (!tache.value) return
  savingEdit.value = true
  try {
    const res = await tachesApi.update(missionId.value, tache.value.id, {
      ...editForm.value,
      date_debut: toIsoDate(editDateDebut.value),
      date_echeance: toIsoDate(editDateEcheance.value),
    })
    tache.value = res.data
    editDialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Tâche mise à jour.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de modifier la tâche.', life: 3000 })
  } finally {
    savingEdit.value = false
  }
}

async function changerStatut(newStatut: string) {
  if (!tache.value) return
  try {
    const res = await tachesApi.update(missionId.value, tache.value.id, { statut: newStatut })
    tache.value = res.data
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Statut mis à jour.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur lors de la mise à jour.', life: 3000 })
  }
}

async function envoyerCommentaire() {
  const contenu = newContenu.value.trim()
  if (!contenu) return
  sending.value = true
  try {
    await createCommentaire(tacheId.value, contenu)
    newContenu.value = ''
    await fetchCommentaires(tacheId.value)
    // Reflète la transition automatique du backend (a_faire → en_cours au 1er commentaire)
    if (tache.value && tache.value.statut === 'a_faire') {
      tache.value.statut = 'en_cours'
    }
  } finally {
    sending.value = false
  }
}

function ouvrirEdition(c: TacheCommentaire) {
  editingCommentaire.value = c
  editContenu.value = c.contenu
}

async function sauvegarderEdition() {
  if (!editingCommentaire.value) return
  await updateCommentaire(tacheId.value, editingCommentaire.value.id, editContenu.value.trim())
  editingCommentaire.value = null
  editContenu.value = ''
  await fetchCommentaires(tacheId.value)
}

function annulerEdition() {
  editingCommentaire.value = null
  editContenu.value = ''
}

function confirmDeleteCommentaire(c: TacheCommentaire) {
  confirm.require({
    message: 'Supprimer ce commentaire ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: async () => {
      await deleteCommentaire(tacheId.value, c.id)
      await fetchCommentaires(tacheId.value)
    },
  })
}

onMounted(async () => {
  await loadTache()
  await fetchCommentaires(tacheId.value)
  // Seuls collaborateurs/admins sont assignables à une tâche.
  if (!auth.isCollaborateur) fetchUsers({ role: ['admin', 'collaborateur'], per_page: 100 })
})
</script>

<template>
  <div v-if="!loading && tache">
    <!-- En-tête -->
    <div class="page-header">
      <div class="header-left">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          aria-label="Retour à la mission"
          @click="router.push({ name: 'mission-detail', params: { id: missionId } })"
        />
        <div class="header-breadcrumb">
          <RouterLink :to="{ name: 'mission-detail', params: { id: missionId } }" class="breadcrumb-mission">
            Mission
          </RouterLink>
          <i class="pi pi-chevron-right breadcrumb-sep" aria-hidden="true"></i>
          <h1 class="header-titre">{{ tache.titre }}</h1>
        </div>
      </div>
      <div class="header-actions">
        <Tag
          :value="statutLabel(tache.statut)"
          :severity="statutSeverity(tache.statut)"
          style="font-size: 0.85rem;"
        />
        <Button
          v-if="!auth.isCollaborateur"
          label="Modifier"
          icon="pi pi-pencil"
          severity="secondary"
          size="small"
          aria-label="Modifier cette tâche"
          @click="openEdit"
        />
      </div>
    </div>

    <!-- Carte infos tâche -->
    <section class="tache-card" aria-labelledby="tache-info-title">
      <h2 id="tache-info-title" class="sr-only">Informations de la tâche</h2>

      <div class="tache-meta-grid">
        <div class="meta-item">
          <span class="meta-label">Assignée à</span>
          <div class="meta-value assignee-value">
            <span v-if="tache.assignee" class="assignee-avatar" aria-hidden="true">
              {{ initiales(tache.assignee.name) }}
            </span>
            <span>{{ tache.assignee?.name ?? 'Non assignée' }}</span>
          </div>
        </div>

        <div class="meta-item">
          <span class="meta-label">Début</span>
          <div class="meta-value">
            <i class="pi pi-calendar" aria-hidden="true" style="margin-right: 0.35rem; color: var(--p-text-muted-color);"></i>
            {{ formatDate(tache.date_debut) }}
          </div>
        </div>

        <div class="meta-item">
          <span class="meta-label">Échéance</span>
          <div class="meta-value">
            <i class="pi pi-calendar" aria-hidden="true" style="margin-right: 0.35rem; color: var(--p-text-muted-color);"></i>
            {{ formatDate(tache.date_echeance) }}
          </div>
        </div>

        <div class="meta-item">
          <span class="meta-label">Priorité</span>
          <Tag :value="prioriteLabel(tache.priorite)" :severity="prioriteSeverity(tache.priorite)" />
        </div>

        <div class="meta-item">
          <span class="meta-label">Statut</span>
          <Select
            v-if="isStatutEditable"
            :modelValue="tache.statut"
            :options="statutOptions"
            optionLabel="label"
            optionValue="value"
            :aria-label="`Statut de la tâche ${tache.titre}`"
            style="min-width: 9rem;"
            @update:modelValue="changerStatut"
          />
          <Tag v-else :value="statutLabel(tache.statut)" :severity="statutSeverity(tache.statut)" />
        </div>
      </div>

      <div v-if="tache.description" class="tache-description">
        <span class="meta-label">Description</span>
        <p class="description-text">{{ tache.description }}</p>
      </div>
    </section>

    <!-- Section commentaires -->
    <section class="commentaires-section" aria-labelledby="commentaires-heading">
      <div class="commentaires-header">
        <h2 id="commentaires-heading" class="commentaires-heading">
          <i class="pi pi-comments" aria-hidden="true"></i>
          Commentaires
          <span
            role="status"
            :aria-label="`${commentaires.length} commentaire(s)`"
            class="count-badge"
          >{{ commentaires.length }}</span>
        </h2>
      </div>

      <!-- Liste -->
      <div v-if="loadingCommentaires" class="commentaires-loading" aria-live="polite">
        <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
        <span>Chargement des commentaires…</span>
      </div>

      <ul v-else class="commentaires-liste" aria-live="polite">
        <li
          v-for="c in commentaires"
          :key="c.id"
          class="commentaire-item"
        >
          <!-- Mode lecture -->
          <template v-if="!editingCommentaire || editingCommentaire.id !== c.id">
            <div class="commentaire-avatar" aria-hidden="true">{{ initiales(c.user?.name) }}</div>
            <div class="commentaire-body">
              <div class="commentaire-meta">
                <div class="commentaire-meta-left">
                  <span class="commentaire-auteur">{{ c.user?.name ?? 'Inconnu' }}</span>
                  <span class="commentaire-sep" aria-hidden="true">·</span>
                  <time class="commentaire-date" :datetime="c.created_at" :title="c.created_at">
                    {{ formatDateRelative(c.created_at) }}
                  </time>
                  <Tag
                    v-if="c.visible_portail && auth.isAdmin"
                    value="portail"
                    severity="info"
                    class="portail-badge"
                  />
                </div>
                <div v-if="peutModifierCommentaire(c)" class="commentaire-actions">
                  <Button
                    icon="pi pi-pencil"
                    text
                    rounded
                    size="small"
                    :aria-label="`Modifier le commentaire de ${c.user?.name}`"
                    v-tooltip.top="'Modifier'"
                    @click="ouvrirEdition(c)"
                  />
                  <Button
                    icon="pi pi-trash"
                    text
                    rounded
                    size="small"
                    severity="danger"
                    :aria-label="`Supprimer le commentaire de ${c.user?.name}`"
                    v-tooltip.top="'Supprimer'"
                    @click="confirmDeleteCommentaire(c)"
                  />
                </div>
              </div>
              <p class="commentaire-contenu">{{ c.contenu }}</p>
            </div>
          </template>

          <!-- Mode édition -->
          <template v-else>
            <div class="commentaire-avatar" aria-hidden="true">{{ initiales(c.user?.name) }}</div>
            <div class="commentaire-body commentaire-edit-mode">
              <label :for="`edit-${c.id}`" class="sr-only">Modifier le commentaire</label>
              <Textarea
                :id="`edit-${c.id}`"
                v-model="editContenu"
                rows="3"
                fluid
                aria-required="true"
                style="resize: vertical;"
              />
              <div class="commentaire-edit-actions">
                <Button
                  label="Sauvegarder"
                  icon="pi pi-check"
                  size="small"
                  :disabled="!editContenu.trim()"
                  @click="sauvegarderEdition"
                />
                <Button
                  label="Annuler"
                  severity="secondary"
                  size="small"
                  @click="annulerEdition"
                />
              </div>
            </div>
          </template>
        </li>

        <li v-if="!loadingCommentaires && commentaires.length === 0" class="commentaire-vide">
          <i class="pi pi-inbox" aria-hidden="true" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
          <span>Aucun commentaire pour le moment.</span>
        </li>
      </ul>

      <!-- Formulaire nouveau commentaire (masqué si la tâche n'est pas affectée au collaborateur) -->
      <form v-if="peutCommenter" class="nouveau-commentaire-form" @submit.prevent="envoyerCommentaire">
        <label for="nouveau-commentaire" class="nouveau-commentaire-label">
          Ajouter un commentaire
        </label>
        <Textarea
          id="nouveau-commentaire"
          v-model="newContenu"
          placeholder="Écrivez votre commentaire…"
          rows="3"
          fluid
          style="resize: vertical;"
        />
        <div class="form-submit">
          <Button
            type="submit"
            label="Envoyer"
            icon="pi pi-send"
            :loading="sending"
            :disabled="!newContenu.trim()"
            aria-label="Envoyer le commentaire"
          />
        </div>
      </form>
    </section>

    <!-- Dialog modification tâche -->
    <Dialog
      v-model:visible="editDialogVisible"
      header="Modifier la tâche"
      :modal="true"
      :style="{ width: '32rem' }"
    >
      <form @submit.prevent="onSubmitEdit" class="dialog-form">
        <div class="form-field">
          <label for="e-titre">Titre *</label>
          <InputText id="e-titre" v-model="editForm.titre" required fluid />
        </div>

        <div class="form-field">
          <label for="e-desc">Description</label>
          <Textarea id="e-desc" v-model="editForm.description" rows="3" fluid />
        </div>

        <div class="form-field">
          <label for="e-assigne">Assignée à</label>
          <Select
            id="e-assigne"
            v-model="editForm.assigned_to"
            :options="users"
            optionLabel="name"
            optionValue="id"
            placeholder="Non assignée"
            showClear
            fluid
          />
        </div>

        <div class="form-field">
          <label for="e-debut">Début</label>
          <DatePicker id="e-debut" v-model="editDateDebut" dateFormat="dd/mm/yy" fluid />
        </div>

        <div class="form-field">
          <label for="e-echeance">Échéance</label>
          <DatePicker id="e-echeance" v-model="editDateEcheance" dateFormat="dd/mm/yy" :minDate="editDateDebut ?? undefined" fluid />
        </div>

        <div class="form-field">
          <label for="e-priorite">Priorité</label>
          <Select
            id="e-priorite"
            v-model="editForm.priorite"
            :options="PRIORITE_OPTIONS"
            optionLabel="label"
            optionValue="value"
            fluid
          />
        </div>

        <div class="form-field">
          <label for="e-statut">Statut</label>
          <Select
            id="e-statut"
            v-model="editForm.statut"
            :options="statutOptions"
            optionLabel="label"
            optionValue="value"
            fluid
          />
        </div>

        <div v-if="conflitsEdit.length" class="conflit-warning" role="status" aria-live="polite">
          <i class="pi pi-exclamation-triangle" aria-hidden="true" />
          <div>
            <strong>{{ conflitsEdit.length }} tâche(s)</strong> de ce collaborateur chevauchent cette période :
            <ul>
              <li v-for="c in conflitsEdit" :key="c.id">
                {{ c.titre }} ({{ formatDate(c.date_debut) }} → {{ formatDate(c.date_echeance) }}<template v-if="c.mission.reference">, {{ c.mission.reference }}</template>)
              </li>
            </ul>
          </div>
        </div>

        <div class="form-actions">
          <Button label="Annuler" severity="secondary" type="button" @click="editDialogVisible = false" />
          <Button label="Enregistrer" type="submit" icon="pi pi-check" :loading="savingEdit" :disabled="!editForm.titre" />
        </div>
      </form>
    </Dialog>
  </div>

  <div v-else-if="loading" class="loading-center" aria-busy="true" aria-label="Chargement de la tâche">
    <i class="pi pi-spin pi-spinner" style="font-size: 2rem;" aria-hidden="true"></i>
  </div>
</template>

<style scoped>
.conflit-warning {
  display: flex;
  gap: 0.6rem;
  align-items: flex-start;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.45);
  border-left: 4px solid #f59e0b;
  border-radius: 6px;
  padding: 0.65rem 0.85rem;
  font-size: 0.85rem;
  color: var(--p-text-color);
}
.conflit-warning i {
  color: var(--ledge-warning-bright);
  font-size: 1.05rem;
  margin-top: 0.1rem;
}
.conflit-warning ul {
  margin: 0.35rem 0 0;
  padding-left: 1.1rem;
}
.conflit-warning li {
  margin-top: 0.15rem;
}
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

/* ─── Header ─────────────────────────────────────────────────────────────── */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.header-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
}
.breadcrumb-mission {
  color: var(--p-primary-color);
  font-size: 0.9rem;
  white-space: nowrap;
  text-decoration: none;
}
.breadcrumb-mission:hover { text-decoration: underline; }
.breadcrumb-sep {
  font-size: 0.7rem;
  color: var(--p-text-muted-color);
}
.header-titre {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* ─── Carte tâche ─────────────────────────────────────────────────────────── */
.tache-card {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.tache-meta-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1.25rem;
  margin-bottom: 1rem;
}
.meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.meta-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
}
.meta-value {
  font-size: 0.95rem;
  color: var(--p-text-color);
  display: flex;
  align-items: center;
}
.assignee-value { gap: 0.5rem; }
.assignee-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
}
.tache-description {
  border-top: 1px solid var(--p-surface-border);
  padding-top: 1rem;
  margin-top: 0.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.description-text {
  margin: 0;
  line-height: 1.6;
  color: var(--p-text-color);
  white-space: pre-wrap;
}

/* ─── Section commentaires ────────────────────────────────────────────────── */
.commentaires-section {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 12px;
  overflow: hidden;
}
.commentaires-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--p-surface-border);
  background: var(--p-surface-ground);
}
.commentaires-heading {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.count-badge {
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 1rem;
  padding: 0.1rem 0.55rem;
  min-width: 1.5rem;
  text-align: center;
}
.commentaires-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
  color: var(--p-text-muted-color);
}
.commentaires-liste {
  list-style: none;
  padding: 0;
  margin: 0;
}
.commentaire-item {
  display: flex;
  gap: 0.875rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--p-surface-border);
}
.commentaire-item:last-child { border-bottom: none; }
.commentaire-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 0.1rem;
}
.commentaire-body {
  flex: 1;
  min-width: 0;
}
.commentaire-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  flex-wrap: wrap;
}
.commentaire-meta-left {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}
.commentaire-auteur {
  font-weight: 700;
  font-size: 0.875rem;
  color: var(--p-text-color);
}
.commentaire-sep {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}
.commentaire-date {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}
.portail-badge { font-size: 0.7rem; }
.commentaire-contenu {
  margin: 0;
  line-height: 1.65;
  white-space: pre-wrap;
  font-size: 0.9rem;
  color: var(--p-text-color);
}
.commentaire-actions {
  display: flex;
  gap: 0.1rem;
  flex-shrink: 0;
}
.commentaire-edit-mode {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.commentaire-edit-actions {
  display: flex;
  gap: 0.5rem;
}
.commentaire-vide {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  color: var(--p-text-muted-color);
  font-size: 0.9rem;
  text-align: center;
  gap: 0.4rem;
}

/* ─── Formulaire nouveau commentaire ──────────────────────────────────────── */
.nouveau-commentaire-form {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--p-surface-border);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.nouveau-commentaire-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-color);
}
.form-submit {
  display: flex;
  justify-content: flex-end;
}

/* ─── Dialog ──────────────────────────────────────────────────────────────── */
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

/* ─── Loader ──────────────────────────────────────────────────────────────── */
.loading-center {
  text-align: center;
  padding: 4rem;
}
</style>
