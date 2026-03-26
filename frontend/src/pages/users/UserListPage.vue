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
import Password from 'primevue/password'
import ConfirmDialog from 'primevue/confirmdialog'
import { useUsers } from '@/composables/useUsers'
import type { User } from '@/types'
import type { UserPayload } from '@/api/modules/users'

const confirm = useConfirm()
const {
  users, loading, totalRecords, filters,
  fetchUsers, createUser, updateUser, deleteUser,
  onPage, onSearch,
} = useUsers()

const search = ref('')
const dialogVisible = ref(false)
const editMode = ref(false)
const saving = ref(false)

const emptyForm = (): UserPayload => ({
  name: '',
  email: '',
  password: '',
  role: 'collaborateur',
  entreprise_id: null,
  portail_actif: false,
})

const form = ref<UserPayload>(emptyForm())
const editId = ref<number | null>(null)

const roleOptions = [
  { label: 'Admin', value: 'admin' },
  { label: 'Collaborateur', value: 'collaborateur' },
  { label: 'Secretaire', value: 'secretaire' },
  { label: 'Client', value: 'client' },
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
    portail_actif: user.portail_actif,
  }
  editId.value = user.id
  editMode.value = true
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    if (editMode.value && editId.value) {
      const payload: Partial<UserPayload> = { ...form.value }
      if (!payload.password) delete payload.password
      await updateUser(editId.value, payload)
    } else {
      await createUser(form.value)
    }
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
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
      <h2>Utilisateurs</h2>
      <Button label="Nouvel utilisateur" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="handleSearch" role="search" class="search-form">
        <label for="search-users" class="sr-only">Rechercher un utilisateur</label>
        <InputText
          id="search-users"
          v-model="search"
          placeholder="Rechercher..."
        />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="handleSearch" />
      </form>
    </div>

    <DataTable
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
      <Column header="Actions" style="width: 8rem">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text severity="info" aria-label="Modifier" @click="openEdit(data)" />
          <Button icon="pi pi-trash" text severity="danger" aria-label="Supprimer" @click="confirmDelete(data)" />
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />

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
          <label for="user-password">{{ editMode ? 'Mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe *' }}</label>
          <Password id="user-password" v-model="form.password" :required="!editMode" toggleMask :feedback="false" fluid />
        </div>

        <div class="form-field">
          <label for="user-role">Role *</label>
          <Select id="user-role" v-model="form.role" :options="roleOptions" optionLabel="label" optionValue="value" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" :label="editMode ? 'Modifier' : 'Creer'" icon="pi pi-check" :loading="saving" />
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
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
</style>
