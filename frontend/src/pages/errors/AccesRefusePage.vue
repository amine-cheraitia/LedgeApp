<script setup lang="ts">
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const router = useRouter()

function retourDashboard() {
  router.push('/')
}
</script>

<template>
  <a href="#contenu-acces-refuse" class="skip-link">Aller au contenu principal</a>

  <main id="contenu-acces-refuse" class="acces-refuse-page">
    <div class="card acces-refuse-card" role="alert" aria-live="polite">
      <div class="acces-refuse-icon" aria-hidden="true">
        <i class="pi pi-lock"></i>
      </div>
      <h1 class="acces-refuse-title">Accès refusé</h1>
      <p class="acces-refuse-message">
        Vous n'avez pas les droits nécessaires pour accéder à cette page.
        Contactez votre administrateur si vous pensez qu'il s'agit d'une erreur.
      </p>
      <Button
        label="Retour au tableau de bord"
        icon="pi pi-home"
        aria-label="Retourner au tableau de bord"
        @click="retourDashboard"
      />
    </div>
  </main>
</template>

<style scoped>
.acces-refuse-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 1.5rem;
}

.acces-refuse-card {
  max-width: 28rem;
  width: 100%;
  text-align: center;
  padding: 2rem 1.5rem;
}

.acces-refuse-icon {
  width: 4rem;
  height: 4rem;
  margin: 0 auto 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--red-100, #fee2e2);
  color: var(--red-500, #ef4444);
  font-size: 1.75rem;
}

.acces-refuse-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 0.75rem;
  color: var(--text-color);
}

.acces-refuse-message {
  color: var(--text-color-secondary);
  margin: 0 0 1.5rem;
  line-height: 1.5;
}
</style>
