<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'
import { statsApi, type KpiCollaborateur, type KpiObjectifType } from '@/api/modules/stats'
import { exercicesApi } from '@/api/modules/exercices'
import { formatDA, formatDAKpi } from '@/utils/currency'
import type { Exercice } from '@/types'

const toast = useToast()
const confirm = useConfirm()

const exercices = ref<Exercice[]>([])
const exerciceId = ref<number | null>(null)
const collaborateurs = ref<KpiCollaborateur[]>([])
const loading = ref(false)
const saving = ref<number | null>(null)
const error = ref(false)

// Valeurs en cours d'édition : clé = `${userId}-${type}`
const editing = ref<Record<string, number | null>>({})

// KPIs avec objectif saisissable (cible annuelle)
const typesObjectif = [
  { key: 'ca_ht' as KpiObjectifType, label: 'CA HT cible (DA)', unit: 'DA', icon: 'pi pi-chart-line' },
  { key: 'missions_cloturees' as KpiObjectifType, label: 'Missions clôturées', unit: '', icon: 'pi pi-briefcase' },
  { key: 'taches_terminees' as KpiObjectifType, label: 'Tâches terminées', unit: '', icon: 'pi pi-check-circle' },
]

async function fetchExercices() {
  const res = await exercicesApi.getAll()
  exercices.value = res.data
  const courant = res.data.find((e: Exercice) => e.statut === 'ouvert')
  if (courant) exerciceId.value = courant.id
}

function remplirEditing(data: KpiCollaborateur[]) {
  editing.value = {}
  for (const collab of data) {
    for (const t of typesObjectif) {
      editing.value[`${collab.user.id}-${t.key}`] = collab.objectifs[t.key]?.valeur ?? null
    }
  }
}

async function fetchKpi() {
  loading.value = true
  try {
    const res = await statsApi.getKpiObjectifs(exerciceId.value)
    collaborateurs.value = res.data
    remplirEditing(res.data)
    error.value = false
  } catch {
    error.value = true
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les KPI.', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function init() {
  error.value = false
  loading.value = true
  try {
    await fetchExercices()
    await fetchKpi()
  } catch {
    error.value = true
    loading.value = false
  }
}

interface DiffOperation {
  type: KpiObjectifType
  action: 'upsert' | 'delete'
  valeur?: number
  id?: number
  ecrase: boolean
}

// Diff par type entre la saisie en cours et les objectifs existants du collaborateur
function calculerDiff(collab: KpiCollaborateur): DiffOperation[] {
  const diffs: DiffOperation[] = []
  for (const t of typesObjectif) {
    const existant = collab.objectifs[t.key]
    const saisie = editing.value[`${collab.user.id}-${t.key}`]

    if (saisie !== null && saisie !== undefined) {
      if (!existant) {
        diffs.push({ type: t.key, action: 'upsert', valeur: saisie, ecrase: false })
      } else if (existant.valeur !== saisie) {
        diffs.push({ type: t.key, action: 'upsert', valeur: saisie, ecrase: true })
      }
      // sinon : valeur identique -> aucune operation
    } else if (existant) {
      // champ vide alors qu'un objectif existe -> suppression
      diffs.push({ type: t.key, action: 'delete', id: existant.id, ecrase: false })
    }
  }
  return diffs
}

async function executerDiff(collab: KpiCollaborateur, diffs: DiffOperation[]) {
  saving.value = collab.user.id
  try {
    const resultats = await Promise.allSettled(
      diffs.map((d) =>
        d.action === 'delete'
          ? statsApi.deleteKpiObjectif(d.id!)
          : statsApi.upsertKpiObjectif({
              user_id: collab.user.id,
              exercice_id: exerciceId.value!,
              type: d.type,
              valeur: d.valeur!,
            }),
      ),
    )

    const echecsTypes: KpiObjectifType[] = []
    resultats.forEach((r, i) => {
      if (r.status === 'rejected') echecsTypes.push(diffs[i].type)
    })
    const echecs = echecsTypes.map((t) => typesObjectif.find((to) => to.key === t)?.label ?? t)

    if (echecs.length > 0) {
      toast.add({
        severity: 'error',
        summary: 'Erreur',
        detail: `Échec de la sauvegarde pour : ${echecs.join(', ')}.`,
        life: 4000,
      })
    } else {
      toast.add({ severity: 'success', summary: 'Sauvegardé', detail: 'Objectifs mis à jour.', life: 2000 })
    }
  } finally {
    saving.value = null
    await fetchKpiSilent()
  }
}

function sauvegarder(collab: KpiCollaborateur): void {
  if (!exerciceId.value) return

  const diffs = calculerDiff(collab)
  if (diffs.length === 0) {
    toast.add({ severity: 'info', summary: 'Information', detail: 'Aucune modification à enregistrer.', life: 2500 })
    return
  }

  const suppressions = diffs.filter((d) => d.action === 'delete').length
  const ecrasements = diffs.filter((d) => d.action === 'upsert' && d.ecrase).length
  const misAJour = diffs.filter((d) => d.action === 'upsert').length

  if (ecrasements > 0 || suppressions > 0) {
    const parts: string[] = []
    if (misAJour > 0) parts.push(`${misAJour} objectif${misAJour > 1 ? 's' : ''} mis à jour`)
    if (suppressions > 0) parts.push(`${suppressions} supprimé${suppressions > 1 ? 's' : ''}`)

    confirm.require({
      message: `${parts.join(', ')} pour ${collab.user.name}.`,
      header: 'Confirmation',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Confirmer',
      rejectLabel: 'Annuler',
      accept: () => executerDiff(collab, diffs),
    })
    return
  }

  executerDiff(collab, diffs)
}

async function fetchKpiSilent() {
  try {
    const res = await statsApi.getKpiObjectifs(exerciceId.value)
    collaborateurs.value = res.data
    remplirEditing(res.data)
  } catch {
    // silencieux — ne pas interrompre l'UI après sauvegarde
  }
}

function pourcentage(realise: number, objectif: number | null | undefined): number {
  if (objectif === null || objectif === undefined) return 0
  if (objectif === 0) return realise >= 0 ? 100 : 0
  return Math.round((realise / objectif) * 100)
}

function progressSeverity(pct: number): string {
  if (pct >= 100) return 'success'
  if (pct >= 70) return 'info'
  if (pct >= 40) return 'warn'
  return 'danger'
}

function formatValeur(val: number, unit: string): string {
  if (unit === 'DA') return formatDAKpi(val)
  if (unit === 'jours') return val.toFixed(1) + ' j'
  return String(Math.round(val))
}

const exerciceOptions = computed(() =>
  exercices.value.map((e) => ({ label: `${e.annee}${e.statut === 'ouvert' ? ' (ouvert)' : ''}`, value: e.id }))
)

onMounted(init)
</script>

<template>
  <div>
    <header class="page-header">
      <div>
        <h1 id="page-title">KPI objectifs collaborateurs</h1>
        <p class="page-subtitle">Définissez les cibles annuelles et suivez le réalisé par collaborateur</p>
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

    <div v-else-if="error" class="error-state" role="alert">
      <i class="pi pi-exclamation-triangle" aria-hidden="true"></i>
      <p>Impossible de charger les données. Veuillez réessayer.</p>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="init" />
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
        <!-- En-tête collaborateur -->
        <div class="collab-header">
          <div class="collab-avatar" aria-hidden="true">{{ collab.user.name.charAt(0).toUpperCase() }}</div>
          <div>
            <div class="collab-name">{{ collab.user.name }}</div>
            <div class="collab-email">{{ collab.user.email }}</div>
          </div>
        </div>

        <!-- Objectifs avec cible saisissable -->
        <div class="section-title">Objectifs annuels</div>
        <div class="kpi-grid">
          <div v-for="type in typesObjectif" :key="type.key" class="kpi-row">
            <div class="kpi-label">
              <i :class="type.icon" aria-hidden="true"></i>
              <span>{{ type.label }}</span>
            </div>

            <div class="kpi-valeurs">
              <!-- Réalisé -->
              <div class="kpi-realise">
                <span class="kpi-val-label">Réalisé</span>
                <strong
                  :title="type.unit === 'DA' ? formatDA(collab.realise[type.key]) : undefined"
                  :aria-label="type.unit === 'DA' ? formatDA(collab.realise[type.key]) : undefined"
                >{{ formatValeur(collab.realise[type.key], type.unit) }}</strong>
              </div>

              <!-- Objectif éditable -->
              <div class="kpi-objectif">
                <label :for="`obj-${collab.user.id}-${type.key}`" class="kpi-val-label">Objectif</label>
                <InputNumber
                  :id="`obj-${collab.user.id}-${type.key}`"
                  v-model="editing[`${collab.user.id}-${type.key}`]"
                  :min="0"
                  :max-fraction-digits="0"
                  :suffix="type.key === 'ca_ht' ? ' DA' : undefined"
                  :aria-label="`Objectif ${type.label} pour ${collab.user.name}`"
                  class="kpi-input"
                  :disabled="!exerciceId"
                />
              </div>

              <!-- Progression -->
              <div v-if="collab.objectifs[type.key] != null" class="kpi-progress">
                <div class="kpi-pct-row">
                  <span class="kpi-val-label">Progression</span>
                  <Tag
                    class="kpi-pct-tag"
                    :value="`${pourcentage(collab.realise[type.key], collab.objectifs[type.key]?.valeur)}%`"
                    :severity="progressSeverity(pourcentage(collab.realise[type.key], collab.objectifs[type.key]?.valeur))"
                    :aria-label="`Progression ${type.label} : ${pourcentage(collab.realise[type.key], collab.objectifs[type.key]?.valeur)} pour cent`"
                  />
                </div>
                <ProgressBar
                  :value="Math.min(pourcentage(collab.realise[type.key], collab.objectifs[type.key]?.valeur), 100)"
                  :aria-label="`Progression ${type.label} : ${pourcentage(collab.realise[type.key], collab.objectifs[type.key]?.valeur)}%`"
                  style="height: 6px; margin-top: 4px;"
                />
              </div>
              <div v-else class="kpi-no-objectif">
                <span class="kpi-val-label" style="color: var(--p-text-muted-color);">Pas d'objectif fixé</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats réalisées seulement -->
        <div class="section-title" style="margin-top: 1.25rem;">Indicateurs de suivi</div>
        <div class="stats-grid">
          <!-- Tâches terminées / en retard -->
          <div class="stat-card">
            <i class="pi pi-list-check stat-icon" aria-hidden="true"></i>
            <div class="stat-body">
              <span class="stat-label">Tâches terminées / en retard</span>
              <div class="stat-values">
                <span class="stat-ok">{{ Math.round(collab.realise.taches_terminees) }} terminées</span>
                <span class="stat-sep">·</span>
                <span :class="collab.realise.taches_en_retard > 0 ? 'stat-warn' : 'stat-ok'">
                  {{ Math.round(collab.realise.taches_en_retard) }} en retard
                </span>
              </div>
            </div>
          </div>

          <!-- Délai moyen tâche -->
          <div class="stat-card">
            <i class="pi pi-clock stat-icon" aria-hidden="true"></i>
            <div class="stat-body">
              <span class="stat-label">Délai moyen de traitement d'une tâche</span>
              <strong class="stat-val">{{ collab.realise.delai_moyen_tache.toFixed(1) }} j</strong>
            </div>
          </div>
        </div>

        <!-- Bouton sauvegarde -->
        <div class="collab-footer">
          <p class="save-hint">Vider un champ puis sauvegarder supprime l'objectif.</p>
          <Button
            label="Sauvegarder les objectifs"
            icon="pi pi-save"
            :loading="saving === collab.user.id"
            :disabled="!exerciceId"
            :aria-label="`Sauvegarder les objectifs de ${collab.user.name}`"
            @click="sauvegarder(collab)"
          />
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
.empty-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 3rem;
  color: var(--p-text-muted-color);
}

.empty-state i { font-size: 2.5rem; opacity: 0.4; }

.error-state i { font-size: 2.5rem; color: var(--p-red-500); }
.error-state p { margin: 0; }

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

.section-title {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
  margin-bottom: 0.75rem;
}

.kpi-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.kpi-row {
  display: grid;
  grid-template-columns: 200px 1fr;
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
  font-family: var(--ledge-ff-mono);
}

.kpi-pct-tag {
  font-family: var(--ledge-ff-mono);
}

.kpi-input {
  width: 130px;
}

.kpi-pct-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.kpi-no-objectif {
  padding-top: 0.25rem;
}

/* Stats indicateurs */
.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.stat-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  background: var(--p-surface-ground);
  border-radius: 6px;
}

.stat-icon {
  font-size: 1.1rem;
  color: var(--p-text-muted-color);
  margin-top: 2px;
  flex-shrink: 0;
}

.stat-body {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.stat-label {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.stat-values {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.9rem;
  font-weight: 600;
}

.stat-ok { color: var(--p-green-500); }
.stat-warn { color: var(--p-red-500); }
.stat-sep { color: var(--p-text-muted-color); }

.stat-val {
  font-size: 0.95rem;
  color: var(--p-text-color);
}

.collab-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding-top: 1rem;
  margin-top: 0.75rem;
  border-top: 1px solid var(--p-surface-border);
}

.save-hint {
  margin: 0;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}

@media (max-width: 900px) {
  .kpi-row {
    grid-template-columns: 1fr;
  }

  .kpi-valeurs {
    grid-template-columns: 1fr 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .kpi-valeurs {
    grid-template-columns: 1fr;
  }
}
</style>
