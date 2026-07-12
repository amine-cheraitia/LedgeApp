<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import { portailApi } from '@/api/modules/portail'
import type { Mission, Tache } from '@/types'

const toast = useToast()

const missions = ref<Mission[]>([])
const loading = ref(false)
const totalRecords = ref(0)
const page = ref(1)
const filtreStatut = ref<string | null>(null)

const detailVisible = ref(false)
const detailLoading = ref(false)
const missionDetail = ref<Mission | null>(null)

const statutOptions = [
  { label: 'Tous les statuts', value: null },
  { label: 'En cours', value: 'en_cours' },
  { label: 'Terminée', value: 'terminee' },
  { label: 'Suspendue', value: 'suspendue' },
  { label: 'Annulée', value: 'annulee' },
]

async function fetchMissions() {
  loading.value = true
  try {
    const res = await portailApi.getMissions({
      page: page.value,
      per_page: 15,
      statut: filtreStatut.value ?? undefined,
    })
    missions.value = res.data
    totalRecords.value = res.meta.total
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les missions.', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function openDetail(mission: Mission) {
  detailVisible.value = true
  detailLoading.value = true
  try {
    const res = await portailApi.getMission(mission.id)
    missionDetail.value = res.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger le détail.', life: 3000 })
    detailVisible.value = false
  } finally {
    detailLoading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchMissions()
}

function statutSeverity(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'info',
    terminee: 'success',
    suspendue: 'warn',
    annulee: 'danger',
  }
  return (map[statut] ?? 'secondary') as 'info' | 'success' | 'warn' | 'danger' | 'secondary'
}

function statutLabel(statut: string) {
  const map: Record<string, string> = {
    en_cours: 'En cours',
    terminee: 'Terminée',
    suspendue: 'Suspendue',
    annulee: 'Annulée',
  }
  return map[statut] ?? statut
}

function tacheStatutSeverity(statut: Tache['statut']) {
  const map: Record<string, string> = {
    a_faire: 'secondary',
    en_cours: 'info',
    terminee: 'success',
    bloquee: 'danger',
  }
  return (map[statut] ?? 'secondary') as 'secondary' | 'info' | 'success' | 'danger'
}

function avancement(mission: Mission): number {
  const taches = mission.taches ?? []
  if (taches.length === 0) return 0
  const terminees = taches.filter(t => t.statut === 'terminee').length
  return Math.round((terminees / taches.length) * 100)
}

function telechargerRapportMission(mission: Mission) {
  window.open(portailApi.rapportMissionPdfUrl(mission.id), '_blank')
}

onMounted(fetchMissions)
</script>

<template>
  <div>
    <header class="page-header">
      <h1 id="page-title">Mes missions</h1>
    </header>

    <div class="page-toolbar">
      <label for="filtre-statut-mission" class="sr-only">Filtrer par statut</label>
      <Select
        id="filtre-statut-mission"
        v-model="filtreStatut"
        :options="statutOptions"
        option-label="label"
        option-value="value"
        aria-label="Filtrer par statut de mission"
        @change="fetchMissions"
      />
    </div>

    <DataTable aria-label="Mes missions"
      :value="missions"
      :loading="loading"
      :paginator="true"
      :rows="15"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      stripedRows
      aria-labelledby="page-title"
    >
      <Column field="reference" header="Référence" />
      <Column header="Prestation">
        <template #body="{ data }">
          {{ data.prestation?.designation ?? '—' }}
        </template>
      </Column>
      <Column field="date_debut" header="Début" />
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="statutLabel(data.statut)" :severity="statutSeverity(data.statut)" />
        </template>
      </Column>
      <Column header="Avancement">
        <template #body="{ data }">
          <div class="avancement-bar" role="progressbar" :aria-valuenow="avancement(data)" aria-valuemin="0" aria-valuemax="100">
            <div class="avancement-fill" :style="{ width: avancement(data) + '%' }" />
            <span class="avancement-label">{{ avancement(data) }}%</span>
          </div>
        </template>
      </Column>
      <Column header="Détail" style="width: 5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-eye"
            text
            severity="info"
            :aria-label="`Voir le détail de la mission ${data.reference}`"
            @click="openDetail(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog détail mission — tâches sans commentaires internes -->
    <Dialog
      v-model:visible="detailVisible"
      :header="missionDetail?.reference ?? 'Détail mission'"
      :modal="true"
      :style="{ width: '38rem', maxWidth: '95vw' }"
    >
      <div v-if="detailLoading" class="detail-loading" aria-live="polite" aria-label="Chargement...">
        <i class="pi pi-spin pi-spinner" aria-hidden="true" />
      </div>

      <template v-else-if="missionDetail">
        <div class="detail-info">
          <div class="detail-row">
            <span class="detail-label">Prestation</span>
            <span>{{ missionDetail.prestation?.designation }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Statut</span>
            <Tag :value="statutLabel(missionDetail.statut)" :severity="statutSeverity(missionDetail.statut)" />
          </div>
          <div class="detail-row">
            <span class="detail-label">Début</span>
            <span>{{ missionDetail.date_debut }}</span>
          </div>
          <div v-if="missionDetail.date_fin" class="detail-row">
            <span class="detail-label">Fin</span>
            <span>{{ missionDetail.date_fin }}</span>
          </div>
        </div>

        <section v-if="missionDetail.taches?.length" aria-labelledby="taches-title" class="detail-taches">
          <h2 id="taches-title" class="detail-section-title">Tâches ({{ missionDetail.taches.length }})</h2>
          <ul class="tache-list" role="list">
            <li
              v-for="tache in missionDetail.taches"
              :key="tache.id"
              class="tache-item"
            >
              <Tag
                :value="tache.statut.replace('_', ' ')"
                :severity="tacheStatutSeverity(tache.statut)"
                class="tache-tag"
              />
              <span class="tache-titre">{{ tache.titre }}</span>
            </li>
          </ul>
        </section>
        <p v-else class="text-muted">Aucune tâche pour cette mission.</p>

        <div class="detail-rapport" style="border-top: 1px solid var(--p-surface-border); padding-top: 1rem; margin-top: 1rem;">
          <Button
            label="Télécharger le rapport PDF"
            icon="pi pi-file-pdf"
            severity="secondary"
            outlined
            size="small"
            aria-label="Télécharger le rapport de fin de mission en PDF"
            @click="telechargerRapportMission(missionDetail)"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 1.25rem;
}

.page-header h1 {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0;
  color: var(--p-text-color);
}

.page-toolbar {
  margin-bottom: 1rem;
}

.avancement-bar {
  position: relative;
  height: 8px;
  background: var(--p-surface-border);
  border-radius: 4px;
  min-width: 80px;
  overflow: visible;
}

.avancement-fill {
  height: 100%;
  background: var(--p-primary-color);
  border-radius: 4px;
  transition: width 0.3s;
}

.avancement-label {
  position: absolute;
  right: 0;
  top: -18px;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}

.detail-loading {
  display: flex;
  justify-content: center;
  padding: 2rem;
  font-size: 1.5rem;
  color: var(--p-primary-color);
}

.detail-info {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin-bottom: 1.25rem;
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.detail-label {
  font-size: 0.8rem;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
  min-width: 80px;
}

.detail-taches {
  border-top: 1px solid var(--p-surface-border);
  padding-top: 1rem;
}

.detail-section-title {
  font-size: 0.95rem;
  font-weight: 600;
  margin: 0 0 0.75rem;
  color: var(--p-text-color);
}

.tache-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.tache-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.tache-titre {
  font-size: 0.9rem;
  color: var(--p-text-color);
}

.text-muted {
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
}
</style>
