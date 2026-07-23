<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { useUsers } from '@/composables/useUsers'
import type { User } from '@/types'
import type { UserPayload } from '@/api/modules/users'

const confirm = useConfirm()
const toast = useToast()
const {
  users, loading, totalRecords, filters,
  fetchUsers, createUser, updateUser, deleteUser, resendInvitation,
  onPage, onSearch,
} = useUsers()

const search = ref('')
const dialogVisible = ref(false)
const editMode = ref(false)
const saving = ref(false)

// Invitation (lien de definition de mot de passe — repli copiable sans mot de passe)
const invitationDialogVisible = ref(false)
const invitationEmail = ref('')
const invitationLink = ref('')

const emptyForm = (): UserPayload => ({
  name: '',
  email: '',
  role: 'collaborateur',
  entreprise_id: null,
})

const form = ref<UserPayload>(emptyForm())
const editId = ref<number | null>(null)

const roleOptions = [
  { label: 'Admin', value: 'admin' },
  { label: 'Collaborateur', value: 'collaborateur' },
  { label: 'Secretaire', value: 'secretaire' },
]

function roleColor(role: string) {
  const colors: Record<string, string> = {
    admin: 'danger',
    collaborateur: 'info',
    secretaire: 'warn',
    client: 'success',
  }
  return (colors[role] ?? 'secondary') as 'danger' | 'info' | 'warn' | 'success' | 'secondary'
}

function openCreate() {
  form.value = emptyForm()
  editId.value = null
  editMode.value = false
  dialogVisible.value = true
}

function openEdit(user: User) {
  form.value = {
    name: user.name,
    email: user.email,
    role: user.roles[0] ?? 'collaborateur',
    entreprise_id: user.entreprise_id,
  }
  editId.value = user.id
  editMode.value = true
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    if (editMode.value && editId.value) {
      await updateUser(editId.value, form.value)
      dialogVisible.value = false
    } else {
      const result = await createUser(form.value)
      dialogVisible.value = false
      showInvitation(form.value.email, result.invitation_url)
    }
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

function showInvitation(email: string, link: string) {
  invitationEmail.value = email
  invitationLink.value = link
  invitationDialogVisible.value = true
}

function onResendInvitation(user: User) {
  confirm.require({
    message: `Renvoyer une invitation a « ${user.name} » ? Un nouveau lien sera envoye a ${user.email} et l'ancien lien deviendra invalide.`,
    header: 'Renvoyer l\'invitation',
    icon: 'pi pi-envelope',
    acceptLabel: 'Renvoyer',
    rejectLabel: 'Annuler',
    accept: () => doResendInvitation(user),
  })
}

async function doResendInvitation(user: User) {
  try {
    const result = await resendInvitation(user.id)
    showInvitation(user.email, result.invitation_url)
  } catch {
    // erreur geree par le composable
  }
}

function copyInvitationLink() {
  navigator.clipboard.writeText(invitationLink.value)
  toast.add({ severity: 'info', summary: 'Copie', detail: 'Lien d\'invitation copie dans le presse-papier.', life: 2000 })
}

function confirmDelete(user: User) {
  confirm.require({
    message: `Supprimer "${user.name}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteUser(user.id),
  })
}

function handleSearch() {
  onSearch(search.value)
}

onMounted(fetchUsers)
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1>Utilisateurs</h1>
        <p v-if="totalRecords" class="page-compteurs">
          {{ totalRecords }} utilisateur{{ totalRecords > 1 ? 's' : '' }}
        </p>
      </div>
      <Button label="Nouvel utilisateur" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <div class="toolbar-filters">
        <form @submit.prevent="handleSearch" role="search" class="search-wrapper">
          <label for="search-users" class="sr-only">Rechercher un utilisateur</label>
          <i class="pi pi-search search-icon" aria-hidden="true" />
          <InputText
            id="search-users"
            v-model="search"
            class="search-input"
            placeholder="Rechercher..."
          />
          <Button icon="pi pi-search" aria-label="Lancer la recherche" type="submit" text rounded size="small" class="search-submit" @click="handleSearch" />
        </form>
      </div>
    </div>

    <div class="table-card">
    <DataTable aria-label="Liste des utilisateurs"
      :value="users"
      :loading="loading"
      :paginator="true"
      :rows="filters.per_page"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
      paginatorTemplate="CurrentPageReport PrevPageLink PageLinks NextPageLink"
      currentPageReportTemplate="{totalRecords} résultat(s) · Page {currentPage} sur {totalPages}"
    >
      <Column field="name" header="Nom" />
      <Column field="email" header="Email" />
      <Column header="Role">
        <template #body="{ data }">
          <Tag v-for="role in data.roles" :key="role" :value="role" :severity="roleColor(role)" />
        </template>
      </Column>
      <Column field="created_at" header="Cree le">
        <template #body="{ data }">
          {{ new Date(data.created_at).toLocaleDateString('fr-FR') }}
        </template>
      </Column>
      <Column header="Actions" style="width: 11rem">
        <template #body="{ data }">
          <div class="actions-cell">
            <Button icon="pi pi-pencil" text rounded severity="info" size="small" aria-label="Modifier" v-tooltip.top="'Modifier'" @click="openEdit(data)" />
            <Button icon="pi pi-envelope" text rounded severity="secondary" size="small" :aria-label="`Renvoyer l'invitation a ${data.name}`" v-tooltip.top="'Renvoyer l\'invitation'" @click="onResendInvitation(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" size="small" aria-label="Supprimer" v-tooltip.top="'Supprimer'" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
    </DataTable>
    </div>

    <Dialog
      v-model:visible="dialogVisible"
      :header="editMode ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'"
      :modal="true"
      :style="{ width: '30rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div class="form-field">
          <label for="user-name">Nom *</label>
          <InputText id="user-name" v-model="form.name" required fluid />
        </div>

        <div class="form-field">
          <label for="user-email">Email *</label>
          <InputText id="user-email" v-model="form.email" type="email" required fluid />
        </div>

        <div class="form-field">
          <label for="user-role">Role *</label>
          <Select id="user-role" v-model="form.role" :options="roleOptions" optionLabel="label" optionValue="value" fluid />
        </div>

        <p v-if="!editMode" class="form-hint">
          <i class="pi pi-envelope" aria-hidden="true" />
          Un e-mail d'invitation sera envoye : l'utilisateur definira lui-meme son mot de passe.
        </p>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" :label="editMode ? 'Modifier' : 'Creer et inviter'" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <Dialog
      v-model:visible="invitationDialogVisible"
      header="Invitation envoyee"
      :modal="true"
      :style="{ width: '32rem' }"
      :closable="true"
    >
      <div class="invitation-box" role="status" aria-live="polite">
        <p>
          Un e-mail d'invitation a ete envoye a <strong>{{ invitationEmail }}</strong>.
          L'utilisateur y definira son mot de passe.
        </p>
        <p class="invitation-note">
          Si l'e-mail n'arrive pas, transmettez ce lien securise (valable 24 h, a usage unique) :
        </p>
        <code class="invitation-link">{{ invitationLink }}</code>
      </div>
      <div class="dialog-actions">
        <Button label="Copier le lien" icon="pi pi-copy" @click="copyInvitationLink" />
        <Button label="Fermer" severity="secondary" text @click="invitationDialogVisible = false" />
      </div>
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

/* ── En-tete : compteur sous le titre (meme patron que les entreprises/missions) ── */
.page-compteurs {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

/* ── Barre de recherche façon maquette : large, arrondie, pleine largeur ── */
.search-wrapper {
  position: relative;
  display: flex;
  align-items: center;
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
  padding-right: 2.6rem;
  border-radius: 10px;
}
.search-submit {
  position: absolute;
  right: 0.4rem;
  top: 50%;
  transform: translateY(-50%);
}

.dialog-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
.form-hint {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--p-text-muted-color, #64748b);
}

/* ── Table ──────────────────────────────────────────────────────────────── */
.actions-cell {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.15rem;
  align-items: center;
}

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

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
  .page-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .toolbar-filters {
    width: 100%;
  }
  .search-wrapper {
    min-width: 0;
    width: 100%;
  }
}
.invitation-box {
  background: rgba(128, 128, 128, 0.08);
  border: 1px solid var(--p-content-border-color, rgba(128, 128, 128, 0.2));
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 0.75rem;
  color: var(--p-text-color);
}
.invitation-box p { margin: 0 0 0.5rem; line-height: 1.5; }
.invitation-note { font-size: 0.8125rem; color: var(--p-text-muted-color, #64748b); }
.invitation-link {
  display: block;
  background: rgba(128, 128, 128, 0.15);
  color: var(--p-text-color);
  padding: 0.5rem 0.625rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  word-break: break-all;
  user-select: all;
}
</style>
