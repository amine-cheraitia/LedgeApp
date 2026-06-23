<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Drawer from 'primevue/drawer'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import { facturesApi, type PaiementPayload } from '@/api/modules/factures'
import { useAuthStore } from '@/stores/auth'
import type { Facture, Paiement } from '@/types'

const props = defineProps<{
  modelValue: boolean
  facture: Facture | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'paiement-changed': []
}>()

const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const paiements = ref<Paiement[]>([])
const loadingPaiements = ref(false)

type Step = 'history' | 'form' | 'confirm'
const step = ref<Step>('history')
const saving = ref(false)

const errors = ref<{ montant?: string; date?: string }>({})

const paiementForm = ref<PaiementPayload>({
  montant: 0,
  date_paiement: '',
  mode_paiement: 'virement',
  reference: null,
  notes: null,
})
const datePaiement = ref<Date | null>(null)

const modeOptions = [
  { label: 'Virement', value: 'virement' },
  { label: 'Chèque', value: 'cheque' },
  { label: 'Autre', value: 'autre' },
]

const montantRestantApres = computed(() => {
  if (!props.facture) return 0
  return Math.max(0, props.facture.montant_restant - (paiementForm.value.montant ?? 0))
})

const statutApres = computed(() => {
  if (!props.facture) return 'en_attente'
  const totalApres = Number(props.facture.montant_paye) + Number(paiementForm.value.montant ?? 0)
  if (totalApres >= Number(props.facture.montant_ttc)) return 'solde'
  if (totalApres > 0) return 'partiel'
  return 'en_attente'
})

watch(
  () => props.modelValue,
  async (visible) => {
    if (visible && props.facture) {
      step.value = 'history'
      await loadPaiements()
    }
  },
)

async function loadPaiements() {
  if (!props.facture) return
  loadingPaiements.value = true
  try {
    const res = await facturesApi.getPaiements(props.facture.id)
    paiements.value = res.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les paiements.', life: 3000 })
  } finally {
    loadingPaiements.value = false
  }
}

function openForm() {
  if (!props.facture) return
  errors.value = {}
  paiementForm.value = {
    montant: Number(props.facture.montant_restant),
    date_paiement: '',
    mode_paiement: 'virement',
    reference: null,
    notes: null,
  }
  datePaiement.value = new Date()
  step.value = 'form'
}

function validate(): boolean {
  errors.value = {}
  const montant = Number(paiementForm.value.montant ?? 0)
  if (!montant || montant <= 0) {
    errors.value.montant = 'Le montant doit être supérieur à 0 DA.'
  } else if (props.facture && montant > Number(props.facture.montant_restant)) {
    errors.value.montant = `Le montant ne peut pas dépasser le restant dû (${formatMontant(props.facture.montant_restant)}).`
  }
  if (!datePaiement.value) {
    errors.value.date = 'La date de paiement est obligatoire.'
  }
  return Object.keys(errors.value).length === 0
}

function goToConfirm() {
  if (!validate()) return
  step.value = 'confirm'
}

async function submitPaiement() {
  if (!props.facture || !datePaiement.value) return
  saving.value = true
  try {
    await facturesApi.createPaiement(props.facture.id, {
      ...paiementForm.value,
      date_paiement: toIsoDate(datePaiement.value),
    })
    toast.add({
      severity: 'success',
      summary: 'Paiement enregistré',
      detail: `${formatMontant(paiementForm.value.montant)} enregistré avec succès.`,
      life: 3000,
    })
    await loadPaiements()
    emit('paiement-changed')
    step.value = 'history'
  } catch (err: any) {
    const detail = err.response?.data?.message ?? "Erreur lors de l'enregistrement."
    toast.add({ severity: 'error', summary: 'Erreur', detail, life: 5000 })
    step.value = 'form'
  } finally {
    saving.value = false
  }
}

function confirmDelete(paiement: Paiement) {
  confirm.require({
    message: `Supprimer ce paiement de ${formatMontant(paiement.montant)} ?`,
    header: 'Supprimer le paiement',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      if (!props.facture) return
      try {
        await facturesApi.deletePaiement(props.facture.id, paiement.id)
        toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Le paiement a été annulé.', life: 3000 })
        await loadPaiements()
        emit('paiement-changed')
      } catch (err: any) {
        const detail = err.response?.data?.message ?? 'Suppression impossible.'
        toast.add({ severity: 'error', summary: 'Erreur', detail, life: 4000 })
      }
    },
  })
}

function canDeletePaiement(paiement: Paiement): boolean {
  if (auth.isAdmin) return true
  if (auth.isSecretaire && auth.user?.id === paiement.recorded_by) return true
  return false
}

function toIsoDate(d: Date): string {
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

function statutColor(statut: string): 'warn' | 'info' | 'success' | 'secondary' {
  const map: Record<string, 'warn' | 'info' | 'success' | 'secondary'> = {
    en_attente: 'warn',
    partiel: 'info',
    solde: 'success',
  }
  return map[statut] ?? 'secondary'
}

function statutLabel(statut: string): string {
  const map: Record<string, string> = {
    en_attente: 'En attente',
    partiel: 'Partiel',
    solde: 'Soldé',
  }
  return map[statut] ?? statut
}

function modeLabel(mode: string): string {
  const map: Record<string, string> = { virement: 'Virement', cheque: 'Chèque', autre: 'Autre', espece: 'Espèces' }
  return map[mode] ?? mode
}
</script>

<template>
  <Drawer
    :visible="modelValue"
    @update:visible="emit('update:modelValue', $event)"
    position="right"
    :style="{ width: '38rem', maxWidth: '95vw' }"
    :blockScroll="true"
  >
    <template #header>
      <div class="drawer-header" v-if="facture">
        <div class="drawer-header-top">
          <span class="drawer-title">{{ facture.numero }}</span>
          <Tag
            :value="statutLabel(facture.statut_paiement)"
            :severity="statutColor(facture.statut_paiement)"
          />
        </div>
        <span class="drawer-sub">{{ facture.entreprise?.raison_sociale }}</span>
      </div>
    </template>

    <div v-if="facture" class="drawer-body">

      <!-- Bande montants -->
      <div class="amounts-band" role="region" aria-label="Résumé financier de la facture">
        <div class="amount-item">
          <span class="amount-label">Total TTC</span>
          <span class="amount-value">{{ formatMontant(facture.montant_ttc) }}</span>
        </div>
        <div class="amount-item">
          <span class="amount-label">Encaissé</span>
          <span class="amount-value amount-paye">{{ formatMontant(facture.montant_paye) }}</span>
        </div>
        <div class="amount-item">
          <span class="amount-label">Reste dû</span>
          <span
            class="amount-value"
            :class="facture.montant_restant > 0 ? 'amount-restant' : 'amount-zero'"
          >
            {{ formatMontant(facture.montant_restant) }}
          </span>
        </div>
      </div>

      <!-- ===== Étape : Historique ===== -->
      <template v-if="step === 'history'">
        <div class="section-header">
          <h2 class="section-title" id="paiements-heading">Paiements enregistrés</h2>
          <Button
            v-if="facture.statut_paiement !== 'solde' && (auth.isAdmin || auth.isSecretaire)"
            label="Ajouter"
            icon="pi pi-plus"
            size="small"
            aria-label="Ajouter un paiement"
            @click="openForm"
          />
        </div>

        <div v-if="loadingPaiements" class="loading-state" role="status" aria-live="polite">
          <i class="pi pi-spin pi-spinner" style="font-size: 1.5rem" aria-hidden="true" />
          <span>Chargement…</span>
        </div>

        <div v-else-if="paiements.length === 0" class="empty-state">
          <i class="pi pi-inbox empty-icon" aria-hidden="true" />
          <p>Aucun paiement enregistré pour cette facture.</p>
        </div>

        <ul v-else class="paiements-list" aria-labelledby="paiements-heading">
          <li
            v-for="p in paiements"
            :key="p.id"
            class="paiement-row"
          >
            <div class="paiement-main">
              <span class="paiement-montant">{{ formatMontant(p.montant) }}</span>
              <span class="paiement-mode">{{ modeLabel(p.mode_paiement) }}</span>
            </div>
            <div class="paiement-meta">
              <span>{{ formatDate(p.date_paiement) }}</span>
              <span v-if="p.reference" class="paiement-ref">{{ p.reference }}</span>
              <span v-if="p.recorded_by_name" class="paiement-by">par {{ p.recorded_by_name }}</span>
            </div>
            <Button
              v-if="canDeletePaiement(p)"
              icon="pi pi-trash"
              text
              rounded
              severity="danger"
              size="small"
              :aria-label="`Supprimer le paiement de ${formatMontant(p.montant)}`"
              v-tooltip.top="'Supprimer'"
              @click="confirmDelete(p)"
            />
          </li>
        </ul>
      </template>

      <!-- ===== Étape : Formulaire ===== -->
      <template v-else-if="step === 'form'">
        <div class="section-header">
          <Button
            icon="pi pi-arrow-left"
            text
            size="small"
            aria-label="Retour à l'historique"
            @click="step = 'history'"
          />
          <h2 class="section-title">Nouveau paiement</h2>
        </div>

        <form @submit.prevent="goToConfirm" class="paiement-form" novalidate>
          <div class="form-field">
            <label for="pd-montant">Montant (DA) *</label>
            <InputNumber
              id="pd-montant"
              v-model="paiementForm.montant"
              :min="0.01"
              mode="decimal"
              :minFractionDigits="2"
              fluid
              aria-required="true"
              :aria-describedby="errors.montant ? 'pd-montant-error' : 'pd-montant-hint'"
              :aria-invalid="!!errors.montant"
            />
            <small
              v-if="errors.montant"
              id="pd-montant-error"
              role="alert"
              aria-live="assertive"
              class="field-error"
            >
              <i class="pi pi-exclamation-circle" aria-hidden="true" />
              {{ errors.montant }}
            </small>
            <small v-else id="pd-montant-hint" class="hint">
              Reste dû : <strong>{{ formatMontant(facture.montant_restant) }}</strong>
            </small>
          </div>

          <div class="form-field">
            <label for="pd-date">Date de paiement *</label>
            <DatePicker
              id="pd-date"
              v-model="datePaiement"
              dateFormat="dd/mm/yy"
              fluid
              aria-required="true"
              :aria-describedby="errors.date ? 'pd-date-error' : undefined"
              :aria-invalid="!!errors.date"
            />
            <small
              v-if="errors.date"
              id="pd-date-error"
              role="alert"
              aria-live="assertive"
              class="field-error"
            >
              <i class="pi pi-exclamation-circle" aria-hidden="true" />
              {{ errors.date }}
            </small>
          </div>

          <div class="form-field">
            <label for="pd-mode">Mode de paiement *</label>
            <Select
              id="pd-mode"
              v-model="paiementForm.mode_paiement"
              :options="modeOptions"
              optionLabel="label"
              optionValue="value"
              fluid
              aria-required="true"
            />
          </div>

          <div class="form-field">
            <label for="pd-ref">Référence</label>
            <InputText
              id="pd-ref"
              v-model="paiementForm.reference"
              fluid
              placeholder="N° chèque, réf. virement…"
            />
          </div>

          <div class="form-actions">
            <Button
              label="Annuler"
              severity="secondary"
              text
              type="button"
              @click="step = 'history'"
            />
            <Button
              type="submit"
              label="Vérifier →"
              icon="pi pi-arrow-right"
              iconPos="right"
            />
          </div>
        </form>
      </template>

      <!-- ===== Étape : Confirmation ===== -->
      <template v-else-if="step === 'confirm'">
        <div class="section-header">
          <Button
            icon="pi pi-arrow-left"
            text
            size="small"
            aria-label="Retour au formulaire"
            @click="step = 'form'"
          />
          <h2 class="section-title">Confirmer l'encaissement</h2>
        </div>

        <div class="confirm-card" role="region" aria-label="Récapitulatif du paiement">
          <div class="confirm-row">
            <span class="confirm-label">Montant encaissé</span>
            <strong class="confirm-montant">{{ formatMontant(paiementForm.montant) }}</strong>
          </div>
          <div class="confirm-row">
            <span class="confirm-label">Mode</span>
            <span>{{ modeLabel(paiementForm.mode_paiement) }}</span>
          </div>
          <div class="confirm-row">
            <span class="confirm-label">Date</span>
            <span>{{ datePaiement ? formatDate(toIsoDate(datePaiement)) : '—' }}</span>
          </div>
          <div v-if="paiementForm.reference" class="confirm-row">
            <span class="confirm-label">Référence</span>
            <span class="confirm-ref">{{ paiementForm.reference }}</span>
          </div>

          <div class="confirm-divider" aria-hidden="true" />

          <div class="confirm-row">
            <span class="confirm-label">Reste dû après</span>
            <strong :class="montantRestantApres === 0 ? 'text-success' : 'text-warn'">
              {{ formatMontant(montantRestantApres) }}
            </strong>
          </div>
          <div class="confirm-row">
            <span class="confirm-label">Nouveau statut</span>
            <Tag
              :value="statutLabel(statutApres)"
              :severity="statutColor(statutApres)"
            />
          </div>
        </div>

        <div class="form-actions">
          <Button
            label="Modifier"
            severity="secondary"
            text
            type="button"
            @click="step = 'form'"
          />
          <Button
            label="Confirmer"
            icon="pi pi-check"
            :loading="saving"
            @click="submitPaiement"
            aria-label="Confirmer l'enregistrement du paiement"
          />
        </div>
      </template>
    </div>
  </Drawer>
</template>

<style scoped>
.drawer-header {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.drawer-header-top {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.drawer-title {
  font-size: 1.05rem;
  font-weight: 700;
}
.drawer-sub {
  font-size: 0.82rem;
  color: var(--p-text-muted-color);
}

.drawer-body {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Bande montants */
.amounts-band {
  display: flex;
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  overflow: hidden;
}
.amount-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.7rem 0.5rem;
  background: var(--p-surface-ground);
  border-right: 1px solid var(--p-surface-border);
  gap: 0.15rem;
  text-align: center;
}
.amount-item:last-child {
  border-right: none;
}
.amount-label {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
}
.amount-value {
  font-size: 0.9rem;
  font-weight: 600;
}
.amount-paye { color: var(--p-green-500); }
.amount-restant { color: var(--p-orange-500); }
.amount-zero { color: var(--p-green-500); }
@media (prefers-color-scheme: dark) {
  .amount-paye, .amount-zero { color: var(--p-green-300); }
  .amount-restant { color: var(--p-orange-300); }
}
:global(.p-dark) .amount-paye,
:global(.p-dark) .amount-zero { color: var(--p-green-300); }
:global(.p-dark) .amount-restant { color: var(--p-orange-300); }

/* Section header */
.section-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.section-title {
  font-size: 0.9rem;
  font-weight: 600;
  margin: 0;
  flex: 1;
}

/* États vides / chargement */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2rem 1rem;
  color: var(--p-text-muted-color);
  font-size: 0.88rem;
}
.empty-icon {
  font-size: 2rem;
  opacity: 0.35;
}

/* Liste des paiements */
.paiements-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  overflow: hidden;
}
.paiement-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.85rem;
  border-bottom: 1px solid var(--p-surface-border);
  background: var(--p-surface-card);
  transition: background 0.15s;
}
.paiement-row:last-child {
  border-bottom: none;
}
.paiement-row:hover {
  background: var(--p-surface-ground);
}
:global(.p-dark) .paiement-row {
  background: var(--p-surface-ground);
}
:global(.p-dark) .paiement-row:hover {
  background: var(--p-surface-hover);
}
@media (prefers-color-scheme: dark) {
  .paiement-row { background: var(--p-surface-ground); }
  .paiement-row:hover { background: var(--p-surface-hover); }
}
.paiement-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
}
.paiement-montant {
  font-weight: 600;
  font-size: 0.92rem;
}
.paiement-mode {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.paiement-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.05rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.paiement-ref {
  font-family: monospace;
  font-size: 0.72rem;
}
.paiement-by {
  font-style: italic;
  font-size: 0.7rem;
}

/* Formulaire */
.paiement-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.form-field label {
  font-size: 0.875rem;
  font-weight: 500;
}
.hint {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding-top: 0.25rem;
}

/* Carte de confirmation */
.confirm-card {
  background: var(--p-surface-ground);
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  padding: 1rem 1.1rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.confirm-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}
.confirm-label {
  font-size: 0.82rem;
  color: var(--p-text-muted-color);
}
.confirm-montant {
  font-size: 1.1rem;
  color: var(--p-primary-color);
}
.confirm-ref {
  font-family: monospace;
  font-size: 0.85rem;
}
.confirm-divider {
  border-top: 1px dashed var(--p-surface-border);
  margin: 0.1rem 0;
}
.text-success { color: var(--p-green-500); }
.text-warn { color: var(--p-orange-500); }
@media (prefers-color-scheme: dark) {
  .text-success { color: var(--p-green-300); }
  .text-warn { color: var(--p-orange-300); }
}
:global(.p-dark) .text-success { color: var(--p-green-300); }
:global(.p-dark) .text-warn { color: var(--p-orange-300); }

.field-error {
  color: var(--p-red-500);
  font-size: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
@media (prefers-color-scheme: dark) {
  .field-error { color: var(--p-red-300); }
}
:global(.p-dark) .field-error { color: var(--p-red-300); }

@media (max-width: 480px) {
  .amounts-band { flex-direction: column; }
  .amount-item {
    border-right: none;
    border-bottom: 1px solid var(--p-surface-border);
  }
  .amount-item:last-child { border-bottom: none; }
}
</style>
