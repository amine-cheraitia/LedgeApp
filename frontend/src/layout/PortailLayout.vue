<script setup lang="ts">
import { useAuthStore } from '@/stores/authStore'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="portail-wrapper">
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>

    <header class="portail-topbar" role="banner">
      <div class="portail-topbar-left">
        <i class="pi pi-briefcase" style="font-size: 1.4rem; color: var(--p-primary-color)" aria-hidden="true"></i>
        <span class="portail-logo-text">LEDGE</span>
        <span class="portail-separator" aria-hidden="true">|</span>
        <span class="portail-company">{{ auth.user?.entreprise?.raison_sociale ?? 'Portail Client' }}</span>
      </div>

      <nav class="portail-nav" aria-label="Navigation portail">
        <router-link to="/portail" class="portail-nav-link" aria-label="Tableau de bord portail">
          <i class="pi pi-home" aria-hidden="true"></i>
          <span>Accueil</span>
        </router-link>
        <router-link to="/portail/factures" class="portail-nav-link" aria-label="Mes factures">
          <i class="pi pi-file-invoice" aria-hidden="true"></i>
          <span>Mes factures</span>
        </router-link>
        <router-link to="/portail/missions" class="portail-nav-link" aria-label="Mes missions">
          <i class="pi pi-briefcase" aria-hidden="true"></i>
          <span>Mes missions</span>
        </router-link>
        <router-link to="/portail/documents" class="portail-nav-link" aria-label="Mes documents">
          <i class="pi pi-folder" aria-hidden="true"></i>
          <span>Mes documents</span>
        </router-link>
      </nav>

      <div class="portail-topbar-right">
        <span class="portail-user-name">{{ auth.user?.name }}</span>
        <button
          type="button"
          class="portail-logout-btn"
          aria-label="Se déconnecter"
          @click="handleLogout"
        >
          <i class="pi pi-sign-out" aria-hidden="true"></i>
          <span>Déconnexion</span>
        </button>
      </div>
    </header>

    <main id="main-content" class="portail-main" role="main" tabindex="-1">
      <router-view />
    </main>

    <footer class="portail-footer" role="contentinfo">
      <span>© {{ new Date().getFullYear() }} Ledge — Cabinet de conseil</span>
    </footer>
  </div>

  <Toast />
</template>

<style scoped>
.portail-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--p-surface-ground);
}

.skip-link {
  position: absolute;
  top: -3rem;
  left: 0;
  background: var(--p-primary-color);
  color: #fff;
  padding: 0.5rem 1rem;
  z-index: 9999;
  border-radius: 0 0 4px 0;
  text-decoration: none;
}

.skip-link:focus {
  top: 0;
  outline: 2px solid #fff;
  outline-offset: 2px;
}

.portail-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0 1.5rem;
  height: 3.5rem;
  background: var(--p-surface-card);
  border-bottom: 1px solid var(--p-surface-border);
  position: sticky;
  top: 0;
  z-index: 100;
  flex-wrap: wrap;
}

.portail-topbar-left {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.portail-logo-text {
  font-weight: 700;
  font-size: 1.1rem;
  letter-spacing: 0.05em;
  color: var(--p-primary-color);
}

.portail-separator {
  color: var(--p-text-muted-color);
  font-size: 1.25rem;
}

.portail-company {
  font-size: 0.9rem;
  color: var(--p-text-color);
  font-weight: 500;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.portail-nav {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.portail-nav-link {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  text-decoration: none;
  font-size: 0.875rem;
  color: var(--p-text-color);
  transition: background 0.15s;
}

.portail-nav-link:hover {
  background: var(--p-surface-hover);
}

.portail-nav-link.router-link-active {
  background: var(--p-primary-50, #eff6ff);
  color: var(--p-primary-color);
  font-weight: 600;
}

.portail-nav-link:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.portail-topbar-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.portail-user-name {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.portail-logout-btn {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--p-surface-border);
  background: transparent;
  border-radius: 6px;
  font-size: 0.875rem;
  cursor: pointer;
  color: var(--p-text-color);
  transition: background 0.15s, border-color 0.15s;
}

.portail-logout-btn:hover {
  background: var(--p-surface-hover);
  border-color: var(--p-primary-color);
}

.portail-logout-btn:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.portail-main {
  flex: 1;
  padding: 2rem 1.5rem;
  max-width: 1200px;
  width: 100%;
  margin: 0 auto;
}

.portail-footer {
  text-align: center;
  padding: 1rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  border-top: 1px solid var(--p-surface-border);
}

@media (max-width: 768px) {
  .portail-topbar {
    height: auto;
    padding: 0.75rem 1rem;
    gap: 0.5rem;
  }

  .portail-nav {
    order: 3;
    width: 100%;
    justify-content: center;
    padding-bottom: 0.5rem;
  }

  .portail-company {
    display: none;
  }

  .portail-user-name {
    display: none;
  }

  .portail-main {
    padding: 1rem;
  }
}
</style>
