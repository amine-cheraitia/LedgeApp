<script setup lang="ts">
import { onMounted } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import { useSettings } from '@/composables/useSettings'

const { settings, loading, saving, fetchSettings, saveSettings } = useSettings()

onMounted(fetchSettings)
</script>

<template>
  <div>
    <div class="page-header">
      <h2>Parametres</h2>
    </div>

    <div v-if="loading" class="loading">Chargement...</div>

    <form v-else @submit.prevent="saveSettings" class="settings-form">
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

.loading {
  padding: 2rem;
  text-align: center;
  color: var(--p-text-muted-color);
}
</style>
