<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'
import Menu from 'primevue/menu'

const auth = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(false)

const menuItems = computed(() => [
  {
    label: 'Navigation',
    items: [
      { label: 'Tableau de bord', icon: 'pi pi-home', command: () => router.push('/') },
      { label: 'Entreprises', icon: 'pi pi-building', command: () => router.push('/entreprises') },
      { label: 'Exercices', icon: 'pi pi-calendar', command: () => router.push('/exercices') },
      { label: 'Prestations', icon: 'pi pi-list', command: () => router.push('/prestations') },
    ],
  },
  {
    label: 'Administration',
    items: [
      { label: 'Utilisateurs', icon: 'pi pi-users', command: () => router.push('/users'), visible: auth.isAdmin },
      { label: 'Paramètres', icon: 'pi pi-cog', command: () => router.push('/settings'), visible: auth.isAdmin },
    ],
  },
])

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}
</script>

<template>
  <a href="#main-content" class="skip-link">
    Aller au contenu principal
  </a>

  <div class="layout">
    <!-- Header mobile -->
    <header class="layout-header" role="banner">
      <Button
        icon="pi pi-bars"
        severity="secondary"
        text
        class="sidebar-toggle"
        :aria-label="sidebarOpen ? 'Fermer le menu' : 'Ouvrir le menu'"
        :aria-expanded="sidebarOpen"
        aria-controls="sidebar-nav"
        @click="toggleSidebar"
      />
      <h1 class="layout-title">Ledge</h1>
      <div class="layout-header-actions">
        <span class="user-name" aria-hidden="true">{{ auth.user?.name }}</span>
        <Button
          icon="pi pi-sign-out"
          severity="secondary"
          text
          aria-label="Se déconnecter"
          @click="handleLogout"
        />
      </div>
    </header>

    <!-- Sidebar overlay (mobile) -->
    <div
      v-if="sidebarOpen"
      class="sidebar-overlay"
      @click="sidebarOpen = false"
      aria-hidden="true"
    />

    <!-- Sidebar nav -->
    <nav
      id="sidebar-nav"
      class="layout-sidebar"
      :class="{ 'is-open': sidebarOpen }"
      role="navigation"
      aria-label="Menu principal"
    >
      <div class="sidebar-brand">
        <strong>Ledge</strong>
      </div>
      <Menu :model="menuItems" class="sidebar-menu" />
    </nav>

    <!-- Main content -->
    <main id="main-content" class="layout-main" role="main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
}

/* Header (mobile-first : visible) */
.layout-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 3.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0 1rem;
  background: var(--p-surface-0);
  border-bottom: 1px solid var(--p-surface-200);
  z-index: 100;
}

.layout-title {
  font-size: 1.125rem;
  font-weight: 700;
  flex: 1;
}

.layout-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.user-name {
  display: none;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

/* Sidebar */
.layout-sidebar {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 16rem;
  background: var(--p-surface-0);
  border-right: 1px solid var(--p-surface-200);
  transform: translateX(-100%);
  transition: transform 0.3s ease;
  z-index: 200;
  overflow-y: auto;
  padding-top: 1rem;
}

.layout-sidebar.is-open {
  transform: translateX(0);
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 150;
}

.sidebar-brand {
  padding: 0 1.25rem 1rem;
  font-size: 1.25rem;
}

.sidebar-menu {
  border: none;
  width: 100%;
}

.sidebar-toggle {
  display: inline-flex;
}

/* Main content */
.layout-main {
  flex: 1;
  padding: 4.5rem 1rem 1rem;
  width: 100%;
  min-width: 0;
}

/* Tablette (768px+) */
@media (min-width: 768px) {
  .user-name {
    display: block;
  }

  .layout-main {
    padding: 4.5rem 1.5rem 1.5rem;
  }
}

/* Desktop (1024px+) */
@media (min-width: 1024px) {
  .layout-header {
    left: 16rem;
  }

  .layout-sidebar {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: none;
  }

  .sidebar-toggle {
    display: none;
  }

  .layout-main {
    margin-left: 16rem;
    padding: 4.5rem 2rem 2rem;
  }
}
</style>
