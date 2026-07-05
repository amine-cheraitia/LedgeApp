<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'

const router = useRouter()
const auth = useAuthStore()

// Destination "accueil" selon l'etat de session : client -> portail, staff -> dashboard,
// visiteur non connecte -> login. La page 404 est accessible dans toutes les zones.
const accueil = computed(() => {
  if (!auth.isAuthenticated) return '/login'
  return auth.isClient ? '/portail' : '/'
})

const accueilLabel = computed(() =>
  auth.isAuthenticated ? "Retour à l'accueil" : 'Aller à la connexion',
)

function retourAccueil() {
  router.push(accueil.value)
}
</script>

<template>
  <a href="#contenu-introuvable" class="skip-link">Aller au contenu principal</a>

  <main id="contenu-introuvable" class="notfound-page">
    <div class="card notfound-card" role="alert" aria-live="polite">
      <p class="notfound-code" aria-hidden="true">404</p>
      <div class="notfound-icon" aria-hidden="true">
        <i class="pi pi-compass"></i>
      </div>
      <h1 class="notfound-title">Page introuvable</h1>
      <p class="notfound-message">
        La page que vous cherchez n'existe pas ou a été déplacée.
        Vérifiez l'adresse saisie ou revenez à une page connue.
      </p>
      <Button
        :label="accueilLabel"
        icon="pi pi-home"
        :aria-label="accueilLabel"
        @click="retourAccueil"
      />
    </div>
  </main>
</template>

<style scoped>
/* Page standalone (hors AppLayout) : on pose explicitement le fond clair/sombre,
   comme la page de login. `.app-dark` est porte par <html> (global). */
.notfound-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  min-height: 100dvh;
  padding: 1.5rem;
  background: var(--p-surface-50);
}

.app-dark .notfound-page {
  background: #111827;
}

/* La carte s'appuie sur la classe globale `.card` (theme-aware :
   --p-surface-0 en clair, --p-surface-800 en sombre). */
.notfound-card {
  max-width: 28rem;
  width: 100%;
  text-align: center;
  padding: 2rem 1.5rem;
}

.notfound-code {
  font-family: var(--ledge-ff-mono, monospace);
  font-size: 3.5rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0.05em;
  margin: 0 0 0.5rem;
  color: var(--p-text-muted-color);
}

.notfound-icon {
  width: 4rem;
  height: 4rem;
  margin: 0 auto 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--p-surface-100);
  color: var(--p-text-muted-color);
  font-size: 1.75rem;
}

.app-dark .notfound-icon {
  background: var(--p-surface-700);
}

.notfound-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 0.75rem;
  color: var(--p-text-color);
}

.notfound-message {
  color: var(--p-text-muted-color);
  margin: 0 0 1.5rem;
  line-height: 1.5;
}
</style>
