<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { portailApi } from '@/api/modules/portail'
import type { Mission } from '@/types'

const toast = useToast()

const missions = ref<Mission[]>([])
const loading = ref(false)
const downloading = ref<string | null>(null)

async function fetchDocuments() {
  loading.value = true
  try {
    const res = await portailApi.getDocuments()
    missions.value = res.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les documents.', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function telechargerConvention(mission: Mission) {
  if (!mission.convention_numero) return
  downloading.value = `convention-${mission.id}`
  try {
    await portailApi.telechargerConventionPdf(mission.id, mission.convention_numero)
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de télécharger la convention.', life: 3000 })
  } finally {
    downloading.value = null
  }
}

async function telechargerMandat(mission: Mission) {
  if (!mission.mandat_numero) return
  downloading.value = `mandat-${mission.id}`
  try {
    await portailApi.telechargerMandatPdf(mission.id, mission.mandat_numero)
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de télécharger le mandat.', life: 3000 })
  } finally {
    downloading.value = null
  }
}

function formatDate(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('fr-FR')
}

onMounted(fetchDocuments)
</script>

<template>
  <div>
    <header class="page-header">
      <h1 id="page-title">Mes documents</h1>
      <p class="page-subtitle">Conventions et mandats partagés par le cabinet</p>
    </header>

    <div v-if="loading" class="loading-state" role="status" aria-live="polite">
      <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
      <span>Chargement des documents…</span>
    </div>

    <div v-else-if="missions.length === 0" class="empty-state" role="status">
      <i class="pi pi-folder-open" aria-hidden="true"></i>
      <p>Aucun document disponible pour le moment.</p>
    </div>

    <div v-else class="missions-list" aria-labelledby="page-title">
      <article
        v-for="mission in missions"
        :key="mission.id"
        class="mission-card"
        :aria-label="`Mission ${mission.reference} — ${mission.prestation?.designation ?? ''}`"
      >
        <div class="mission-header">
          <div class="mission-meta">
            <span class="mission-ref">{{ mission.reference }}</span>
            <Tag :value="mission.prestation?.designation ?? '-'" severity="secondary" />
          </div>
          <span class="mission-dates">
            {{ formatDate(mission.date_debut) }} — {{ formatDate(mission.date_fin) }}
          </span>
        </div>

        <div class="documents-grid">
          <!-- Convention -->
          <div
            v-if="mission.convention_numero"
            class="doc-item"
          >
            <div class="doc-info">
              <i class="pi pi-file-pdf doc-icon" aria-hidden="true"></i>
              <div>
                <div class="doc-label">Convention de prestation</div>
                <div class="doc-ref">{{ mission.convention_numero }}</div>
              </div>
            </div>
            <Button
              label="Télécharger"
              icon="pi pi-download"
              size="small"
              severity="secondary"
              :loading="downloading === `convention-${mission.id}`"
              :aria-label="`Télécharger la convention ${mission.convention_numero}`"
              @click="telechargerConvention(mission)"
            />
          </div>

          <!-- Mandat -->
          <div
            v-if="mission.mandat_numero"
            class="doc-item"
          >
            <div class="doc-info">
              <i class="pi pi-file-pdf doc-icon" aria-hidden="true"></i>
              <div>
                <div class="doc-label">Mandat d'acceptation</div>
                <div class="doc-ref">{{ mission.mandat_numero }}</div>
              </div>
            </div>
            <Button
              label="Télécharger"
              icon="pi pi-download"
              size="small"
              :loading="downloading === `mandat-${mission.id}`"
              :aria-label="`Télécharger le mandat ${mission.mandat_numero}`"
              @click="telechargerMandat(mission)"
            />
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 1.5rem;
}

/* Taille/graisse : base h1 globale (main.css) */
.page-header h1 {
  margin: 0 0 0.25rem;
}

.page-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem;
  color: var(--p-text-muted-color);
  font-size: 0.95rem;
}

.empty-state i {
  font-size: 2.5rem;
  opacity: 0.4;
}

.missions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.mission-card {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 1.25rem;
}

.mission-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--p-surface-border);
}

.mission-meta {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.mission-ref {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--p-text-color);
}

.mission-dates {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.documents-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.doc-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  background: var(--p-surface-ground);
  border-radius: 6px;
  flex-wrap: wrap;
}

.doc-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.doc-icon {
  font-size: 1.4rem;
  color: #1e3a5f;
  flex-shrink: 0;
}

.doc-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--p-text-color);
}

.doc-ref {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

@media (max-width: 600px) {
  .doc-item {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
