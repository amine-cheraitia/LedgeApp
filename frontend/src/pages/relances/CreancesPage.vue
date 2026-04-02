<script setup lang="ts">
import { ref, onMounted } from 'vue'
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
    await relancesApi.store(relanceFacture.value.id, { niveau: relanceNiveau.value })
    toast.add({ severity: 'success', summary: 'Relance envoyée', detail: `Niveau ${relanceNiveau.value} envoyé pour ${relanceFacture.value.numero}`, life: 3000 })
    relanceDialog.value = false
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Erreur lors de l\'envoi'
    toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 })
  } finally {
    relanceSaving.value = false
  }
}

function statutSeverity(statut: string): 'warn' | 'danger' {
  return statut === 'partiel' ? 'warn' : 'danger'
}

function joursRetard(dateEcheance: string): number {
  const diff = new Date().getTime() - new Date(dateEcheance).getTime()
  return Math.max(0, Math.floor(diff / (1000 * 60 * 60 * 24)))
}

onMounted(fetchCreances)
</script>

<template>
  <div>
    <div class="flex align-items-center justify-content-between mb-4">
      <div>
        <h1 class="text-2xl font-bold m-0">Créances impayées</h1>
        <p class="text-color-secondary mt-1 mb-0">Factures en attente ou partiellement réglées</p>
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

    <DataTable
      :value="creances"
      :loading="loading"
      striped-rows
      responsive-layout="scroll"
      aria-label="Liste des créances impayées"
    >
      <template #empty>
        <div class="text-center py-6 text-color-secondary">
          <i class="pi pi-check-circle text-4xl mb-3 block" aria-hidden="true"></i>
          Aucune créance impayée
        </div>
      </template>

      <Column field="numero" header="Facture" sortable />
      <Column header="Entreprise">
        <template #body="{ data }">
          {{ data.entreprise?.raison_sociale ?? '—' }}
        </template>
      </Column>
      <Column field="date_echeance" header="Échéance" sortable>
        <template #body="{ data }">
          {{ new Date(data.date_echeance).toLocaleDateString('fr-FR') }}
        </template>
      </Column>
      <Column header="Retard (jours)" sortable>
        <template #body="{ data }">
          <Tag
            :value="`J+${joursRetard(data.date_echeance)}`"
            :severity="joursRetard(data.date_echeance) > 30 ? 'danger' : 'warn'"
          />
        </template>
      </Column>
      <Column field="montant_ttc" header="Montant TTC" sortable>
        <template #body="{ data }">
          {{ Number(data.montant_ttc).toLocaleString('fr-FR') }} DA
        </template>
      </Column>
      <Column header="Restant dû">
        <template #body="{ data }">
          <span class="font-bold text-red-600">
            {{ Number(data.montant_restant).toLocaleString('fr-FR') }} DA
          </span>
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag
            :value="data.statut_paiement === 'partiel' ? 'Partiel' : 'Impayée'"
            :severity="statutSeverity(data.statut_paiement)"
          />
        </template>
      </Column>
      <Column header="Actions">
        <template #body="{ data }">
          <Button
            icon="pi pi-bell"
            label="Relancer"
            size="small"
            severity="warn"
            aria-label="Envoyer une relance pour cette facture"
            @click="ouvrirRelance(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog relance manuelle -->
    <Dialog
      v-model:visible="relanceDialog"
      :header="`Relance — ${relanceFacture?.numero}`"
      :style="{ width: '400px' }"
      modal
      aria-labelledby="dialog-relance-title"
    >
      <div class="flex flex-column gap-3">
        <div>
          <label id="label-niveau" class="font-semibold block mb-1">Niveau de relance</label>
          <Select
            v-model="relanceNiveau"
            :options="niveauxOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            aria-labelledby="label-niveau"
          />
        </div>
        <p class="text-color-secondary text-sm m-0">
          Un email sera envoyé à <strong>{{ relanceFacture?.entreprise?.email ?? 'N/A' }}</strong>
          avec le template de niveau {{ relanceNiveau }}.
        </p>
      </div>
      <template #footer>
        <Button
          label="Annuler"
          severity="secondary"
          outlined
          :disabled="relanceSaving"
          aria-label="Annuler l'envoi de la relance"
          @click="relanceDialog = false"
        />
        <Button
          label="Envoyer la relance"
          icon="pi pi-send"
          :loading="relanceSaving"
          aria-label="Confirmer l'envoi de la relance"
          @click="envoyerRelance"
        />
      </template>
    </Dialog>
  </div>
</template>
