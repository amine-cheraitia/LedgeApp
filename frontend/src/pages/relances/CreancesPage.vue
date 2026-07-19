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
import { formatDA } from '@/utils/currency'
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

// Valeur signee : negative tant que l'echeance n'est pas atteinte.
// L'ancien Math.max(0, ...) affichait « J+0 » pour des factures pas encore
// echues — la colonne Retard mentait.
function joursRetard(dateEcheance: string): number {
  return Math.floor((Date.now() - new Date(dateEcheance).getTime()) / 86400000)
}

function retardLabel(jours: number): string {
  return jours < 0 ? `échéance dans ${-jours} j` : `J+${jours}`
}

function retardSeverity(jours: number): 'secondary' | 'warn' | 'danger' {
  if (jours < 0) return 'secondary'
  return jours > 30 ? 'danger' : 'warn'
}

function statutSeverity(statut: string): 'warn' | 'danger' {
  return statut === 'partiel' ? 'warn' : 'danger'
}

function statutLabel(statut: string): string {
  return statut === 'partiel' ? 'Partiel' : 'Impayée'
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
        <h1 class="page-title">Créances impayées</h1>
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
        <span class="kpi-value">{{ formatDA(totalRestant) }}</span>
      </div>
    </div>

    <!-- Tableau desktop -->
    <div class="table-card">
    <DataTable
      :value="creances"
      :loading="loading"
      striped-rows
      responsive-layout="scroll"
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
            :value="retardLabel(joursRetard(data.date_echeance))"
            :severity="retardSeverity(joursRetard(data.date_echeance))"
          />
        </template>
      </Column>

      <Column header="Montant TTC" sortable style="min-width: 8rem">
        <template #body="{ data }">
          <span class="cell-mono">{{ formatDA(data.montant_ttc) }}</span>
        </template>
      </Column>

      <Column header="Restant dû" style="min-width: 8rem">
        <template #body="{ data }">
          <span
            class="montant-restant"
            :class="{ 'montant-restant--zero': Number(data.montant_restant) <= 0 }"
          >{{ formatDA(data.montant_restant) }}</span>
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
            :aria-label="`Envoyer une relance pour la facture ${data.numero}`"
            @click="ouvrirRelance(data)"
          />
        </template>
      </Column>
    </DataTable>
    </div>

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
            <span class="recap-val montant-restant">{{ formatDA(relanceFacture.montant_restant) }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Retard</span>
            <span class="recap-val">
              <Tag
                :value="retardLabel(joursRetard(relanceFacture.date_echeance))"
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
/* Taille/graisse : base h1 globale (main.css) */
.page-title { margin: 0; }
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
/* Dark mode : teinte rouge sombre au lieu du rose clair (seule surface claire
   de la page sinon), texte rouge clair pour le contraste */
.app-dark .kpi-item.kpi-danger {
  border-color: color-mix(in srgb, var(--p-red-500) 45%, transparent);
  background: color-mix(in srgb, var(--p-red-500) 14%, var(--p-surface-900));
}
.kpi-label { font-size: 0.75rem; color: var(--p-text-muted-color); text-transform: uppercase; letter-spacing: 0.05em; }
/* Chiffres en mono, regle charte */
.kpi-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--p-text-color);
  font-family: var(--ledge-ff-mono);
  letter-spacing: var(--ledge-letter-spacing-mono);
}
.kpi-item.kpi-danger .kpi-value { color: var(--p-red-600); }
.app-dark .kpi-item.kpi-danger .kpi-value { color: var(--p-red-300); }

/* Table */
.cell-main { font-weight: 500; }
.cell-sub { font-size: 0.75rem; color: var(--p-text-muted-color); margin-top: 2px; }
/* Chiffres en mono, regle charte */
.cell-mono {
  font-family: var(--ledge-ff-mono);
  letter-spacing: var(--ledge-letter-spacing-mono);
}
.montant-restant {
  font-weight: 700;
  color: var(--p-red-600);
  font-family: var(--ledge-ff-mono);
  letter-spacing: var(--ledge-letter-spacing-mono);
}
.app-dark .montant-restant { color: var(--p-red-300); }
/* Zero du : pas d'alarme rouge pour un montant nul */
.montant-restant--zero {
  color: var(--p-text-muted-color);
  font-weight: 500;
}
.app-dark .montant-restant--zero { color: var(--p-text-muted-color); }

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
.table-card :deep(.p-datatable-tbody > tr) {
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
