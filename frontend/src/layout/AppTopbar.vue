<script setup lang="ts">
import { useLayout } from '@/layout/composables/layout'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import AppConfigurator from './AppConfigurator.vue'

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout()
const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="layout-topbar">
    <div class="layout-topbar-logo-container">
      <button class="layout-menu-button layout-topbar-action" aria-label="Ouvrir le menu" @click="toggleMenu">
        <i class="pi pi-bars"></i>
      </button>
      <router-link to="/" class="layout-topbar-logo">
        <i class="pi pi-briefcase" style="font-size: 1.5rem; color: var(--p-primary-color)"></i>
        <span>LEDGE</span>
      </router-link>
    </div>

    <div class="layout-topbar-actions">
      <div class="layout-config-menu">
        <button type="button" class="layout-topbar-action" aria-label="Basculer le mode sombre" @click="toggleDarkMode">
          <i :class="['pi', isDarkTheme ? 'pi-moon' : 'pi-sun']"></i>
        </button>
        <div class="relative">
          <button
            v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
            type="button"
            class="layout-topbar-action layout-topbar-action-highlight"
            aria-label="Configuration du theme"
          >
            <i class="pi pi-palette"></i>
          </button>
          <AppConfigurator />
        </div>
      </div>

      <button
        class="layout-topbar-menu-button layout-topbar-action"
        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
        aria-label="Menu utilisateur"
      >
        <i class="pi pi-ellipsis-v"></i>
      </button>

      <div class="layout-topbar-menu hidden lg:block">
        <div class="layout-topbar-menu-content">
          <button type="button" class="layout-topbar-action" aria-label="Notifications">
            <i class="pi pi-bell"></i>
            <span>Notifications</span>
          </button>
          <button type="button" class="layout-topbar-action" aria-label="Profil">
            <i class="pi pi-user"></i>
            <span>{{ auth.user?.name }}</span>
          </button>
          <button type="button" class="layout-topbar-action" aria-label="Se deconnecter" @click="handleLogout">
            <i class="pi pi-sign-out"></i>
            <span>Deconnexion</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
