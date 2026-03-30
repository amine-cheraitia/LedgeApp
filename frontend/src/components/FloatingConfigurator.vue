<script setup lang="ts">
import AppConfigurator from '@/layout/AppConfigurator.vue'
import { useLayout } from '@/layout/composables/layout'

const { toggleDarkMode, isDarkTheme } = useLayout()
</script>

<template>
  <div class="fixed flex gap-4 top-8 right-8">
    <Button type="button" @click="toggleDarkMode" rounded :icon="isDarkTheme ? 'pi pi-moon' : 'pi pi-sun'" severity="secondary" aria-label="Basculer le mode sombre" />
    <div class="relative">
      <Button
        icon="pi pi-palette"
        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
        type="button"
        rounded
        aria-label="Configuration du theme"
      />
      <AppConfigurator />
    </div>
  </div>
</template>
