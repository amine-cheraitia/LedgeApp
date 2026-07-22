<script setup lang="ts">
import { useNavigationProgress } from '@/composables/useNavigationProgress'

const { visible, valeur } = useNavigationProgress()
</script>

<template>
  <div
    v-if="visible"
    class="nav-progress"
    role="progressbar"
    aria-label="Chargement de la page"
    :aria-valuenow="Math.round(valeur)"
    aria-valuemin="0"
    aria-valuemax="100"
  >
    <div class="nav-progress-bar" :style="{ width: valeur + '%' }" />
  </div>
  <RouterView />
</template>

<style scoped>
.nav-progress {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  z-index: 10000;
  pointer-events: none;
}

.nav-progress-bar {
  height: 100%;
  background: var(--p-primary-color);
  border-radius: 0 2px 2px 0;
  transition: width 0.2s ease;
}

@media (prefers-reduced-motion: reduce) {
  .nav-progress-bar {
    transition: none;
  }
}
</style>
