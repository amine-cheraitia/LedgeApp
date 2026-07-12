<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'

const auth = useAuthStore()
const router = useRouter()

const entreprise = computed(() => auth.user?.entreprise)

const quickLinks = [
  {
    label: 'Mes factures',
    description: 'Consultez et téléchargez vos factures',
    icon: 'pi pi-file-invoice',
    route: '/portail/factures',
    severity: 'primary',
  },
  {
    label: 'Mes missions',
    description: "Suivez l\u2019avancement de vos dossiers",
    icon: 'pi pi-briefcase',
    route: '/portail/missions',
    severity: 'secondary',
  },
]
</script>

<template>
  <div>
    <section class="portail-welcome" aria-labelledby="portail-title">
      <h1 id="portail-title" class="portail-title">
        Bienvenue, <span class="portail-name">{{ auth.user?.name }}</span>
      </h1>
      <p class="portail-subtitle">
        Espace client de
        <strong>{{ entreprise?.raison_sociale ?? 'votre entreprise' }}</strong>
      </p>
    </section>

    <section class="portail-cards" aria-label="Accès rapide">
      <Card
        v-for="link in quickLinks"
        :key="link.route"
        class="portail-quick-card"
        :pt="{ body: { class: 'portail-card-body' } }"
      >
        <template #content>
          <div class="portail-card-icon" aria-hidden="true">
            <i :class="link.icon"></i>
          </div>
          <h2 class="portail-card-title">{{ link.label }}</h2>
          <p class="portail-card-desc">{{ link.description }}</p>
          <Button
            :label="link.label"
            :icon="link.icon"
            class="portail-card-btn"
            :aria-label="`Accéder à ${link.label}`"
            @click="router.push(link.route)"
          />
        </template>
      </Card>
    </section>

    <section v-if="entreprise" class="portail-info" aria-labelledby="portail-info-title">
      <h2 id="portail-info-title" class="portail-section-title">Informations cabinet</h2>
      <div class="portail-info-grid">
        <div class="portail-info-item">
          <span class="portail-info-label">Régime fiscal</span>
          <span class="portail-info-value">{{ entreprise.regime_fiscal }}</span>
        </div>
        <div class="portail-info-item">
          <span class="portail-info-label">Catégorie</span>
          <span class="portail-info-value">{{ entreprise.categorie }}</span>
        </div>
        <div v-if="entreprise.nif" class="portail-info-item">
          <span class="portail-info-label">NIF</span>
          <span class="portail-info-value">{{ entreprise.nif }}</span>
        </div>
        <div v-if="entreprise.ville" class="portail-info-item">
          <span class="portail-info-label">Ville</span>
          <span class="portail-info-value">{{ entreprise.ville }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.portail-welcome {
  margin-bottom: 2rem;
}

.portail-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--p-text-color);
  margin: 0 0 0.5rem;
}

.portail-name {
  color: var(--p-primary-color);
}

.portail-subtitle {
  font-size: 1rem;
  color: var(--p-text-muted-color);
  margin: 0;
}

.portail-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2.5rem;
}

.portail-quick-card {
  cursor: pointer;
  transition: box-shadow 0.2s;
}

.portail-quick-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.portail-card-body {
  text-align: center;
  padding: 1.5rem;
}

.portail-card-icon {
  font-size: 2.5rem;
  color: var(--p-primary-color);
  margin-bottom: 0.75rem;
}

.portail-card-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0 0 0.5rem;
  color: var(--p-text-color);
}

.portail-card-desc {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  margin: 0 0 1rem;
}

.portail-card-btn {
  width: 100%;
}

.portail-section-title {
  font-size: 1.15rem;
  font-weight: 600;
  color: var(--p-text-color);
  margin: 0 0 1rem;
}

.portail-info {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 1.25rem 1.5rem;
}

.portail-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem 1.5rem;
}

.portail-info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.portail-info-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
}

.portail-info-value {
  font-size: 0.925rem;
  font-weight: 500;
  color: var(--p-text-color);
  text-transform: capitalize;
}

@media (max-width: 480px) {
  .portail-title {
    font-size: 1.4rem;
  }

  .portail-cards {
    grid-template-columns: 1fr;
  }
}
</style>
