<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import { statsApi, type KpiCollaborateur } from '@/api/modules/stats'
import { exercicesApi } from '@/api/modules/exercices'
import type { Exercice } from '@/types'

const toast = useToast()

const exercices = ref<Exercice[]>([])
const exerciceId = ref<number | null>(null)
const collaborateurs = ref<KpiCollaborateur[]>([])
const loading = ref(false)
const saving = ref<string | null>(null)

// Valeurs en cours d'édition : clé = `${userId}-${type}`
const editing = ref<Record<string, number | null>>({})

const types = [
  { key: 'ca_ht', label: 'CA HT (DA)', unit: 'DA', icon: 'pi pi-chart-line' },
  { key: 'missions_cloturees', label: 'Missions clôturées', unit: '', icon: 'pi pi-briefcase' },
  { key: 'delai_moyen_facturation', label: 'Délai moyen facturation', unit: 'jours', icon: 'pi pi-clock' },
] as const

type KpiType = 'ca_ht' | 'missions_cloturees' | 'delai_moyen_facturation'

async function fetchExercices() {
  const res = await exercicesApi.getAll()
  exercices.value = res.data
  const courant = res.data.find((e: Exercice) => e.statut === 'ouvert')
  if (courant) exerciceId.value = courant.id
}

async function fetchKpi() {
  loading.value = true
  try {
    const res = await statsApi.getKpiObjectifs(exerciceId.value)
    collaborateurs.value = res.data
    // Initialise les valeurs d'édition depuis les objectifs existants
    editing.value = {}
    for (const collab of res.data) {
      for (const type of types) {
        const key = `${collab.user.id}-${type.key}`
        editing.value[key] = collab.objectifs[type.key] ?? null
      }
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les KPI.', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function sauvegarder(collab: KpiCollaborateur, type: KpiType) {
  if (!exerciceId.value) return
  const key = `${collab.user.id}-${type}`
  const valeur = editing.value[key]
  if (valeur === null || valeur === undefined) return

  saving.value = key
  try {
    await statsApi.upsertKpiObjectif({
      user_id: collab.user.id,
      exercice_id: exerciceId.value,
      type,
      valeur,
    })
    toast.add({ severity: 'success', summary: 'Sauvegardé', detail: 'Objectif mis à jour.', life: 2000 })
    await fetchKpi()
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de sauvegarder.', life: 3000 })
  } finally {
    saving.value = null
  }
}

function pourcentage(realise: number, objectif: number | undefined): number {
  if (!objectif || objectif <= 0) return 0
  return Math.min(Math.round((realise / objectif) * 100), 100)
}

function progressSeverity(pct: number): string {
  if (pct >= 100) return 'success'
  if (pct >= 70) return 'info'
  if (pct >= 40) return 'warn'
  return 'danger'
}

function formatValeur(val: number, unit: string): string {
  if (unit === 'DA') return new Intl.NumberFormat('fr-DZ').format(val) + ' DA'
  if (unit === 'jours') return val.toFixed(1) + ' j'
  return String(Math.round(val))
}

const exerciceOptions = computed(() =>
  exercices.value.map((e) => ({ label: `${e.annee}${e.statut === 'ouvert' ? ' (ouvert)' : ''}`, value: e.id }))
)

onMounted(async () => {
  await fetchExercices()
  await fetchKpi()
})
</script>

<template>
  <div>
    <header class="page-header">
      <div>
        <h1 id="page-title">KPI objectifs collaborateurs</h1>
        <p class="page-subtitle">Définissez les cibles et suivez le réalisé par collaborateur</p>
      </div>
      <div class="toolbar">
        <label for="filtre-exercice" class="sr-only">Exercice</label>
        <Select
          id="filtre-exercice"
          v-model="exerciceId"
          :options="exerciceOptions"
          option-label="label"
          option-value="value"
          aria-label="Filtrer par exercice"
          @change="fetchKpi"
        />
      </div>
    </header>

    <div v-if="loading" class="loading-state" role="status" aria-live="polite">
      <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
      <span>Chargement…</span>
    </div>

    <div v-else-if="collaborateurs.length === 0" class="empty-state">
      <i class="pi pi-users" aria-hidden="true"></i>
      <p>Aucun collaborateur trouvé.</p>
    </div>

    <div v-else class="collaborateurs-list" aria-labelledby="page-title">
      <section
        v-for="collab in collaborateurs"
        :key="collab.user.id"
        class="collab-card"
        :aria-label="`Objectifs de ${collab.user.name}`"
      >
        <div class="collab-header">
          <div class="collab-avatar" aria-hidden="true">{{ collab.user.name.charAt(0).toUpperCase() }}</div>
          <div>
            <div class="collab-name">{{ collab.user.name }}</div>
            <div class="collab-email">{{ collab.user.email }}</div>
          </div>
        </div>

        <div class="kpi-grid">
          <div
            v-for="type in types"
            :key="type.key"
            class="kpi-row"
          >
            <div class="kpi-label">
              <i :class="type.icon" aria-hidden="true"></i>
              <span>{{ type.label }}</span>
            </div>

            <div class="kpi-valeurs">
              <!-- Réalisé -->
              <div class="kpi-realise">
                <span class="kpi-val-label">Réalisé</span>
                <strong>{{ formatValeur(collab.realise[type.key], type.unit) }}</strong>
              </div>

              <!-- Objectif éditable -->
              <div class="kpi-objectif">
                <label :for="`obj-${collab.user.id}-${type.key}`" class="kpi-val-label">Objectif</label>
                <div class="kpi-input-row">
                  <InputNumber
                    :id="`obj-${collab.user.id}-${type.key}`"
                    v-model="editing[`${collab.user.id}-${type.key}`]"
                    :min="0"
                    :max-fraction-digits="type.key === 'delai_moyen_facturation' ? 1 : 0"
                    :aria-label="`Objectif ${type.label} pour ${collab.user.name}`"
                    class="kpi-input"
                    :disabled="!exerciceId"
                  />
                  <Button
                    icon="pi pi-check"
                    text
                    rounded
                    size="small"
                    severity="success"
                    :loading="saving === `${collab.user.id}-${type.key}`"
                    :disabled="editing[`${collab.user.id}-${type.key}`] === null || !exerciceId"
                    :aria-label="`Sauvegarder objectif ${type.label} de ${collab.user.name}`"
                    @click="sauvegarder(collab, type.key)"
                  />
                </div>
              </div>

              <!-- Progression -->
              <div class="kpi-progress" v-if="collab.objectifs[type.key]">
                <div class="kpi-pct-row">
                  <span class="kpi-val-label">Progression</span>
                  <Tag
                    :value="`${pourcentage(collab.realise[type.key], collab.objectifs[type.key])}%`"
                    :severity="progressSeverity(pourcentage(collab.realise[type.key], collab.objectifs[type.key]))"
                  />
                </div>
                <ProgressBar
                  :value="pourcentage(collab.realise[type.key], collab.objectifs[type.key])"
                  :aria-label="`Progression ${type.label} : ${pourcentage(collab.realise[type.key], collab.objectifs[type.key])}%`"
                  style="height: 6px; margin-top: 4px;"
                />
              </div>
              <div v-else class="kpi-no-objectif">
                <span class="kpi-val-label" style="color: var(--p-text-muted-color);">Pas d'objectif fixé</span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0 0 0.25rem;
  color: var(--p-text-color);
}

.page-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.toolbar {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem;
  color: var(--p-text-muted-color);
}

.empty-state i { font-size: 2.5rem; opacity: 0.4; }

.collaborateurs-list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.collab-card {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
}

.collab-header {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  margin-bottom: 1.25rem;
  padding-bottom: 0.875rem;
  border-bottom: 1px solid var(--p-surface-border);
}

.collab-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--p-primary-color);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1rem;
  flex-shrink: 0;
}

.collab-name {
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--p-text-color);
}

.collab-email {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.kpi-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.kpi-row {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1rem;
  align-items: start;
  padding: 0.75rem;
  background: var(--p-surface-ground);
  border-radius: 6px;
}

.kpi-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--p-text-color);
  padding-top: 0.25rem;
}

.kpi-valeurs {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1rem;
  align-items: start;
}

.kpi-val-label {
  display: block;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  margin-bottom: 0.25rem;
}

.kpi-realise strong {
  font-size: 0.9rem;
  color: var(--p-text-color);
}

.kpi-input-row {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.kpi-input {
  width: 120px;
}

.kpi-pct-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.kpi-no-objectif {
  padding-top: 0.25rem;
}

@media (max-width: 900px) {
  .kpi-row {
    grid-template-columns: 1fr;
  }

  .kpi-valeurs {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 600px) {
  .kpi-valeurs {
    grid-template-columns: 1fr;
  }
}
</style>
