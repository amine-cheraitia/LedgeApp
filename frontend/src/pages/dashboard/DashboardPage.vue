<script setup lang="ts">
import { statsApi, type DashboardStats, type CollaborateurStats } from '@/api/modules/stats'
import { useAuthStore } from '@/stores/auth'
import { onMounted, ref } from 'vue'
import Select from 'primevue/select'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ProgressBar from 'primevue/progressbar'

const auth = useAuthStore()
const loading = ref(true)
const stats = ref<DashboardStats | null>(null)
const collabStats = ref<CollaborateurStats | null>(null)
const exerciceId = ref<number | null>(null)

async function fetchStats() {
  if (auth.isCollaborateur) {
    try {
      const res = await statsApi.getCollaborateurDashboard()
      collabStats.value = res.data
    } catch {
      // silencieux
    } finally {
      loading.value = false
    }
    return
  }
  loading.value = true
  try {
    const res = await statsApi.getDashboard(exerciceId.value)
    stats.value = res.data
  } catch {
    // Silencieux — stats non disponibles
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)

function alerteSeverity(type: string): 'error' | 'warn' | 'info' | 'success' {
  return type === 'danger' ? 'error' : type === 'warn' ? 'warn' : 'info'
}

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
  <section aria-labelledby="dashboard-title">
  <div class="grid grid-cols-12 gap-8">
    <!-- Header -->
    <div class="col-span-12">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h2 id="dashboard-title" class="text-2xl font-bold text-surface-900 dark:text-surface-0 m-0">Tableau de bord</h2>
          <p class="text-muted-color mt-1">Bienvenue, {{ auth.user?.name }}.</p>
        </div>
        <div v-if="stats">
          <label for="filtre-exercice" class="sr-only">Filtrer par exercice</label>
          <Select
            id="filtre-exercice"
            v-model="exerciceId"
            :options="[{ id: null, annee: 'Tous les exercices' }, ...stats.exercices]"
            option-label="annee"
            option-value="id"
            aria-label="Filtrer les KPI par exercice"
            @change="fetchStats"
          />
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="col-span-12 flex justify-center py-20">
      <ProgressSpinner aria-label="Chargement du tableau de bord" />
    </div>

    <!-- Dashboard collaborateur -->
    <template v-if="!loading && auth.isCollaborateur && collabStats">

      <!-- KPI tâches -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Missions assignées</span>
            <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width:2.5rem;height:2.5rem">
              <i class="pi pi-briefcase text-blue-500 text-xl!" aria-hidden="true"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ collabStats.missions.total }}</div>
          <div class="flex items-center gap-2 mt-4 text-sm">
            <span class="text-blue-500 font-medium">{{ collabStats.missions.en_cours }} en cours</span>
            <span class="text-muted-color">&bull;</span>
            <span class="text-green-500 font-medium">{{ collabStats.missions.terminees }} terminées</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Mes tâches</span>
            <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border" style="width:2.5rem;height:2.5rem">
              <i class="pi pi-check-square text-orange-500 text-xl!" aria-hidden="true"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ collabStats.taches.total }}</div>
          <div class="flex items-center gap-2 mt-4 text-sm">
            <span class="text-orange-500 font-medium">{{ collabStats.taches.en_cours }} en cours</span>
            <span class="text-muted-color">&bull;</span>
            <span class="text-muted-color">{{ collabStats.taches.a_faire }} à faire</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Taux de complétion</span>
            <div
              class="flex items-center justify-center rounded-border"
              :class="collabStats.taches.taux_completion >= 70 ? 'bg-green-100 dark:bg-green-400/10' : 'bg-orange-100 dark:bg-orange-400/10'"
              style="width:2.5rem;height:2.5rem"
            >
              <i
                class="text-xl!"
                :class="collabStats.taches.taux_completion >= 70 ? 'pi pi-check-circle text-green-500' : 'pi pi-clock text-orange-500'"
                aria-hidden="true"
              ></i>
            </div>
          </div>
          <div
            class="font-bold text-2xl"
            :class="collabStats.taches.taux_completion >= 70 ? 'text-green-600' : 'text-orange-500'"
          >
            {{ collabStats.taches.taux_completion }}%
          </div>
          <div class="mt-3">
            <ProgressBar
              :value="collabStats.taches.taux_completion"
              :showValue="false"
              style="height:6px"
              :aria-label="`Taux de complétion des tâches : ${collabStats.taches.taux_completion}%`"
            />
            <span class="text-muted-color text-xs mt-1 block">{{ collabStats.taches.terminees }} / {{ collabStats.taches.total }} tâches terminées</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Tâches bloquées</span>
            <div
              class="flex items-center justify-center rounded-border"
              :class="collabStats.taches.bloquees > 0 ? 'bg-red-100 dark:bg-red-400/10' : 'bg-green-100 dark:bg-green-400/10'"
              style="width:2.5rem;height:2.5rem"
            >
              <i
                class="text-xl!"
                :class="collabStats.taches.bloquees > 0 ? 'pi pi-ban text-red-500' : 'pi pi-check text-green-500'"
                aria-hidden="true"
              ></i>
            </div>
          </div>
          <div
            class="font-bold text-3xl"
            :class="collabStats.taches.bloquees > 0 ? 'text-red-500' : 'text-green-600'"
          >
            {{ collabStats.taches.bloquees }}
          </div>
          <div class="mt-4 text-sm text-muted-color">
            {{ collabStats.taches.bloquees > 0 ? 'À débloquer' : 'Aucun blocage' }}
          </div>
        </div>
      </div>

      <!-- Mes missions -->
      <div class="col-span-12 xl:col-span-7">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-surface-900 dark:text-surface-0 font-bold text-lg m-0">Mes missions</h3>
            <router-link to="/missions" class="text-primary font-medium no-underline hover:underline text-sm" aria-label="Voir toutes mes missions">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1" aria-hidden="true"></i>
            </router-link>
          </div>
          <DataTable :value="collabStats.mes_missions" responsiveLayout="scroll" aria-label="Liste de mes missions">
            <Column header="Référence" style="min-width:8rem">
              <template #body="{ data }">
                <router-link :to="`/missions/${data.id}`" class="text-primary font-bold no-underline hover:underline">
                  {{ data.reference }}
                </router-link>
              </template>
            </Column>
            <Column header="Client" style="min-width:9rem">
              <template #body="{ data }">{{ data.entreprise ?? '—' }}</template>
            </Column>
            <Column header="Statut" style="min-width:7rem">
              <template #body="{ data }">
                <Tag :value="statutMissionLabel(data.statut)" :severity="statutMissionSeverity(data.statut)" />
              </template>
            </Column>
            <Column header="Progression" style="min-width:10rem">
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <ProgressBar :value="data.progression" :showValue="false" style="height:6px;flex:1" :aria-label="`Progression ${data.progression}%`" />
                  <span class="text-xs text-muted-color" style="min-width:2.5rem">{{ data.progression }}%</span>
                </div>
              </template>
            </Column>
          </DataTable>
          <div v-if="collabStats.mes_missions.length === 0" class="text-center text-muted-color py-6">
            Aucune mission assignée.
          </div>
        </div>
      </div>

      <!-- Tâches urgentes -->
      <div class="col-span-12 xl:col-span-5">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-surface-900 dark:text-surface-0 font-bold text-lg m-0">Tâches à traiter</h3>
            <router-link to="/missions" class="text-primary font-medium no-underline hover:underline text-sm" aria-label="Voir mes missions">
              Voir tout <i class="pi pi-arrow-right text-xs ml-1" aria-hidden="true"></i>
            </router-link>
          </div>
          <ul class="list-none p-0 m-0" role="list" aria-label="Tâches urgentes">
            <li
              v-for="tache in collabStats.mes_taches_urgentes"
              :key="tache.id"
              class="flex items-start justify-between py-3 border-b border-surface last:border-0 gap-3"
            >
              <div class="flex items-start gap-3 min-w-0">
                <i
                  class="pi mt-1 flex-shrink-0"
                  :class="tache.en_retard ? 'pi-exclamation-triangle text-red-500' : tache.statut === 'en_cours' ? 'pi-spin pi-spinner text-blue-500' : 'pi-circle text-muted-color'"
                  aria-hidden="true"
                ></i>
                <div class="min-w-0">
                  <div class="font-medium text-surface-900 dark:text-surface-0 truncate">{{ tache.titre }}</div>
                  <div class="text-xs text-muted-color mt-0.5">
                    <router-link :to="`/missions/${tache.mission_id}`" class="text-primary no-underline hover:underline">
                      {{ tache.mission_reference }}
                    </router-link>
                    <span v-if="tache.entreprise"> · {{ tache.entreprise }}</span>
                  </div>
                </div>
              </div>
              <div class="flex flex-col items-end gap-1 flex-shrink-0">
                <Tag :value="tache.statut === 'en_cours' ? 'En cours' : 'À faire'" :severity="tache.statut === 'en_cours' ? 'info' : 'secondary'" />
                <span v-if="tache.date_fin" class="text-xs" :class="tache.en_retard ? 'text-red-500 font-medium' : 'text-muted-color'">
                  {{ formatDate(tache.date_fin) }}
                </span>
              </div>
            </li>
          </ul>
          <div v-if="collabStats.mes_taches_urgentes.length === 0" class="text-center text-muted-color py-6">
            Aucune tâche en cours.
          </div>
        </div>
      </div>

    </template>

    <template v-if="stats && !loading">
      <!-- Alertes -->
      <div v-if="stats.alertes.length" class="col-span-12" role="alert" aria-live="polite">
        <Message
          v-for="(alerte, i) in stats.alertes"
          :key="i"
          :severity="alerteSeverity(alerte.type)"
          :closable="false"
          class="mb-2"
        >
          {{ alerte.message }}
        </Message>
      </div>

      <!-- KPI widgets -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-4">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">CA du mois</span>
            <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-chart-line text-cyan-500 text-xl!" aria-hidden="true"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">{{ formatDA(stats.kpi.ca_mois) }}</div>
          <div class="mt-4">
            <span class="text-muted-color text-sm">Factures émises ce mois</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-4">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">TVA collectée</span>
            <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-percentage text-purple-500 text-xl!" aria-hidden="true"></i>
            </div>
          </div>
          <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">{{ formatDA(stats.kpi.tva_collectee) }}</div>
          <div class="mt-4">
            <span class="text-muted-color text-sm">Cumul sur la période</span>
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6 xl:col-span-4">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Taux de recouvrement</span>
            <div
              class="flex items-center justify-center rounded-border"
              :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'bg-green-100 dark:bg-green-400/10' : 'bg-orange-100 dark:bg-orange-400/10'"
              style="width: 2.5rem; height: 2.5rem"
            >
              <i
                class="text-xl!"
                :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'pi pi-check-circle text-green-500' : 'pi pi-exclamation-circle text-orange-500'"
                aria-hidden="true"
              ></i>
            </div>
          </div>
          <div
            class="font-bold text-2xl"
            :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'text-green-600' : 'text-orange-500'"
          >
            {{ stats.kpi.taux_recouvrement }}%
          </div>
          <div class="mt-3">
            <div
              role="progressbar"
              :aria-valuenow="stats.kpi.taux_recouvrement"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-label="`Taux de recouvrement : ${stats.kpi.taux_recouvrement}%`"
              class="recouvrement-bar"
            >
              <div
                class="recouvrement-fill"
                :style="{ width: stats.kpi.taux_recouvrement + '%' }"
                :class="stats.kpi.taux_recouvrement >= stats.kpi.seuil_recouvrement ? 'fill-success' : 'fill-warn'"
              />
            </div>
            <span class="text-muted-color text-xs mt-1 block">Seuil : {{ stats.kpi.seuil_recouvrement }}%</span>
          </div>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card h-full">
          <div class="flex items-center justify-between mb-4">
            <span class="text-muted-color font-medium">Clients</span>
            <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
              <i class="pi pi-building text-blue-500 text-xl!"></i>
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
              <i class="pi pi-briefcase text-orange-500 text-xl!"></i>
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
              <i class="pi pi-dollar text-cyan-500 text-xl!"></i>
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
              <i class="pi pi-exclamation-triangle text-xl!" :class="stats.factures.en_retard > 0 ? 'text-red-500' : 'text-green-500'"></i>
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
  </section>
</template>

<style scoped>
.recouvrement-bar {
  height: 6px;
  background: var(--p-surface-border);
  border-radius: 3px;
  overflow: hidden;
}

.recouvrement-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease;
}

.fill-success {
  background: var(--p-green-500, #22c55e);
}

.fill-warn {
  background: var(--p-orange-500, #f97316);
}

</style>
