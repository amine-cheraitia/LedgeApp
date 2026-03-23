<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <a href="#main-content" class="skip-link">
    Aller au contenu principal
  </a>

  <div class="portail-layout">
    <header class="portail-header" role="banner">
      <h1 class="portail-title">Ledge — Portail Client</h1>
      <div class="portail-header-actions">
        <span>{{ auth.user?.name }}</span>
        <Button
          icon="pi pi-sign-out"
          severity="secondary"
          text
          aria-label="Se déconnecter"
          @click="handleLogout"
        />
      </div>
    </header>

    <main id="main-content" class="portail-main" role="main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.portail-layout {
  min-height: 100vh;
}

.portail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: var(--p-content-background);
  border-bottom: 1px solid var(--p-content-border-color);
}

.portail-title {
  font-size: 1.125rem;
  font-weight: 700;
}

.portail-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.portail-main {
  padding: 1rem;
  max-width: 1024px;
  margin: 0 auto;
}

@media (min-width: 768px) {
  .portail-main {
    padding: 1.5rem;
  }
}
</style>
