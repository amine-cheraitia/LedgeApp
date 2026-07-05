<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
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
import ToggleSwitch from 'primevue/toggleswitch'
import { useEntreprises } from '@/composables/useEntreprises'
import { useContacts } from '@/composables/useContacts'
import { useAuthStore } from '@/stores/auth'
import { entreprisesApi } from '@/api/modules/entreprises'
import type { Contact, Entreprise } from '@/types'
import type { ContactPayload } from '@/api/modules/contacts'

const confirm = useConfirm()
const toast = useToast()
const router = useRouter()
const auth = useAuthStore()
const {
  entreprises, loading, totalRecords, filters,
  fetchEntreprises, createEntreprise, updateEntreprise, deleteEntreprise,
  onPage, onSearch, setStatut, setWilaya, resetFilters,
} = useEntreprises()

const search = ref('')
const filtreStatut = ref<string | null>(null)
const filtreWilaya = ref<string | null>(null)
const wilayas = ref<string[]>([])
const exportLoading = ref(false)

watch(search, (val) => {
  onSearch(val)
})

watch(filtreStatut, (val) => {
  setStatut(val)
})

watch(filtreWilaya, (val) => {
  setWilaya(val)
})

async function loadWilayas() {
  try {
    const response = await entreprisesApi.wilayas()
    wilayas.value = response.data
  } catch {
    // silencieux
  }
}

function handleReset() {
  search.value = ''
  filtreStatut.value = null
  filtreWilaya.value = null
  resetFilters()
}

async function handleExportCsv() {
  exportLoading.value = true
  try {
    const blob = await entreprisesApi.exportCsv({
      search: filters.value.search,
      statut: filters.value.statut,
      wilaya: filters.value.wilaya,
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'entreprises.csv'
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'exporter.", life: 3000 })
  } finally {
    exportLoading.value = false
  }
}
const dialogVisible = ref(false)
const editMode = ref(false)
const saving = ref(false)

// Portal activation
const portailDialogVisible = ref(false)
const portailSaving = ref(false)
const portailEntreprise = ref<Entreprise | null>(null)
const portailForm = ref({ name: '', email: '' })
const invitationDialogVisible = ref(false)
const invitation = ref({ email: '', link: '' })

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
    showInvitation(portailForm.value.email, result.invitation_url)
    await fetchEntreprises()
  } catch (error: any) {
    const message = error.response?.data?.message || 'Erreur lors de l\'activation.'
    toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
  } finally {
    portailSaving.value = false
  }
}

function onTogglePortail(entreprise: Entreprise, estActif: boolean) {
  // Verrouiller (couper l'acces) est une action sensible : on confirme.
  // Reactiver restaure simplement l'acces : pas de confirmation.
  if (!estActif) {
    doTogglePortail(entreprise)
    return
  }

  confirm.require({
    message: `Verrouiller l'acces portail de « ${entreprise.raison_sociale} » ? Le client ne pourra plus se connecter tant que l'acces n'aura pas ete reactive.`,
    header: 'Verrouiller l\'acces',
    icon: 'pi pi-lock',
    acceptLabel: 'Verrouiller',
    rejectLabel: 'Annuler',
    accept: () => doTogglePortail(entreprise),
  })
}

async function doTogglePortail(entreprise: Entreprise) {
  try {
    const result = await entreprisesApi.togglePortail(entreprise.id)
    toast.add({ severity: 'success', summary: 'Succes', detail: result.message, life: 3000 })
    await fetchEntreprises()
  } catch (error: any) {
    const message = error.response?.data?.message || 'Erreur lors du changement.'
    toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
  }
}

function onResendInvitation(entreprise: Entreprise) {
  confirm.require({
    message: `Renvoyer une invitation a « ${entreprise.raison_sociale} » ? Un nouveau lien sera envoye a ${entreprise.email || 'l\'adresse du client'} et l'ancien lien deviendra invalide.`,
    header: 'Renvoyer l\'invitation',
    icon: 'pi pi-envelope',
    acceptLabel: 'Renvoyer',
    rejectLabel: 'Annuler',
    accept: () => doResendInvitation(entreprise),
  })
}

async function doResendInvitation(entreprise: Entreprise) {
  try {
    const result = await entreprisesApi.renvoyerInvitation(entreprise.id)
    toast.add({ severity: 'success', summary: 'Succes', detail: result.message, life: 3000 })
    showInvitation(entreprise.email ?? '', result.invitation_url)
  } catch (error: any) {
    const message = error.response?.data?.message || 'Erreur lors de l\'envoi de l\'invitation.'
    toast.add({ severity: 'error', summary: 'Erreur', detail: message, life: 5000 })
  }
}

function showInvitation(email: string, link: string) {
  invitation.value = { email, link }
  invitationDialogVisible.value = true
}

function copyInvitationLink() {
  navigator.clipboard.writeText(invitation.value.link)
  toast.add({ severity: 'info', summary: 'Copie', detail: 'Lien d\'invitation copie dans le presse-papier.', life: 2000 })
}

// --- Contacts ---

const { contacts, loading: contactsLoading, fetchContacts, createContact, updateContact, deleteContact } = useContacts()

const contactsDialogVisible = ref(false)
const contactsEntreprise = ref<Entreprise | null>(null)

const contactFormVisible = ref(false)
const contactEditMode = ref(false)
const contactSaving = ref(false)
const contactEditId = ref<number | null>(null)
const emptyContactForm = (): ContactPayload => ({ nom: '', prenom: null, email: null, telephone: null, poste: null, est_principal: false })
const contactForm = ref<ContactPayload>(emptyContactForm())

function openContacts(entreprise: Entreprise) {
  contactsEntreprise.value = entreprise
  contactsDialogVisible.value = true
  fetchContacts(entreprise.id)
}

function openContactCreate() {
  contactForm.value = emptyContactForm()
  contactEditMode.value = false
  contactEditId.value = null
  contactFormVisible.value = true
}

function openContactEdit(contact: Contact) {
  contactForm.value = {
    nom: contact.nom,
    prenom: contact.prenom,
    email: contact.email,
    telephone: contact.telephone,
    poste: contact.poste,
    est_principal: contact.est_principal,
  }
  contactEditId.value = contact.id
  contactEditMode.value = true
  contactFormVisible.value = true
}

async function onSubmitContact() {
  if (!contactsEntreprise.value) return
  contactSaving.value = true
  try {
    if (contactEditMode.value && contactEditId.value) {
      await updateContact(contactsEntreprise.value.id, contactEditId.value, contactForm.value)
    } else {
      await createContact(contactsEntreprise.value.id, contactForm.value)
    }
    contactFormVisible.value = false
  } catch {
    // erreur geree par le composable
  } finally {
    contactSaving.value = false
  }
}

function confirmDeleteContact(contact: Contact) {
  if (!contactsEntreprise.value) return
  confirm.require({
    message: `Supprimer le contact "${contact.nom}${contact.prenom ? ' ' + contact.prenom : ''}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    accept: () => deleteContact(contactsEntreprise.value!.id, contact.id),
  })
}

onMounted(() => {
  fetchEntreprises()
  loadWilayas()
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Entreprises</h1>
      <Button label="Nouvelle entreprise" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="page-toolbar">
      <div class="toolbar-filters">
        <div class="search-wrapper">
          <label for="search-entreprises" class="sr-only">Rechercher une entreprise</label>
          <span class="p-input-icon-left">
            <i class="pi pi-search" />
            <InputText
              id="search-entreprises"
              v-model="search"
              placeholder="Raison sociale, NIF, email..."
              style="padding-left: 2.25rem; min-width: 18rem;"
            />
          </span>
        </div>

        <Select
          v-model="filtreStatut"
          :options="[{ label: 'Tous les statuts', value: null }, ...statutOptions]"
          optionLabel="label"
          optionValue="value"
          placeholder="Statut"
          aria-label="Filtrer par statut"
          style="min-width: 12rem;"
        />

        <Select
          v-model="filtreWilaya"
          :options="[{ label: 'Toutes les wilayas', value: null }, ...wilayas.map(w => ({ label: w, value: w }))]"
          optionLabel="label"
          optionValue="value"
          placeholder="Wilaya"
          aria-label="Filtrer par wilaya"
          style="min-width: 12rem;"
        />

        <Button
          v-if="filtreStatut || filtreWilaya || search"
          icon="pi pi-filter-slash"
          label="Reinitialiser"
          severity="secondary"
          text
          aria-label="Reinitialiser les filtres"
          @click="handleReset"
        />
      </div>

      <Button
        icon="pi pi-download"
        label="Export CSV"
        severity="secondary"
        outlined
        :loading="exportLoading"
        aria-label="Exporter en CSV"
        @click="handleExportCsv"
      />
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
      <Column v-if="auth.isAdmin" header="Portail">
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
                :aria-label="data.portail_user.portail_actif ? `Verrouiller l'acces de ${data.raison_sociale}` : `Reactiver l'acces de ${data.raison_sociale}`"
                v-tooltip.top="data.portail_user.portail_actif ? 'Verrouiller l\'acces' : 'Reactiver l\'acces'"
                @click="onTogglePortail(data, data.portail_user.portail_actif)"
              />
              <Button
                icon="pi pi-envelope"
                text
                size="small"
                severity="secondary"
                :aria-label="`Renvoyer l'invitation a ${data.raison_sociale}`"
                v-tooltip.top="'Renvoyer l\'invitation'"
                @click="onResendInvitation(data)"
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
      <Column header="Actions" style="width: 13rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-eye"
            text
            rounded
            severity="secondary"
            aria-label="Voir le dossier complet"
            v-tooltip.top="'Dossier 360°'"
            @click="router.push(`/entreprises/${data.id}`)"
          />
          <Button
            icon="pi pi-users"
            text
            rounded
            severity="secondary"
            aria-label="Gerer les contacts"
            v-tooltip.top="'Contacts'"
            @click="openContacts(data)"
          />
          <Button
            icon="pi pi-pencil"
            text
            rounded
            severity="info"
            aria-label="Modifier l'entreprise"
            v-tooltip.top="'Modifier'"
            @click="openEdit(data)"
          />
          <Button
            v-if="auth.isAdmin"
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            aria-label="Supprimer l'entreprise"
            v-tooltip.top="'Supprimer'"
            @click="confirmDelete(data)"
          />
        </template>
      </Column>
    </DataTable>

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
          <label for="ent-statut">Statut{{ editMode ? '' : ' *' }}</label>
          <Select v-if="!editMode" id="ent-statut" v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value" fluid />
          <template v-else>
            <Tag :value="form.statut ?? 'prospect'" :severity="statutColor(form.statut ?? 'prospect')" />
            <small class="statut-hint">Le passage en « client » est automatique a la creation de la premiere mission.</small>
          </template>
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

    <!-- Dialog activation portail (admin uniquement) -->
    <Dialog
      v-if="auth.isAdmin"
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

    <!-- Dialog contacts -->
    <Dialog
      v-model:visible="contactsDialogVisible"
      :header="`Contacts — ${contactsEntreprise?.raison_sociale ?? ''}`"
      :modal="true"
      :style="{ width: '38rem' }"
    >
      <div class="contacts-header">
        <span class="contacts-count">{{ contacts.length }} contact{{ contacts.length !== 1 ? 's' : '' }}</span>
        <Button
          label="Ajouter un contact"
          icon="pi pi-plus"
          size="small"
          @click="openContactCreate"
        />
      </div>

      <DataTable
        :value="contacts"
        :loading="contactsLoading"
        dataKey="id"
        size="small"
        stripedRows
      >
        <Column field="nom" header="Nom">
          <template #body="{ data }">
            <span :class="{ 'contact-principal': data.est_principal }">
              {{ data.nom }}{{ data.prenom ? ' ' + data.prenom : '' }}
            </span>
            <Tag v-if="data.est_principal" value="Principal" severity="info" class="ml-2" />
          </template>
        </Column>
        <Column field="poste" header="Poste" />
        <Column field="email" header="Email" />
        <Column field="telephone" header="Tel" />
        <Column header="" style="width: 6rem">
          <template #body="{ data }">
            <Button
              icon="pi pi-pencil"
              text
              rounded
              size="small"
              severity="secondary"
              aria-label="Modifier le contact"
              @click="openContactEdit(data)"
            />
            <Button
              icon="pi pi-trash"
              text
              rounded
              size="small"
              severity="danger"
              aria-label="Supprimer le contact"
              @click="confirmDeleteContact(data)"
            />
          </template>
        </Column>
      </DataTable>

      <div v-if="contacts.length === 0 && !contactsLoading" class="contacts-empty">
        Aucun contact. Cliquez sur "Ajouter un contact" pour commencer.
      </div>
    </Dialog>

    <!-- Dialog formulaire contact -->
    <Dialog
      v-model:visible="contactFormVisible"
      :header="contactEditMode ? 'Modifier le contact' : 'Nouveau contact'"
      :modal="true"
      :style="{ width: '30rem' }"
    >
      <form @submit.prevent="onSubmitContact" class="dialog-form">
        <div class="form-row">
          <div class="form-field">
            <label for="c-nom">Nom *</label>
            <InputText id="c-nom" v-model="contactForm.nom" required fluid />
          </div>
          <div class="form-field">
            <label for="c-prenom">Prenom</label>
            <InputText id="c-prenom" v-model="contactForm.prenom" fluid />
          </div>
        </div>

        <div class="form-field">
          <label for="c-poste">Poste</label>
          <InputText id="c-poste" v-model="contactForm.poste" fluid />
        </div>

        <div class="form-row">
          <div class="form-field">
            <label for="c-email">Email</label>
            <InputText id="c-email" v-model="contactForm.email" type="email" fluid />
          </div>
          <div class="form-field">
            <label for="c-tel">Telephone</label>
            <InputText id="c-tel" v-model="contactForm.telephone" fluid />
          </div>
        </div>

        <div class="form-field form-field--inline">
          <label for="c-principal">Contact principal</label>
          <ToggleSwitch id="c-principal" v-model="contactForm.est_principal" aria-label="Definir comme contact principal" />
        </div>

        <div class="dialog-actions">
          <Button label="Annuler" severity="secondary" text @click="contactFormVisible = false" type="button" />
          <Button
            :label="contactEditMode ? 'Enregistrer' : 'Ajouter'"
            type="submit"
            :loading="contactSaving"
            :disabled="!contactForm.nom"
          />
        </div>
      </form>
    </Dialog>

    <!-- Dialog invitation portail (admin uniquement) -->
    <Dialog
      v-if="auth.isAdmin"
      v-model:visible="invitationDialogVisible"
      header="Invitation envoyee"
      :modal="true"
      :style="{ width: '32rem' }"
      :closable="true"
    >
      <div class="credentials-box" role="status" aria-live="polite">
        <p>
          Un e-mail d'invitation a ete envoye a <strong>{{ invitation.email }}</strong>.
          Le client y definira lui-meme son mot de passe.
        </p>
        <p class="invitation-note">
          Si l'e-mail n'arrive pas, transmettez ce lien securise (valable 24 h, a usage unique) :
        </p>
        <code class="invitation-link">{{ invitation.link }}</code>
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
  margin-bottom: 1rem;
}
.page-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.toolbar-filters {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.search-wrapper { position: relative; }
.dialog-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
.form-field label { font-size: 0.875rem; font-weight: 500; }
.form-row { display: flex; gap: 0.75rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
.text-muted { color: var(--p-text-muted-color, #999); }
.portail-tag { margin-right: 0.25rem; }
.portail-intro { margin-bottom: 0.75rem; font-size: 0.875rem; }
.credentials-box {
  background: rgba(128,128,128,0.1);
  border: 1px solid var(--p-content-border-color, rgba(128,128,128,0.2));
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 0.75rem;
  color: var(--p-text-color);
}
.credentials-box p { margin: 0 0 0.5rem; line-height: 1.5; }
.invitation-note { font-size: 0.8125rem; color: var(--p-text-muted-color, #64748b); }
.statut-hint { display: block; margin-top: 0.375rem; font-size: 0.8125rem; color: var(--p-text-muted-color, #64748b); }
.invitation-link {
  display: block;
  background: rgba(128,128,128,0.15);
  color: var(--p-text-color);
  padding: 0.5rem 0.625rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  word-break: break-all;
  user-select: all;
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
  background: rgba(128,128,128,0.15);
  color: var(--p-text-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.9rem;
  user-select: all;
}

.form-field--inline {
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
}
.contacts-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}
.contacts-count { font-size: 0.875rem; color: var(--p-text-muted-color, #666); }
.contacts-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--p-text-muted-color, #999);
  font-size: 0.875rem;
}
.contact-principal { font-weight: 600; }
.ml-2 { margin-left: 0.5rem; }

@media (max-width: 640px) {
  .form-row { flex-direction: column; }
}
</style>
