<script setup lang="ts">
import { useLayout } from '@/layout/composables/layout'
import LedgeLogo from '@/components/LedgeLogo.vue'

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout()
</script>

<template>
  <header class="layout-topbar" role="banner">
    <div class="layout-topbar-logo-container">
      <button
        class="layout-menu-button layout-topbar-action"
        aria-label="Ouvrir le menu"
        @click="toggleMenu"
      >
        <i class="pi pi-bars"></i>
      </button>
      <router-link to="/" class="layout-topbar-logo lg:hidden" aria-label="Ledge — accueil">
        <LedgeLogo :size="28" with-wordmark />
      </router-link>
    </div>

    <div class="layout-topbar-actions">
      <div class="layout-config-menu">
        <button
          type="button"
          class="layout-topbar-action layout-topbar-theme-toggle"
          :aria-label="isDarkTheme ? 'Passer en mode clair' : 'Passer en mode sombre'"
          @click="toggleDarkMode"
        >
          <i :class="['pi', isDarkTheme ? 'pi-moon' : 'pi-sun']"></i>
        </button>
      </div>
    </div>
  </header>
</template>
