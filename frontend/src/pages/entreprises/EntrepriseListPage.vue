<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ConfirmDialog from 'primevue/confirmdialog'
import { useEntreprises } from '@/composables/useEntreprises'
import { entreprisesApi } from '@/api/modules/entreprises'
import type { Entreprise } from '@/types'

const confirm = useConfirm()
const toast = useToast()
const {
  entreprises, loading, totalRecords, filters,
  fetchEntreprises, createEntreprise, updateEntreprise, deleteEntreprise,
  onPage, onSearch,
} = useEntreprises()

const search = ref('')
const dialogVisible = ref(false)
const editMode = ref(false)
const saving = ref(false)

// Portal activation
const portailDialogVisible = ref(false)
const portailSaving = ref(false)
const portailEntreprise = ref<Entreprise | null>(null)
const portailForm = ref({ name: '', email: '' })
const credentialsDialogVisible = ref(false)
const credentials = ref({ email: '', password: '' })

const emptyForm = (): Partial<Entreprise> => ({
  raison_sociale: '',
  nif: null,
  nis: null,
  num_rc: null,
  article_imposition: null,
  regime_fiscal: 'forfait',
  categorie: 'TPE',
  secteur_activite: null,
  adresse: null,
  ville: null,
  wilaya: null,
  telephone: null,
  email: null,
  contact_principal: null,
  statut: 'prospect',
  notes: null,
})

const form = ref<Partial<Entreprise>>(emptyForm())
const editId = ref<number | null>(null)

const regimeOptions = [
  { label: 'Forfait', value: 'forfait' },
  { label: 'Reel', value: 'reel' },
]

const categorieOptions = [
  { label: 'TPE', value: 'TPE' },
  { label: 'PME', value: 'PME' },
  { label: 'GE', value: 'GE' },
]

const statutOptions = [
  { label: 'Prospect', value: 'prospect' },
  { label: 'Client', value: 'client' },
  { label: 'Ancien client', value: 'ancien_client' },
]

function statutColor(statut: string) {
  const colors: Record<string, string> = {
    prospect: 'warn',
    client: 'success',
    ancien_client: 'secondary',
  }
  return (colors[statut] ?? 'secondary') as 'warn' | 'success' | 'secondary'
}

function openCreate() {
  form.value = emptyForm()
  editId.value = null
  editMode.value = false
  dialogVisible.value = true
}

function openEdit(entreprise: Entreprise) {
  form.value = { ...entreprise }
  editId.value = entreprise.id
  editMode.value = true
  dialogVisible.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    if (editMode.value && editId.value) {
      await updateEntreprise(editId.value, form.value)
    } else {
      await createEntreprise(form.value)
    }
    dialogVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    saving.value = false
  }
}

function confirmDelete(entreprise: Entreprise) {
  confirm.require({
    message: `Supprimer "${entreprise.raison_sociale}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteEntreprise(entreprise.id),
  })
}

function handleSearch() {
  onSearch(search.value)
}

// --- Portail ---

function openPortailActivation(entreprise: Entreprise) {
  portailEntreprise.value = entreprise
  portailForm.value = {
    name: entreprise.contact_principal ?? '',
    email: entreprise.email ?? '',
  }
  portailDialogVisible.value = true
}

async function onActiverPortail() {
  if (!portailEntreprise.value) return
  portailSaving.value = true
  try {
    const result = await entreprisesApi.activerPortail(portailEntreprise.value.id, portailForm.value)
    toast.add({ severity: 'success', summary: 'Succes', detail: result.message, life: 3000 })
    portailDialogVisible.value = false
    credentials.value = {
      email: portailForm.value.email,
      password: result.temporary_password,
    }
    credentialsDialogVisible.value = true
    await fetchEntreprises()
  } catch (error: any) {
    const message = error.response?.data?.message || 'Erreur lors de l\'activation.'
    toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
  } finally {
    portailSaving.value = false
  }
}

async function onTogglePortail(entreprise: Entreprise) {
  try {
    const result = await entreprisesApi.togglePortail(entreprise.id)
    toast.add({ severity: 'success', summary: 'Succes', detail: result.message, life: 3000 })
    await fetchEntreprises()
  } catch (error: any) {
    const message = error.response?.data?.message || 'Erreur lors du changement.'
    toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
  }
}

function copyCredentials() {
  const text = `Email: ${credentials.value.email}\nMot de passe: ${credentials.value.password}`
  navigator.clipboard.writeText(text)
  toast.add({ severity: 'info', summary: 'Copie', detail: 'Identifiants copies dans le presse-papier.', life: 2000 })
}

onMounted(fetchEntreprises)
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Entreprises</h2>
      <Button label="Nouvelle entreprise" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <form @submit.prevent="handleSearch" role="search" class="search-form">
        <label for="search-entreprises" class="sr-only">Rechercher une entreprise</label>
        <InputText
          id="search-entreprises"
          v-model="search"
          placeholder="Rechercher..."
        />
        <Button icon="pi pi-search" aria-label="Lancer la recherche" @click="handleSearch" />
      </form>
    </div>

    <DataTable
      :value="entreprises"
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
      <Column field="raison_sociale" header="Raison sociale" />
      <Column field="nif" header="NIF" />
      <Column field="regime_fiscal" header="Regime fiscal" />
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="data.statut" :severity="statutColor(data.statut)" />
        </template>
      </Column>
      <Column header="Portail">
        <template #body="{ data }">
          <template v-if="data.statut === 'client'">
            <template v-if="data.portail_user">
              <Tag
                :value="data.portail_user.portail_actif ? 'Actif' : 'Desactive'"
                :severity="data.portail_user.portail_actif ? 'success' : 'danger'"
                class="portail-tag"
              />
              <Button
                :icon="data.portail_user.portail_actif ? 'pi pi-lock' : 'pi pi-lock-open'"
                text
                size="small"
                :severity="data.portail_user.portail_actif ? 'danger' : 'success'"
                :aria-label="data.portail_user.portail_actif ? 'Desactiver portail' : 'Reactiver portail'"
                @click="onTogglePortail(data)"
              />
            </template>
            <Button
              v-else
              label="Activer"
              icon="pi pi-user-plus"
              size="small"
              severity="success"
              text
              @click="openPortailActivation(data)"
            />
          </template>
          <span v-else class="text-muted">—</span>
        </template>
      </Column>
      <Column field="wilaya" header="Wilaya" />
      <Column header="Actions" style="width: 8rem">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text severity="info" aria-label="Modifier" @click="openEdit(data)" />
          <Button icon="pi pi-trash" text severity="danger" aria-label="Supprimer" @click="confirmDelete(data)" />
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />

    <!-- Dialog creation/edition entreprise -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="editMode ? 'Modifier l\'entreprise' : 'Nouvelle entreprise'"
      :modal="true"
      :style="{ width: '36rem' }"
    >
      <form @submit.prevent="onSubmit" class="dialog-form">
        <div class="form-field">
          <label for="ent-raison">Raison sociale *</label>
          <InputText id="ent-raison" v-model="form.raison_sociale" required fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-nif">NIF</label>
            <InputText id="ent-nif" v-model="form.nif" fluid />
          </div>
          <div class="form-field">
            <label for="ent-nis">NIS</label>
            <InputText id="ent-nis" v-model="form.nis" fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-rc">Num RC</label>
            <InputText id="ent-rc" v-model="form.num_rc" fluid />
          </div>
          <div class="form-field">
            <label for="ent-art">Article imposition</label>
            <InputText id="ent-art" v-model="form.article_imposition" fluid />
          </div>
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-regime">Regime fiscal *</label>
            <Select id="ent-regime" v-model="form.regime_fiscal" :options="regimeOptions" optionLabel="label" optionValue="value" fluid />
          </div>
          <div class="form-field">
            <label for="ent-categorie">Categorie *</label>
            <Select id="ent-categorie" v-model="form.categorie" :options="categorieOptions" optionLabel="label" optionValue="value" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="ent-statut">Statut *</label>
          <Select id="ent-statut" v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value" fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-email">Email</label>
            <InputText id="ent-email" v-model="form.email" type="email" fluid />
          </div>
          <div class="form-field">
            <label for="ent-tel">Telephone</label>
            <InputText id="ent-tel" v-model="form.telephone" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="ent-adresse">Adresse</label>
          <InputText id="ent-adresse" v-model="form.adresse" fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="ent-ville">Ville</label>
            <InputText id="ent-ville" v-model="form.ville" fluid />
          </div>
          <div class="form-field">
            <label for="ent-wilaya">Wilaya</label>
            <InputText id="ent-wilaya" v-model="form.wilaya" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="ent-contact">Contact principal</label>
          <InputText id="ent-contact" v-model="form.contact_principal" fluid />
        </div>

        <div class="form-field">
          <label for="ent-notes">Notes</label>
          <Textarea id="ent-notes" v-model="form.notes" rows="3" fluid />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="dialogVisible = false" />
          <Button type="submit" :label="editMode ? 'Modifier' : 'Creer'" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog activation portail -->
    <Dialog
      v-model:visible="portailDialogVisible"
      header="Activer l'acces portail"
      :modal="true"
      :style="{ width: '28rem' }"
    >
      <p class="portail-intro">
        Creer un compte client pour <strong>{{ portailEntreprise?.raison_sociale }}</strong>.
        Un mot de passe temporaire sera genere.
      </p>
      <form @submit.prevent="onActiverPortail" class="dialog-form">
        <div class="form-field">
          <label for="portail-name">Nom du contact *</label>
          <InputText id="portail-name" v-model="portailForm.name" required fluid />
        </div>
        <div class="form-field">
          <label for="portail-email">Email *</label>
          <InputText id="portail-email" v-model="portailForm.email" type="email" required fluid />
        </div>
        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="portailDialogVisible = false" />
          <Button type="submit" label="Activer le portail" icon="pi pi-user-plus" :loading="portailSaving" />
        </div>
      </form>
    </Dialog>

    <!-- Dialog credentials -->
    <Dialog
      v-model:visible="credentialsDialogVisible"
      header="Identifiants portail"
      :modal="true"
      :style="{ width: '28rem' }"
      :closable="true"
    >
      <div class="credentials-box" role="alert" aria-live="polite">
        <p><strong>Transmettez ces identifiants au client.</strong></p>
        <p>Ils ne seront plus accessibles apres fermeture.</p>
        <div class="credentials-fields">
          <div class="credential-row">
            <span class="credential-label">Email :</span>
            <code>{{ credentials.email }}</code>
          </div>
          <div class="credential-row">
            <span class="credential-label">Mot de passe :</span>
            <code>{{ credentials.password }}</code>
          </div>
        </div>
      </div>
      <div class="dialog-actions">
        <Button label="Copier les identifiants" icon="pi pi-copy" @click="copyCredentials" />
        <Button label="Fermer" severity="secondary" text @click="credentialsDialogVisible = false" />
      </div>
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
.text-muted { color: var(--p-text-muted-color, #999); }
.portail-tag { margin-right: 0.25rem; }
.portail-intro { margin-bottom: 0.75rem; font-size: 0.875rem; }
.credentials-box {
  background: var(--p-surface-100, #f5f5f5);
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 0.75rem;
}
.credentials-fields { margin-top: 0.75rem; }
.credential-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0;
}
.credential-label { font-weight: 500; min-width: 6rem; }
.credential-row code {
  background: var(--p-surface-200, #e5e5e5);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.9rem;
  user-select: all;
}

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
