<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import { entreprisesApi } from '@/api/modules/entreprises'
import { missionsApi } from '@/api/modules/missions'
import { devisApi } from '@/api/modules/devis'
import { facturesApi } from '@/api/modules/factures'
import { useExercices } from '@/composables/useExercices'
import type { Entreprise, Mission, Devis, Facture } from '@/types'

type TagSeverity = 'info' | 'success' | 'warn' | 'danger' | 'secondary' | 'contrast'

const route = useRoute()
const router = useRouter()
const entrepriseId = Number(route.params.id)

// --- Data ---
const entreprise = ref<Entreprise | null>(null)
const missions = ref<Mission[]>([])
const devisList = ref<Devis[]>([])
const factures = ref<Facture[]>([])
// Factures tous exercices confondus — utilisées uniquement pour les KPIs
const facturesKpi = ref<Facture[]>([])
const loadingEntreprise = ref(false)
const loadingMissions = ref(false)
const loadingDevis = ref(false)
const loadingFactures = ref(false)

// --- Exercice filter ---
const { exercices, fetchExercices } = useExercices()
const exerciceSelectionne = ref<number | null>(null)

// --- KPIs calculés (sur tous les exercices, pas seulement le courant) ---
// CA = somme des montants HT des factures FF (le CA est toujours exprimé HT)
const totalFacture = computed(() =>
  facturesKpi.value
    .filter(f => f.type === 'FF')
    .reduce((sum, f) => sum + (Number(f.montant_ht) || 0), 0)
)

// Impayés = créances clients restantes TTC (base de recouvrement)
const totalImpaye = computed(() =>
  facturesKpi.value
    .filter(f => f.statut_paiement !== 'solde' && f.type === 'FF')
    .reduce((sum, f) => sum + (Number(f.montant_restant) || 0), 0)
)

const missionsActives = computed(() =>
  missions.value.filter(m => m.statut === 'en_cours').length
)

const nbFacturesImpayees = computed(() =>
  facturesKpi.value.filter(f => f.statut_paiement !== 'solde' && f.type === 'FF').length
)

const exerciceCourantAnnee = computed<number | null>(() => {
  const courant = exercices.value.find(e => e.statut === 'ouvert')
  return courant?.annee ?? null
})

const caParAnnee = computed<Record<number, number>>(() => {
  const acc: Record<number, number> = {}
  for (const f of facturesKpi.value) {
    if (f.type !== 'FF' || !f.date_facture) continue
    const annee = new Date(f.date_facture).getFullYear()
    acc[annee] = (acc[annee] ?? 0) + (Number(f.montant_ht) || 0)
  }
  return acc
})

type Trend = { pct: number; direction: 'up' | 'down' | 'stable' }
const variationCa = computed<Trend | null>(() => {
  const annee = exerciceCourantAnnee.value
  if (!annee) return null
  const courant = caParAnnee.value[annee] ?? 0
  const precedent = caParAnnee.value[annee - 1] ?? 0
  if (!precedent) return null
  const pct = ((courant - precedent) / precedent) * 100
  return {
    pct: Math.abs(Math.round(pct)),
    direction: pct > 1 ? 'up' : pct < -1 ? 'down' : 'stable',
  }
})

// --- Fetch ---
async function fetchEntreprise() {
  loadingEntreprise.value = true
  try {
    const res = await entreprisesApi.getOne(entrepriseId)
    entreprise.value = res.data
  } finally {
    loadingEntreprise.value = false
  }
}

async function fetchMissions() {
  loadingMissions.value = true
  try {
    const res = await missionsApi.getAll({
      entreprise_id: entrepriseId,
      exercice_id: exerciceSelectionne.value ?? undefined,
      per_page: 100,
    })
    missions.value = res.data
  } finally {
    loadingMissions.value = false
  }
}

async function fetchDevis() {
  loadingDevis.value = true
  try {
    const res = await devisApi.getAll({
      entreprise_id: entrepriseId,
      exercice_id: exerciceSelectionne.value ?? undefined,
      per_page: 100,
    })
    devisList.value = res.data
  } finally {
    loadingDevis.value = false
  }
}

async function fetchFactures() {
  loadingFactures.value = true
  try {
    const res = await facturesApi.getAll({
      entreprise_id: entrepriseId,
      exercice_id: exerciceSelectionne.value ?? undefined,
      per_page: 100,
    })
    factures.value = res.data
  } finally {
    loadingFactures.value = false
  }
}

// Fetch sans filtre exercice — pour les KPIs CA total / impayés tous exercices
async function fetchFacturesKpi() {
  try {
    const res = await facturesApi.getAll({
      entreprise_id: entrepriseId,
      per_page: 500,
    })
    facturesKpi.value = res.data
  } catch {
    facturesKpi.value = []
  }
}

async function applyExercice(id: number | null) {
  exerciceSelectionne.value = id
  await Promise.all([fetchMissions(), fetchDevis(), fetchFactures()])
}

// --- Formatters ---
function formatMontant(v: number | string | null | undefined) {
  const n = Number(v)
  if (isNaN(n)) return '0 DA'
  return n.toLocaleString('fr-FR') + ' DA'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function statutMissionColor(s: string): TagSeverity {
  const map: Record<string, TagSeverity> = { en_cours: 'info', terminee: 'success', suspendue: 'warn', annulee: 'danger' }
  return map[s] ?? 'secondary'
}

function statutDevisColor(s: string): TagSeverity {
  const map: Record<string, TagSeverity> = { brouillon: 'secondary', envoye: 'info', accepte: 'success', refuse: 'danger', expire: 'warn' }
  return map[s] ?? 'secondary'
}

function statutFactureColor(s: string): TagSeverity {
  const map: Record<string, TagSeverity> = { en_attente: 'warn', partiel: 'info', solde: 'success' }
  return map[s] ?? 'secondary'
}

function statutEntrepriseColor(s: string): TagSeverity {
  const map: Record<string, TagSeverity> = { prospect: 'warn', client: 'success', ancien_client: 'secondary' }
  return map[s] ?? 'secondary'
}

onMounted(async () => {
  await Promise.all([fetchEntreprise(), fetchExercices(), fetchFacturesKpi()])
  // pré-sélectionner l'exercice courant
  const courant = exercices.value.find(e => e.statut === 'ouvert')
  if (courant) {
    exerciceSelectionne.value = courant.id
  }
  await Promise.all([fetchMissions(), fetchDevis(), fetchFactures()])
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <Button
          icon="pi pi-arrow-left"
          text
          severity="secondary"
          aria-label="Retour a la liste"
          @click="router.push('/entreprises')"
        />
        <div v-if="entreprise">
          <h1>{{ entreprise.raison_sociale }}</h1>
          <Tag
            :value="entreprise.statut"
            :severity="statutEntrepriseColor(entreprise.statut)"
            class="mt-1"
          />
        </div>
        <div v-else class="skeleton-title" />
      </div>

      <div class="header-kpis" v-if="entreprise">
        <div
          v-if="totalImpaye > 0"
          class="kpi-card kpi-warning"
          role="status"
          :aria-label="`Montant impayé : ${formatMontant(totalImpaye)} sur ${nbFacturesImpayees} facture${nbFacturesImpayees > 1 ? 's' : ''}`"
        >
          <div class="kpi-icon" aria-hidden="true">
            <i class="pi pi-exclamation-triangle" />
          </div>
          <div class="kpi-content">
            <span class="kpi-label">Impayé</span>
            <span class="kpi-value">{{ formatMontant(totalImpaye) }}</span>
            <span class="kpi-sub">{{ nbFacturesImpayees }} facture{{ nbFacturesImpayees > 1 ? 's' : '' }}</span>
          </div>
        </div>

        <div
          class="kpi-card"
          role="status"
          :aria-label="`Chiffre d'affaires total HT : ${formatMontant(totalFacture)}`"
        >
          <div class="kpi-icon" aria-hidden="true">
            <i class="pi pi-chart-line" />
          </div>
          <div class="kpi-content">
            <span class="kpi-label">CA total</span>
            <span class="kpi-value">{{ formatMontant(totalFacture) }}</span>
            <span
              v-if="variationCa"
              class="kpi-sub"
              :class="`kpi-trend--${variationCa.direction}`"
            >
              <i
                :class="['pi', variationCa.direction === 'up' ? 'pi-arrow-up-right' : variationCa.direction === 'down' ? 'pi-arrow-down-right' : 'pi-minus']"
                aria-hidden="true"
              />
              {{ variationCa.pct }}% vs {{ (exerciceCourantAnnee ?? 0) - 1 }}
            </span>
            <span v-else class="kpi-sub">Tous exercices, HT</span>
          </div>
        </div>

        <div
          class="kpi-card"
          role="status"
          :aria-label="`Missions actives : ${missionsActives}`"
        >
          <div class="kpi-icon" aria-hidden="true">
            <i class="pi pi-briefcase" />
          </div>
          <div class="kpi-content">
            <span class="kpi-label">Missions actives</span>
            <span class="kpi-value">{{ missionsActives }}</span>
            <span class="kpi-sub">{{ exerciceCourantAnnee ? `Exercice ${exerciceCourantAnnee}` : 'Exercice courant' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtre exercice -->
    <div class="exercice-bar">
      <label for="detail-exercice" class="exercice-label">Exercice :</label>
      <Select
        id="detail-exercice"
        v-model="exerciceSelectionne"
        :options="[{ id: null, annee: 'Tous' }, ...exercices]"
        optionLabel="annee"
        optionValue="id"
        placeholder="Tous les exercices"
        aria-label="Filtrer par exercice"
        style="min-width: 10rem"
        @change="applyExercice(exerciceSelectionne)"
      />
    </div>

    <div class="detail-layout">
      <!-- Colonne infos -->
      <aside class="info-panel" v-if="entreprise">
        <section class="info-section" aria-labelledby="section-coordonnees">
          <h2 id="section-coordonnees" class="section-title">Coordonnees</h2>
          <dl class="info-list">
            <div class="info-row" v-if="entreprise.nif">
              <dt>NIF</dt><dd>{{ entreprise.nif }}</dd>
            </div>
            <div class="info-row" v-if="entreprise.nis">
              <dt>NIS</dt><dd>{{ entreprise.nis }}</dd>
            </div>
            <div class="info-row">
              <dt>Regime</dt><dd>{{ entreprise.regime_fiscal }}</dd>
            </div>
            <div class="info-row">
              <dt>Categorie</dt><dd>{{ entreprise.categorie }}</dd>
            </div>
            <div class="info-row" v-if="entreprise.email">
              <dt>Email</dt><dd>{{ entreprise.email }}</dd>
            </div>
            <div class="info-row" v-if="entreprise.telephone">
              <dt>Tel</dt><dd>{{ entreprise.telephone }}</dd>
            </div>
            <div class="info-row" v-if="entreprise.ville || entreprise.wilaya">
              <dt>Ville</dt><dd>{{ [entreprise.ville, entreprise.wilaya].filter(Boolean).join(', ') }}</dd>
            </div>
            <div class="info-row" v-if="entreprise.adresse">
              <dt>Adresse</dt><dd>{{ entreprise.adresse }}</dd>
            </div>
          </dl>
        </section>

        <section class="info-section" aria-labelledby="section-contacts" v-if="entreprise.contacts?.length">
          <h2 id="section-contacts" class="section-title">Contacts ({{ entreprise.contacts.length }})</h2>
          <ul class="contacts-list">
            <li v-for="c in entreprise.contacts" :key="c.id" class="contact-item">
              <div class="contact-name">
                {{ c.nom }}{{ c.prenom ? ' ' + c.prenom : '' }}
                <Tag v-if="c.est_principal" value="Principal" severity="info" class="ml-1" />
              </div>
              <div class="contact-detail" v-if="c.poste">{{ c.poste }}</div>
              <div class="contact-detail" v-if="c.email">{{ c.email }}</div>
              <div class="contact-detail" v-if="c.telephone">{{ c.telephone }}</div>
            </li>
          </ul>
        </section>

        <section class="info-section" v-if="entreprise.notes">
          <h2 class="section-title">Notes</h2>
          <p class="notes-text">{{ entreprise.notes }}</p>
        </section>
      </aside>

      <!-- Onglets -->
      <main class="tabs-area">
        <Tabs value="missions">
          <TabList>
            <Tab value="missions">
              Missions
              <span class="tab-badge">{{ missions.length }}</span>
            </Tab>
            <Tab value="devis">
              Devis
              <span class="tab-badge">{{ devisList.length }}</span>
            </Tab>
            <Tab value="factures">
              Factures
              <span class="tab-badge">{{ factures.length }}</span>
            </Tab>
          </TabList>

          <TabPanels>
            <!-- Missions -->
            <TabPanel value="missions">
              <DataTable
                :value="missions"
                :loading="loadingMissions"
                dataKey="id"
                stripedRows
                size="small"
              >
                <Column field="reference" header="Reference">
                  <template #body="{ data }">
                    <a
                      :href="`/missions/${data.id}`"
                      class="link"
                      aria-label="Voir la mission"
                      @click.prevent="router.push(`/missions/${data.id}`)"
                    >{{ data.reference }}</a>
                  </template>
                </Column>
                <Column field="prestation.designation" header="Prestation" />
                <Column header="Prix HT" style="width: 10rem">
                  <template #body="{ data }">{{ formatMontant(data.prix_ht) }}</template>
                </Column>
                <Column header="Debut" style="width: 8rem">
                  <template #body="{ data }">{{ formatDate(data.date_debut) }}</template>
                </Column>
                <Column header="Fin" style="width: 8rem">
                  <template #body="{ data }">{{ formatDate(data.date_fin) }}</template>
                </Column>
                <Column header="Statut" style="width: 8rem">
                  <template #body="{ data }">
                    <Tag :value="data.statut" :severity="statutMissionColor(data.statut)" />
                  </template>
                </Column>
              </DataTable>
              <p v-if="missions.length === 0 && !loadingMissions" class="empty-msg">
                Aucune mission pour cet exercice.
              </p>
            </TabPanel>

            <!-- Devis -->
            <TabPanel value="devis">
              <DataTable
                :value="devisList"
                :loading="loadingDevis"
                dataKey="id"
                stripedRows
                size="small"
              >
                <Column field="numero" header="Numero" style="width: 9rem" />
                <Column header="Prestation">
                  <template #body="{ data }">{{ data.prestation?.designation ?? '—' }}</template>
                </Column>
                <Column header="Montant TTC" style="width: 10rem">
                  <template #body="{ data }">{{ formatMontant(data.montant_ttc) }}</template>
                </Column>
                <Column header="Date" style="width: 8rem">
                  <template #body="{ data }">{{ formatDate(data.date_devis) }}</template>
                </Column>
                <Column header="Validite" style="width: 8rem">
                  <template #body="{ data }">{{ formatDate(data.date_validite) }}</template>
                </Column>
                <Column header="Statut" style="width: 8rem">
                  <template #body="{ data }">
                    <Tag :value="data.statut" :severity="statutDevisColor(data.statut)" />
                  </template>
                </Column>
              </DataTable>
              <p v-if="devisList.length === 0 && !loadingDevis" class="empty-msg">
                Aucun devis pour cet exercice.
              </p>
            </TabPanel>

            <!-- Factures -->
            <TabPanel value="factures">
              <DataTable
                :value="factures"
                :loading="loadingFactures"
                dataKey="id"
                stripedRows
                size="small"
              >
                <Column field="numero" header="Numero" style="width: 9rem" />
                <Column header="Montant TTC" style="width: 10rem">
                  <template #body="{ data }">{{ formatMontant(data.montant_ttc) }}</template>
                </Column>
                <Column header="Date" style="width: 8rem">
                  <template #body="{ data }">{{ formatDate(data.date_facture) }}</template>
                </Column>
                <Column header="Echeance" style="width: 8rem">
                  <template #body="{ data }">{{ formatDate(data.date_echeance) }}</template>
                </Column>
                <Column header="Restant" style="width: 10rem">
                  <template #body="{ data }">
                    <span :class="{ 'impaye': data.montant_restant > 0 }">
                      {{ formatMontant(data.montant_restant) }}
                    </span>
                  </template>
                </Column>
                <Column header="Statut" style="width: 8rem">
                  <template #body="{ data }">
                    <Tag :value="data.statut_paiement" :severity="statutFactureColor(data.statut_paiement)" />
                  </template>
                </Column>
              </DataTable>
              <p v-if="factures.length === 0 && !loadingFactures" class="empty-msg">
                Aucune facture pour cet exercice.
              </p>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </main>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.header-left {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}
.header-left h1 {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 600;
}
.skeleton-title {
  width: 16rem;
  height: 1.5rem;
  background: var(--p-surface-200, #e5e5e5);
  border-radius: 4px;
}
.header-kpis {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}
.kpi-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  background: transparent;
  border: 1px solid var(--p-content-border-color, rgba(128, 128, 128, 0.2));
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  min-width: 9rem;
  color: var(--p-text-color);
}
.kpi-card.kpi-warning {
  border-color: var(--p-orange-400, #fb923c);
  background: rgba(234, 88, 12, 0.08);
}
.kpi-icon {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  background: var(--p-surface-100, #f1f5f9);
  color: var(--p-primary-color);
  font-size: 1rem;
}
.app-dark .kpi-icon {
  background: rgba(255, 255, 255, 0.06);
}
.kpi-card.kpi-warning .kpi-icon {
  background: rgba(234, 88, 12, 0.15);
  color: #c2410c;
}
.kpi-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.kpi-label {
  font-size: 0.7rem;
  color: var(--p-text-muted-color, #888);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 600;
}
.kpi-value {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.15;
  margin-top: 0.15rem;
  color: var(--p-text-color);
}
.kpi-card.kpi-warning .kpi-value {
  color: #c2410c;
}
.kpi-sub {
  margin-top: 0.3rem;
  font-size: 0.7rem;
  color: var(--p-text-muted-color, #888);
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}
.kpi-trend--up { color: var(--p-green-600, #16a34a); }
.kpi-trend--down { color: var(--p-red-600, #dc2626); }
.kpi-trend--stable { color: var(--p-text-muted-color, #888); }

.exercice-bar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
}
.exercice-label { font-size: 0.875rem; font-weight: 500; }

.detail-layout {
  display: grid;
  grid-template-columns: 18rem 1fr;
  gap: 1.5rem;
  align-items: start;
}
.info-panel {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.info-section {
  background: transparent;
  border: 1px solid var(--p-content-border-color, rgba(128,128,128,0.2));
  border-radius: 0.5rem;
  padding: 1rem;
  color: var(--p-text-color);
}
.section-title {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color, #888);
  margin: 0 0 0.75rem 0;
}
.info-list { display: flex; flex-direction: column; gap: 0.35rem; }
.info-row {
  display: flex;
  gap: 0.5rem;
  font-size: 0.875rem;
  align-items: flex-start;
}
.info-row dt {
  color: var(--p-text-muted-color, #888);
  min-width: 5.5rem;
  flex-shrink: 0;
}
.info-row dd {
  font-weight: 500;
  margin: 0;
  min-width: 0;
  flex: 1;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.contacts-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem; }
.contact-item { font-size: 0.875rem; }
.contact-name { font-weight: 600; }
.contact-detail { color: var(--p-text-muted-color, #888); font-size: 0.8rem; }
.ml-1 { margin-left: 0.35rem; }

.notes-text { font-size: 0.875rem; white-space: pre-wrap; margin: 0; }

.tabs-area { min-width: 0; }
.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--p-surface-200, #e5e5e5);
  color: var(--p-text-color, #333);
  border-radius: 9999px;
  font-size: 0.7rem;
  font-weight: 700;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  margin-left: 0.35rem;
}
.empty-msg {
  text-align: center;
  padding: 2rem;
  color: var(--p-text-muted-color, #999);
  font-size: 0.875rem;
}
.link { color: var(--p-primary-color, #6366f1); text-decoration: none; }
.link:hover { text-decoration: underline; }
.impaye { color: var(--p-red-600, #dc2626); font-weight: 600; }
.mt-1 { margin-top: 0.25rem; }

@media (max-width: 900px) {
  .detail-layout { grid-template-columns: 1fr; }
  .header-kpis {
    width: 100%;
    flex-wrap: nowrap;
    gap: 0.5rem;
  }
  .kpi-card {
    flex: 1;
    min-width: 0;
    padding: 0.55rem 0.6rem;
    gap: 0.5rem;
  }
  .kpi-icon {
    width: 1.75rem;
    height: 1.75rem;
    font-size: 0.85rem;
  }
  .kpi-label { font-size: 0.625rem; letter-spacing: 0.02em; }
  .kpi-value { font-size: 1.05rem; }
  .kpi-sub { font-size: 0.625rem; margin-top: 0.15rem; }
}
</style>
