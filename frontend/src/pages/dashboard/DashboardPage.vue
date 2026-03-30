<script setup lang="ts">
import { statsApi, type DashboardStats } from '@/api/modules/stats'
import { useAuthStore } from '@/stores/auth'
import { onMounted, ref } from 'vue'

const auth = useAuthStore()
const loading = ref(true)
const stats = ref<DashboardStats | null>(null)

onMounted(async () => {
  try {
    const res = await statsApi.getDashboard()
    stats.value = res.data
  } finally {
    loading.value = false
  }
})

function formatDA(montant: number): string {
  return new Intl.NumberFormat('fr-DZ', { style: 'decimal', minimumFractionDigits: 2 }).format(montant) + ' DA'
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function statutPaiementSeverity(statut: string): string {
  const map: Record<string, string> = { en_attente: 'warn', partiel: 'info', solde: 'success' }
  return map[statut] ?? 'secondary'
}

function statutPaiementLabel(statut: string): string {
  const map: Record<string, string> = { en_attente: 'En attente', partiel: 'Partiel', solde: 'Soldé' }
  return map[statut] ?? statut
}

function statutMissionSeverity(statut: string): string {
  const map: Record<string, string> = { en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger' }
  return map[statut] ?? 'secondary'
}

function statutMissionLabel(statut: string): string {
  const map: Record<string, string> = { en_cours: 'En cours', terminee: 'Terminée', suspendue: 'Suspendue', annulee: 'Annulée' }
  return map[statut] ?? statut
}
</script>

<template>
  <div class="grid grid-cols-12 gap-8">
    <!-- Header -->
    <div class="col-span-12">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 m-0">Tableau de bord</h2>
          <p class="text-muted-color mt-1">Bienvenue, {{ auth.user?.name }}.</p>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="col-span-12 flex justify-center py-20">
      <ProgressSpinner aria-label="Chargement du tableau de bord" />
    </div>

    <template v-if="stats && !loading">
      <!-- Stat Cards -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Clients</span>
            <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-building text-blue-500 !text-xl"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ stats.entreprises.clients }}</div>
          <div class="flex items-center mt-4">
            <span class="text-muted-color">{{ stats.entreprises.prospects }} prospects</span>
            <span class="text-muted-color mx-2">&bull;</span>
            <span class="font-medium">{{ stats.entreprises.total }} total</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Missions actives</span>
            <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-briefcase text-orange-500 !text-xl"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ stats.missions.en_cours }}</div>
          <div class="flex items-center mt-4">
            <span class="text-green-500 font-medium">{{ stats.missions.terminees }} terminées</span>
            <span class="text-muted-color mx-2">&bull;</span>
            <span class="text-muted-color">{{ stats.missions.total }} total</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Chiffre d'affaires</span>
            <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-dollar text-cyan-500 !text-xl"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ formatDA(stats.factures.ca_ttc) }}</div>
          <div class="flex items-center mt-4">
            <span class="text-green-500 font-medium">{{ formatDA(stats.factures.total_paye) }}</span>
            <span class="text-muted-color ml-1">encaissé</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Impayés</span>
            <div class="flex items-center justify-center rounded-border" :class="stats.factures.en_retard > 0 ? 'bg-red-100 dark:bg-red-400/10' : 'bg-green-100 dark:bg-green-400/10'" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-exclamation-triangle !text-xl" :class="stats.factures.en_retard > 0 ? 'text-red-500' : 'text-green-500'"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ formatDA(stats.factures.total_impaye) }}</div>
          <div class="flex items-center mt-4">
            <span v-if="stats.factures.en_retard > 0" class="text-red-500 font-medium">{{ stats.factures.en_retard }} en retard</span>
            <span v-else class="text-green-500 font-medium">Aucun retard</span>
            <span class="text-muted-color mx-2">&bull;</span>
            <span class="text-muted-color">{{ stats.factures.en_attente + stats.factures.partielles }} factures</span>
          </div>
        </div>
      </div>

      <!-- Devis résumé -->
      <div class="col-span-12 xl:col-span-4">
        <div class="card h-full">
          <div class="text-surface-900 dark:text-surface-0 font-bold text-lg mb-4">Devis</div>
          <ul class="list-none p-0 m-0">
            <li class="flex items-center justify-between py-3 border-b border-surface">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2rem; height: 2rem">
                  <i class="pi pi-file text-blue-500 text-sm"></i>
                </div>
                <span class="text-surface-900 dark:text-surface-0 font-medium">En attente</span>
              </div>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ stats.devis.en_attente }}</span>
            </li>
            <li class="flex items-center justify-between py-3 border-b border-surface">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center bg-green-100 dark:bg-green-400/10 rounded-border" style="width: 2rem; height: 2rem">
                  <i class="pi pi-check text-green-500 text-sm"></i>
                </div>
                <span class="text-surface-900 dark:text-surface-0 font-medium">Acceptés</span>
              </div>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ stats.devis.acceptes }}</span>
            </li>
            <li class="flex items-center justify-between py-3">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border" style="width: 2rem; height: 2rem">
                  <i class="pi pi-wallet text-purple-500 text-sm"></i>
                </div>
                <span class="text-surface-900 dark:text-surface-0 font-medium">CA potentiel</span>
              </div>
              <span class="font-bold text-surface-900 dark:text-surface-0">{{ formatDA(stats.devis.ca_potentiel) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Factures récentes -->
      <div class="col-span-12 xl:col-span-8">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <div class="text-surface-900 dark:text-surface-0 font-bold text-lg">Dernières factures</div>
            <router-link to="/factures" class="text-primary font-medium no-underline hover:underline text-sm">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1"></i>
            </router-link>
          </div>
          <DataTable :value="stats.recentes.factures" :rows="5" responsiveLayout="scroll">
            <Column field="numero" header="N°" style="min-width: 8rem">
              <template #body="{ data }">
                <span class="font-bold">{{ data.numero }}</span>
              </template>
            </Column>
            <Column header="Entreprise" style="min-width: 10rem">
              <template #body="{ data }">
                {{ data.entreprise?.raison_sociale ?? '—' }}
              </template>
            </Column>
            <Column header="Montant TTC" style="min-width: 8rem">
              <template #body="{ data }">
                {{ formatDA(data.montant_ttc) }}
              </template>
            </Column>
            <Column header="Statut" style="min-width: 7rem">
              <template #body="{ data }">
                <Tag :value="statutPaiementLabel(data.statut_paiement)" :severity="statutPaiementSeverity(data.statut_paiement)" />
              </template>
            </Column>
            <Column header="Date" style="min-width: 7rem">
              <template #body="{ data }">
                {{ formatDate(data.date_facture) }}
              </template>
            </Column>
          </DataTable>
          <div v-if="stats.recentes.factures.length === 0" class="text-center text-muted-color py-6">
            Aucune facture pour le moment.
          </div>
        </div>
      </div>

      <!-- Missions récentes -->
      <div class="col-span-12">
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div class="text-surface-900 dark:text-surface-0 font-bold text-lg">Dernières missions</div>
            <router-link to="/missions" class="text-primary font-medium no-underline hover:underline text-sm">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1"></i>
            </router-link>
          </div>
          <DataTable :value="stats.recentes.missions" :rows="5" responsiveLayout="scroll">
            <Column field="reference" header="Référence" style="min-width: 8rem">
              <template #body="{ data }">
                <router-link :to="`/missions/${data.id}`" class="text-primary font-bold no-underline hover:underline">
                  {{ data.reference }}
                </router-link>
              </template>
            </Column>
            <Column header="Entreprise" style="min-width: 10rem">
              <template #body="{ data }">
                {{ data.entreprise?.raison_sociale ?? '—' }}
              </template>
            </Column>
            <Column header="Prestation" style="min-width: 10rem">
              <template #body="{ data }">
                {{ data.prestation?.libelle ?? '—' }}
              </template>
            </Column>
            <Column header="Prix HT" style="min-width: 8rem">
              <template #body="{ data }">
                {{ formatDA(data.prix_ht) }}
              </template>
            </Column>
            <Column header="Statut" style="min-width: 7rem">
              <template #body="{ data }">
                <Tag :value="statutMissionLabel(data.statut)" :severity="statutMissionSeverity(data.statut)" />
              </template>
            </Column>
            <Column header="Début" style="min-width: 7rem">
              <template #body="{ data }">
                {{ data.date_debut ? formatDate(data.date_debut) : '—' }}
              </template>
            </Column>
          </DataTable>
          <div v-if="stats.recentes.missions.length === 0" class="text-center text-muted-color py-6">
            Aucune mission pour le moment.
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
