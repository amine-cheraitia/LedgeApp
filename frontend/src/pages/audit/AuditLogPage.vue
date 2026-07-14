<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import { auditApi } from '@/api/modules/audit'
import { toIsoDate } from '@/utils/date'
import type { Activity, AuditEvent } from '@/types'

const toast = useToast()

const activites = ref<Activity[]>([])
const loading = ref(false)
const totalRecords = ref(0)
const page = ref(1)
const perPage = 15

const filtres = reactive({
  subject_type: undefined as string | undefined,
  event: undefined as AuditEvent | undefined,
  date_debut: null as Date | null,
  date_fin: null as Date | null,
})

const entiteOptions = [
  { label: 'Facture', value: 'Facture' },
  { label: 'Avoir', value: 'Avoir' },
  { label: 'Paiement', value: 'Paiement' },
  { label: 'Devis', value: 'Devis' },
  { label: 'Entreprise', value: 'Entreprise' },
  { label: 'Utilisateur', value: 'User' },
  { label: 'Parametre', value: 'Setting' },
]

const eventOptions: { label: string; value: AuditEvent }[] = [
  { label: 'Creation', value: 'created' },
  { label: 'Modification', value: 'updated' },
  { label: 'Suppression', value: 'deleted' },
]

async function fetchActivites() {
  loading.value = true
  try {
    const res = await auditApi.getAll({
      page: page.value,
      per_page: perPage,
      subject_type: filtres.subject_type,
      event: filtres.event,
      // toIsoDate (utils/date) formate en LOCAL : pas de bascule J-1 via UTC
      date_debut: toIsoDate(filtres.date_debut) ?? undefined,
      date_fin: toIsoDate(filtres.date_fin) ?? undefined,
    })
    activites.value = res.data
    totalRecords.value = res.meta?.total ?? res.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de charger le journal d'audit.", life: 3000 })
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchActivites()
}

watch(filtres, () => {
  page.value = 1
  fetchActivites()
})

function eventLabel(event: AuditEvent | null): string {
  const map: Record<string, string> = { created: 'Creation', updated: 'Modification', deleted: 'Suppression' }
  return event ? map[event] ?? event : '—'
}

function eventColor(event: AuditEvent | null): 'success' | 'info' | 'danger' | 'secondary' {
  const map: Record<string, 'success' | 'info' | 'danger'> = { created: 'success', updated: 'info', deleted: 'danger' }
  return event ? map[event] ?? 'secondary' : 'secondary'
}

function entiteLabel(type: string): string {
  return entiteOptions.find(o => o.value === type)?.label ?? type
}

function formatDateTime(d: string): string {
  return new Date(d).toLocaleString('fr-FR')
}

// ---------- Dialog detail ----------
const detailVisible = ref(false)
const detailActivite = ref<Activity | null>(null)

interface ChangeRow {
  champ: string
  avant: unknown
  apres: unknown
}

const detailRows = computed<ChangeRow[]>(() => {
  if (!detailActivite.value) return []
  const { attributes, old } = detailActivite.value.changes
  const champs = new Set([...Object.keys(attributes ?? {}), ...Object.keys(old ?? {})])
  return [...champs].map(champ => ({
    champ,
    avant: old?.[champ] ?? null,
    apres: attributes?.[champ] ?? null,
  }))
})

function formatValeur(v: unknown): string {
  if (v === null || v === undefined || v === '') return '—'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function openDetail(activite: Activity) {
  detailActivite.value = activite
  detailVisible.value = true
}

onMounted(fetchActivites)
</script>

<template>
  <section aria-labelledby="audit-title">
    <div class="page-header">
      <h1 id="audit-title">Journal d'audit</h1>
    </div>

    <p class="page-intro">
      Tracabilite des actions sur les entites sensibles (factures, avoirs, paiements, devis, entreprises,
      utilisateurs, parametres) : qui a fait quoi, et quand.
    </p>

    <div class="page-toolbar" role="search" aria-label="Filtres du journal d'audit">
      <div class="filter-field">
        <label for="f-entite" class="sr-only">Filtrer par entite</label>
        <Select
          id="f-entite"
          v-model="filtres.subject_type"
          :options="entiteOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Toutes les entites"
          showClear
          style="width: 12rem"
        />
      </div>
      <div class="filter-field">
        <label for="f-action" class="sr-only">Filtrer par action</label>
        <Select
          id="f-action"
          v-model="filtres.event"
          :options="eventOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Toutes les actions"
          showClear
          style="width: 11rem"
        />
      </div>
      <div class="filter-field">
        <label for="f-debut" class="sr-only">Date de debut</label>
        <DatePicker id="f-debut" v-model="filtres.date_debut" dateFormat="dd/mm/yy" placeholder="Du..." showButtonBar />
      </div>
      <div class="filter-field">
        <label for="f-fin" class="sr-only">Date de fin</label>
        <DatePicker id="f-fin" v-model="filtres.date_fin" dateFormat="dd/mm/yy" placeholder="Au..." showButtonBar />
      </div>
    </div>

    <DataTable aria-label="Journal d'audit"
      :value="activites"
      :loading="loading"
      :paginator="true"
      :rows="perPage"
      :totalRecords="totalRecords"
      :lazy="true"
      @page="onPage"
      dataKey="id"
      stripedRows
    >
      <template #empty>Aucune action enregistree pour ces criteres.</template>

      <Column field="created_at" header="Date">
        <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
      </Column>
      <Column header="Utilisateur">
        <template #body="{ data }">{{ data.causer?.name ?? 'Systeme' }}</template>
      </Column>
      <Column header="Action">
        <template #body="{ data }">
          <Tag :value="eventLabel(data.event)" :severity="eventColor(data.event)" />
        </template>
      </Column>
      <Column header="Entite">
        <template #body="{ data }">
          {{ entiteLabel(data.subject_type) }}
          <span v-if="data.subject_id" class="entite-id">#{{ data.subject_id }}</span>
        </template>
      </Column>
      <Column header="Detail" style="width: 6rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-eye"
            text
            rounded
            severity="secondary"
            :aria-label="`Voir le detail de l'action du ${formatDateTime(data.created_at)}`"
            v-tooltip.top="'Voir le detail'"
            @click="openDetail(data)"
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog detail -->
    <Dialog
      v-model:visible="detailVisible"
      header="Detail de l'action"
      :modal="true"
      :style="{ width: '40rem', maxWidth: '95vw' }"
      :breakpoints="{ '640px': '95vw' }"
      :draggable="false"
    >
      <div v-if="detailActivite" class="detail-content">
        <div class="detail-recap">
          <div class="recap-row">
            <span class="recap-label">Date</span>
            <span>{{ formatDateTime(detailActivite.created_at) }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Utilisateur</span>
            <span>{{ detailActivite.causer?.name ?? 'Systeme' }}</span>
          </div>
          <div class="recap-row">
            <span class="recap-label">Action</span>
            <Tag :value="eventLabel(detailActivite.event)" :severity="eventColor(detailActivite.event)" />
          </div>
          <div class="recap-row">
            <span class="recap-label">Entite</span>
            <span>{{ entiteLabel(detailActivite.subject_type) }}
              <span v-if="detailActivite.subject_id" class="entite-id">#{{ detailActivite.subject_id }}</span>
            </span>
          </div>
        </div>

        <h2 class="detail-subtitle">Champs modifies</h2>
        <DataTable aria-label="Champs modifiés" v-if="detailRows.length" :value="detailRows" dataKey="champ" stripedRows class="detail-table">
          <Column field="champ" header="Champ" />
          <Column header="Avant">
            <template #body="{ data }"><span class="val-avant">{{ formatValeur(data.avant) }}</span></template>
          </Column>
          <Column header="Apres">
            <template #body="{ data }"><span class="val-apres">{{ formatValeur(data.apres) }}</span></template>
          </Column>
        </DataTable>
        <p v-else class="hint">Aucun detail de champ disponible pour cette action.</p>
      </div>
    </Dialog>
  </section>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}
.page-intro {
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
  margin: 0 0 1rem;
  max-width: 60rem;
}
.page-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.filter-field { display: flex; flex-direction: column; }
.entite-id { color: var(--p-text-muted-color); font-size: 0.8rem; }
.detail-content { display: flex; flex-direction: column; gap: 1rem; }
.detail-recap {
  background: var(--p-surface-ground);
  border: 1px solid var(--p-surface-border);
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.recap-row { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.recap-label { font-size: 0.8rem; color: var(--p-text-muted-color); }
.detail-subtitle { font-size: 0.95rem; font-weight: 600; margin: 0; }
.val-avant { color: var(--p-text-muted-color); text-decoration: line-through; }
.val-apres { color: var(--p-text-color); font-weight: 500; }
.hint { color: var(--p-text-muted-color); font-size: 0.85rem; }

@media (max-width: 640px) {
  .page-toolbar { flex-direction: column; }
  .filter-field :deep(.p-select), .filter-field :deep(.p-datepicker) { width: 100% !important; }
}
</style>
