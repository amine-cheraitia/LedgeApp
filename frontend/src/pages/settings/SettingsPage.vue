<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import api from '@/api/client'
import type { Setting } from '@/types'

const toast = useToast()
const settings = ref<Setting[]>([])
const loading = ref(false)
const saving = ref(false)

async function fetchSettings() {
  loading.value = true
  try {
    const { data } = await api.get('/settings')
    settings.value = data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les paramètres.', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    await api.put('/settings', {
      settings: settings.value.map(s => ({ key: s.key, value: s.value })),
    })
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Paramètres enregistrés.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Échec de la sauvegarde.', life: 3000 })
  } finally {
    saving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Paramètres</h2>
    </div>

    <form @submit.prevent="saveSettings" class="settings-form">
      <div v-for="setting in settings" :key="setting.key" class="form-field">
        <label :for="'setting-' + setting.key">
          {{ setting.label || setting.key }}
        </label>
        <InputText
          :id="'setting-' + setting.key"
          v-model="setting.value"
          fluid
        />
      </div>

      <Button
        type="submit"
        label="Enregistrer"
        icon="pi pi-save"
        :loading="saving"
      />
    </form>
  </div>
</template>

<style scoped>
.page-header { margin-bottom: 1.5rem; }

.settings-form {
  max-width: 32rem;
}

.form-field {
  margin-bottom: 1rem;
}

.form-field label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.375rem;
}
</style>
