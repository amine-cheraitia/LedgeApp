<script setup lang="ts">
// Editeur des objectifs annuels d'un collaborateur (extrait de l'ancienne page
// KpiObjectifsPage) : diff entre la saisie et les objectifs existants, avec
// confirmation avant tout ecrasement ou toute suppression.
import { ref, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import { statsApi, type KpiObjectifType, type KpiObjectifValeur } from '@/api/modules/stats'

const props = defineProps<{
  user: { id: number; name: string }
  objectifs: Partial<Record<KpiObjectifType, KpiObjectifValeur>>
  exerciceId: number | null
}>()

const emit = defineEmits<{ saved: [] }>()

const toast = useToast()
const confirm = useConfirm()

// KPIs avec objectif saisissable (cible annuelle)
const typesObjectif: { key: KpiObjectifType; label: string; icon: string }[] = [
  { key: 'ca_ht', label: 'CA HT cible (DA)', icon: 'pi pi-chart-line' },
  { key: 'missions_cloturees', label: 'Missions clôturées', icon: 'pi pi-briefcase' },
  { key: 'taches_terminees', label: 'Tâches terminées', icon: 'pi pi-check-circle' },
]

const saving = ref(false)

// Valeurs en cours d'edition, une par type d'objectif
const editing = ref<Record<KpiObjectifType, number | null>>({
  ca_ht: null,
  missions_cloturees: null,
  taches_terminees: null,
})

function remplirEditing(): void {
  for (const t of typesObjectif) {
    editing.value[t.key] = props.objectifs[t.key]?.valeur ?? null
  }
}

// Reinitialise la saisie a chaque changement de collaborateur ou de donnees
// (nouvelle selection, refetch apres sauvegarde).
watch(() => [props.user.id, props.objectifs], remplirEditing, { immediate: true })

interface DiffOperation {
  type: KpiObjectifType
  action: 'upsert' | 'delete'
  valeur?: number
  id?: number
  ecrase: boolean
}

// Diff par type entre la saisie en cours et les objectifs existants
function calculerDiff(): DiffOperation[] {
  const diffs: DiffOperation[] = []
  for (const t of typesObjectif) {
    const existant = props.objectifs[t.key]
    const saisie = editing.value[t.key]

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

async function executerDiff(diffs: DiffOperation[]): Promise<void> {
  if (!props.exerciceId) return
  saving.value = true
  try {
    const resultats = await Promise.allSettled(
      diffs.map((d) =>
        d.action === 'delete'
          ? statsApi.deleteKpiObjectif(d.id!)
          : statsApi.upsertKpiObjectif({
              user_id: props.user.id,
              exercice_id: props.exerciceId!,
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
    saving.value = false
    // Le parent refetch les stats (realise + objectifs a jour) via l'evenement.
    emit('saved')
  }
}

function sauvegarder(): void {
  if (!props.exerciceId) return

  const diffs = calculerDiff()
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
      message: `${parts.join(', ')} pour ${props.user.name}.`,
      header: 'Confirmation',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Confirmer',
      rejectLabel: 'Annuler',
      accept: () => executerDiff(diffs),
    })
    return
  }

  executerDiff(diffs)
}
</script>

<template>
  <div class="objectifs-editor">
    <div class="section-title">Objectifs annuels</div>
    <div class="objectifs-grid">
      <div v-for="type in typesObjectif" :key="type.key" class="objectif-row">
        <label :for="`obj-${user.id}-${type.key}`" class="objectif-label">
          <i :class="type.icon" aria-hidden="true"></i>
          <span>{{ type.label }}</span>
        </label>
        <InputNumber
          :id="`obj-${user.id}-${type.key}`"
          v-model="editing[type.key]"
          :min="0"
          :max-fraction-digits="0"
          :suffix="type.key === 'ca_ht' ? ' DA' : undefined"
          :aria-label="`Objectif ${type.label} pour ${user.name}`"
          class="objectif-input"
          :disabled="!exerciceId"
        />
      </div>
    </div>

    <div class="editor-footer">
      <p class="save-hint">Vider un champ puis sauvegarder supprime l'objectif.</p>
      <Button
        label="Sauvegarder les objectifs"
        icon="pi pi-save"
        :loading="saving"
        :disabled="!exerciceId"
        :aria-label="`Sauvegarder les objectifs de ${user.name}`"
        @click="sauvegarder"
      />
    </div>
  </div>
</template>

<style scoped>
.objectifs-editor {
  padding-top: 1rem;
  margin-top: 1rem;
  border-top: 1px solid var(--p-surface-border);
}

.section-title {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
  margin-bottom: 0.75rem;
}

.objectifs-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.objectif-row {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 220px;
}

.objectif-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--p-text-color);
}

.objectif-input {
  width: 100%;
}

.editor-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding-top: 1.25rem;
  margin-top: 1rem;
  border-top: 1px solid var(--p-surface-border);
}

.save-hint {
  margin: 0;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}

@media (max-width: 600px) {
  .objectif-row {
    min-width: 100%;
  }
}
</style>
