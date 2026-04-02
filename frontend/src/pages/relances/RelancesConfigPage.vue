<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import { settingsApi } from '@/api/modules/settings'
import type { Setting } from '@/types'

const toast = useToast()
const settings = ref<Setting[]>([])
const loading = ref(false)
const saving = ref(false)

const VARIABLES_HELP = '{{client}}, {{montant}}, {{numero_facture}}, {{echeance}}'

async function fetchSettings() {
  loading.value = true
  try {
    const res = await settingsApi.getAll()
    settings.value = res.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les paramètres', life: 3000 })
  } finally {
    loading.value = false
  }
}

function getVal(key: string): string {
  return settings.value.find(s => s.key === key)?.value ?? ''
}

function setVal(key: string, val: string) {
  const s = settings.value.find(s => s.key === key)
  if (s) s.value = val
}

const jours1 = computed({
  get: () => Number(getVal('relance_niveau1_jours')) || 15,
  set: (v: number) => setVal('relance_niveau1_jours', String(v)),
})
const jours2 = computed({
  get: () => Number(getVal('relance_niveau2_jours')) || 30,
  set: (v: number) => setVal('relance_niveau2_jours', String(v)),
})
const jours3 = computed({
  get: () => Number(getVal('relance_niveau3_jours')) || 45,
  set: (v: number) => setVal('relance_niveau3_jours', String(v)),
})
const template1 = computed({
  get: () => getVal('relance_template_n1'),
  set: (v: string) => setVal('relance_template_n1', v),
})
const template2 = computed({
  get: () => getVal('relance_template_n2'),
  set: (v: string) => setVal('relance_template_n2', v),
})
const template3 = computed({
  get: () => getVal('relance_template_n3'),
  set: (v: string) => setVal('relance_template_n3', v),
})

async function save() {
  saving.value = true
  try {
    const payload = settings.value.map(s => ({ key: s.key, value: s.value }))
    await settingsApi.update(payload)
    toast.add({ severity: 'success', summary: 'Enregistré', detail: 'Paramètres de relance mis à jour', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de sauvegarder', life: 3000 })
  } finally {
    saving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div>
    <div class="flex align-items-center justify-content-between mb-4">
      <div>
        <h1 class="text-2xl font-bold m-0">Configuration des relances</h1>
        <p class="text-color-secondary mt-1 mb-0">Délais et templates des relances automatiques</p>
      </div>
    </div>

    <div v-if="loading" class="text-center py-6 text-color-secondary">Chargement...</div>

    <form v-else @submit.prevent="save">
      <!-- Delais -->
      <div class="card mb-4">
        <h2 class="text-lg font-semibold mb-3">Délais de déclenchement</h2>
        <p class="text-color-secondary text-sm mb-3">
          Nombre de jours après la date d'échéance avant l'envoi automatique de chaque niveau.
        </p>
        <div class="grid">
          <div class="col-12 md:col-4">
            <label for="jours1" class="font-semibold block mb-1">Niveau 1 — Rappel (jours)</label>
            <InputNumber
              id="jours1"
              v-model="jours1"
              :min="1"
              :max="365"
              class="w-full"
              aria-label="Délai relance niveau 1 en jours"
            />
          </div>
          <div class="col-12 md:col-4">
            <label for="jours2" class="font-semibold block mb-1">Niveau 2 — Relance ferme (jours)</label>
            <InputNumber
              id="jours2"
              v-model="jours2"
              :min="1"
              :max="365"
              class="w-full"
              aria-label="Délai relance niveau 2 en jours"
            />
          </div>
          <div class="col-12 md:col-4">
            <label for="jours3" class="font-semibold block mb-1">Niveau 3 — Mise en demeure (jours)</label>
            <InputNumber
              id="jours3"
              v-model="jours3"
              :min="1"
              :max="365"
              class="w-full"
              aria-label="Délai relance niveau 3 en jours"
            />
          </div>
        </div>
      </div>

      <!-- Templates -->
      <div class="card mb-4">
        <h2 class="text-lg font-semibold mb-1">Templates des messages</h2>
        <p class="text-color-secondary text-sm mb-3">
          Variables disponibles : <code>{{ VARIABLES_HELP }}</code>
        </p>
        <TabView>
          <TabPanel value="n1" header="Niveau 1 — Rappel">
            <div class="mt-2">
              <label for="tpl1" class="font-semibold block mb-1">Message du rappel</label>
              <Textarea
                id="tpl1"
                v-model="template1"
                rows="8"
                class="w-full"
                aria-label="Template du message de relance niveau 1"
              />
            </div>
          </TabPanel>
          <TabPanel value="n2" header="Niveau 2 — Relance ferme">
            <div class="mt-2">
              <label for="tpl2" class="font-semibold block mb-1">Message de relance ferme</label>
              <Textarea
                id="tpl2"
                v-model="template2"
                rows="8"
                class="w-full"
                aria-label="Template du message de relance niveau 2"
              />
            </div>
          </TabPanel>
          <TabPanel value="n3" header="Niveau 3 — Mise en demeure">
            <div class="mt-2">
              <label for="tpl3" class="font-semibold block mb-1">Message de mise en demeure</label>
              <Textarea
                id="tpl3"
                v-model="template3"
                rows="8"
                class="w-full"
                aria-label="Template du message de relance niveau 3"
              />
            </div>
          </TabPanel>
        </TabView>
      </div>

      <div class="flex justify-content-end">
        <Button
          type="submit"
          label="Enregistrer"
          icon="pi pi-save"
          :loading="saving"
          aria-label="Enregistrer la configuration des relances"
        />
      </div>
    </form>
  </div>
</template>
