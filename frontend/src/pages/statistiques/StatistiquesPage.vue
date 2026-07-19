<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Select from 'primevue/select'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import OngletCabinet from '@/pages/statistiques/OngletCabinet.vue'
import OngletCollaborateurs from '@/pages/statistiques/OngletCollaborateurs.vue'
import { exercicesApi } from '@/api/modules/exercices'
import type { Exercice } from '@/types'

// Filtre exercice GLOBAL a la page : partage par les onglets Cabinet et Collaborateurs.
const exercices = ref<Exercice[]>([])
const exerciceId = ref<number | null>(null)
const chargementInitial = ref(true)
const erreurInitiale = ref(false)

const exerciceOptions = computed(() =>
  exercices.value.map((e) => ({ label: `${e.annee}${e.statut === 'ouvert' ? ' (ouvert)' : ''}`, value: e.id })),
)

async function init() {
  chargementInitial.value = true
  erreurInitiale.value = false
  try {
    const res = await exercicesApi.getAll()
    exercices.value = res.data
    const courant = res.data.find((e: Exercice) => e.statut === 'ouvert')
    if (courant) exerciceId.value = courant.id
  } catch {
    erreurInitiale.value = true
  } finally {
    chargementInitial.value = false
  }
}

onMounted(init)
</script>

<template>
  <div class="stats-page">
    <div class="stats-header">
      <div>
        <h1>Statistiques</h1>
        <p class="stats-subtitle">Analyse du cabinet et pilotage des collaborateurs</p>
      </div>
      <div class="stats-filtre">
        <label for="stats-exercice">Exercice</label>
        <Select
          id="stats-exercice"
          v-model="exerciceId"
          :options="exerciceOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tous les exercices"
          showClear
        />
      </div>
    </div>

    <div v-if="chargementInitial" class="stats-etat" role="status">
      <ProgressSpinner aria-label="Chargement des statistiques" style="width: 40px; height: 40px" />
      <span>Chargement…</span>
    </div>

    <div v-else-if="erreurInitiale" class="stats-etat stats-etat--erreur" role="alert">
      <i class="pi pi-exclamation-triangle" aria-hidden="true"></i>
      <p>Impossible de charger les exercices. Veuillez réessayer.</p>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="init" />
    </div>

    <Tabs v-else value="cabinet">
      <TabList>
        <Tab value="cabinet">Cabinet</Tab>
        <Tab value="collaborateurs">Collaborateurs</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="cabinet">
          <OngletCabinet :exercice-id="exerciceId" />
        </TabPanel>
        <TabPanel value="collaborateurs">
          <OngletCollaborateurs :exercice-id="exerciceId" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.stats-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
}

/* Taille/graisse : base h1 globale (main.css) */
.stats-header h1 {
  margin: 0;
}

.stats-subtitle {
  color: var(--p-text-muted-color);
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
}

.stats-filtre {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.stats-filtre label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
}

.stats-etat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
}

.stats-etat--erreur i {
  font-size: 2.5rem;
  color: var(--ledge-danger);
}

.stats-etat--erreur p {
  margin: 0;
  font-weight: 600;
  color: var(--p-text-color);
}

@media (max-width: 640px) {
  .stats-header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
