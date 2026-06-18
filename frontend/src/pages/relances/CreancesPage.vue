<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import { creancesApi } from '@/api/modules/creances'
import { relancesApi } from '@/api/modules/relances'
import type { Facture } from '@/types'

const toast = useToast()

const creances = ref<Facture[]>([])
const loading = ref(false)

const relanceDialog = ref(false)
const relanceSaving = ref(false)
const relanceFacture = ref<Facture | null>(null)
const relanceNiveau = ref<1 | 2 | 3>(1)

const niveauxOptions = [
  { label: 'Niveau 1 — Rappel (J+15)', value: 1 },
  { label: 'Niveau 2 — Relance ferme (J+30)', value: 2 },
  { label: 'Niveau 3 — Mise en demeure (J+45)', value: 3 },
]

const totalRestant = computed(() =>
  creances.value.reduce((sum, f) => sum + Number(f.montant_restant ?? 0), 0)
)

async function fetchCreances() {
  loading.value = true
  try {
    const res = await creancesApi.index()
    creances.value = res.data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les créances', life: 3000 })
  } finally {
    loading.value = false
  }
}

function ouvrirRelance(facture: Facture) {
  relanceFacture.value = facture
  relanceNiveau.value = 1
  relanceDialog.value = true
}

async function envoyerRelance() {
  if (!relanceFacture.value) return
  relanceSaving.value = true
  try {
    const res = await relancesApi.store(relanceFacture.value.id, { niveau: relanceNiveau.value })
    toast.add({
      severity: 'success',
      summary: 'Relance envoyée',
      detail: `Niveau ${relanceNiveau.value} envoyé à ${res.data.data.email_destinataire}`,
      life: 4000,
    })
    relanceDialog.value = false
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erreur lors de l\'envoi'
    toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 })
  } finally {
    relanceSaving.value = false
  }
}

function joursRetard(dateEcheance: string): number {
  return Math.max(0, Math.floor((Date.now() - new Date(dateEcheance).getTime()) / 86400000))
}

function retardSeverity(jours: number): 'warn' | 'danger' {
  return jours > 30 ? 'danger' : 'warn'
}

function statutSeverity(statut: string): 'warn' | 'danger' {
  return statut === 'partiel' ? 'warn' : 'danger'
}

function statutLabel(statut: string): string {
  return statut === 'partiel' ? 'Partiel' : 'Impayée'
}

function formatMontant(val: number | string): string {
  return Number(val).toLocaleString('fr-DZ') + ' DA'
}

const niveauLabel = computed(() =>
  niveauxOptions.find(o => o.value === relanceNiveau.value)?.label ?? ''
)

onMounted(fetchCreances)
</script>

<template>
  <div>
    <!-- En-tête -->
    <div class="page-header">
      <div>
        <h2 class="page-title">Créances impayées</h2>
        <p class="page-subtitle">Factures en attente ou partiellement réglées</p>
      </div>
      <Button
        icon="pi pi-refresh"
        label="Actualiser"
        severity="secondary"
        outlined
        aria-label="Actualiser la liste des créances"
        @click="fetchCreances"
      />
    </div>

    <!-- KPI total restant dû -->
    <div v-if="creances.length > 0" class="kpi-bar">
      <div class="kpi-item">
        <span class="kpi-label">Créances</span>
        <span class="kpi-value">{{ creances.length }}</span>
      </div>
      <div class="kpi-item kpi-danger">
        <span class="kpi-label">Total restant dû</span>
        <span class="kpi-value">{{ formatMontant(totalRestant) }}</span>
      </div>
    </div>

    <!-- Tableau desktop -->
    <DataTable
      :value="creances"
      :loading="loading"
      striped-rows
      responsive-layout="scroll"
      class="creances-table"
      aria-label="Liste des créances impayées"
    >
      <template #empty>
        <div class="empty-state" role="status">
          <i class="pi pi-check-circle empty-icon" aria-hidden="true"></i>
          <p>Aucune créance impayée</p>
        </div>
      </template>

      <Column field="numero" header="Facture" sortable style="min-width: 8rem" />

      <Column header="Entreprise" style="min-width: 10rem">
        <template #body="{ data }">
          <div class="cell-main">{{ data.entreprise?.raison_sociale ?? '—' }}</div>
          <div class="cell-sub">{{ data.entreprise?.email ?? '' }}</div>
        </template>
      </Column>

      <Column field="date_echeance" header="Échéance" sortable style="min-width: 7rem">
        <template #body="{ data }">
          {{ new Date(data.date_echeance).toLocaleDateString('fr-FR') }}
        </template>
      </Column>

      <Column header="Retard" sortable style="min-width: 6rem">
        <template #body="{ data }">
          <Tag
            :value="`J+${joursRetard(data.date_echeance)}`"
            :severity="retardSeverity(joursRetard(data.date_echeance))"
          />
        </template>
      </Column>

      <Column header="Montant TTC" sortable style="min-width: 8rem">
        <template #body="{ data }">
          {{ formatMontant(data.montant_ttc) }}
        </template>
      </Column>

      <Column header="Restant dû" style="min-width: 8rem">
        <template #body="{ data }">
          <span class="montant-restant">{{ formatMontant(data.montant_restant) }}</span>
        </template>
      </Column>

      <Column header="Statut" style="min-width: 6rem">
        <template #body="{ data }">
          <Tag :value="statutLabel(data.statut_paiement)" :severity="statutSeverity(data.statut_paiement)" />
        </template>
      </Column>

      <Column header="Action" style="min-width: 7rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-send"
            label="Relancer"
            size="small"
            severity="warn"
            aria-label="`Envoyer une relance pour la facture ${data.numero}`"
            @click="ouvrirRelance(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog relance -->
    <Dialog
      v-model:visible="relanceDialog"
      header="Envoyer une relance"
      :style="{ width: '480px', maxWidth: '95vw' }"
      :breakpoints="{ '640px': '95vw' }"
      modal
      :draggable="false"
    >
      <div v-if="relanceFacture" class="relance-dialog-body">
        <!-- Récap facture -->
        <div class="facture-recap">
          <div class="recap-row">
            <span class="recap-label">Facture</span>
            <span class="recap-val">{{ relanceFacture.numero }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Entreprise</span>
            <span class="recap-val">{{ relanceFacture.entreprise?.raison_sociale }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Email destinataire</span>
            <span class="recap-val email-val">{{ relanceFacture.entreprise?.email ?? 'Non renseigné' }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Restant dû</span>
            <span class="recap-val montant-restant">{{ formatMontant(relanceFacture.montant_restant) }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Retard</span>
            <span class="recap-val">
              <Tag
                :value="`J+${joursRetard(relanceFacture.date_echeance)}`"
                :severity="retardSeverity(joursRetard(relanceFacture.date_echeance))"
              />
            </span>
          </div>
        </div>

        <!-- Choix niveau -->
        <div class="form-field">
          <label id="label-niveau" class="form-label">Niveau de relance *</label>
          <Select
            v-model="relanceNiveau"
            :options="niveauxOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            aria-labelledby="label-niveau"
          />
          <small class="hint">{{ niveauLabel }}</small>
        </div>
      </div>

      <template #footer>
        <Button
          label="Annuler"
          severity="secondary"
          outlined
          :disabled="relanceSaving"
          @click="relanceDialog = false"
        />
        <Button
          label="Envoyer"
          icon="pi pi-send"
          :loading="relanceSaving"
          @click="envoyerRelance"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.page-title { font-size: 1.375rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--p-text-muted-color); margin: 0.25rem 0 0; font-size: 0.875rem; }

/* KPI bar */
.kpi-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.kpi-item {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  padding: 0.75rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 10rem;
}
.kpi-item.kpi-danger { border-color: var(--p-red-300); background: var(--p-red-50); }
.kpi-label { font-size: 0.75rem; color: var(--p-text-muted-color); text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-value { font-size: 1.25rem; font-weight: 700; color: var(--p-text-color); }
.kpi-item.kpi-danger .kpi-value { color: var(--p-red-600); }

/* Table */
.creances-table { border-radius: 0.5rem; overflow: hidden; }
.cell-main { font-weight: 500; }
.cell-sub { font-size: 0.75rem; color: var(--p-text-muted-color); margin-top: 2px; }
.montant-restant { font-weight: 700; color: var(--p-red-600); }

/* Empty state */
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
}
.empty-icon { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: var(--p-green-500); }

/* Dialog */
.relance-dialog-body { display: flex; flex-direction: column; gap: 1.25rem; }

.facture-recap {
  background: var(--p-surface-ground);
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.recap-row { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.recap-label { font-size: 0.8rem; color: var(--p-text-muted-color); flex-shrink: 0; }
.recap-val { font-size: 0.875rem; font-weight: 500; text-align: right; }
.email-val { font-size: 0.8rem; color: var(--p-primary-color); word-break: break-all; }

.form-field { display: flex; flex-direction: column; gap: 0.4rem; }
.form-label { font-size: 0.875rem; font-weight: 600; }
.hint { font-size: 0.75rem; color: var(--p-text-muted-color); }

@media (max-width: 640px) {
  .kpi-item { flex: 1; min-width: 0; }
  .page-header { flex-direction: column; }
}
</style>
