<script setup lang="ts">
defineProps<{
  size?: number
  withWordmark?: boolean
}>()
</script>

<template>
  <span class="ledge-logo" :class="{ 'ledge-logo-with-wordmark': withWordmark }">
    <!-- Quand le wordmark "Ledge" est affiche, le SVG est purement decoratif
         (aria-hidden) : le texte visible sert de nom accessible. Sans wordmark
         (ex. login / PDF), le SVG porte le nom accessible "Ledge". -->
    <svg
      :width="size ?? 32"
      :height="size ?? 32"
      viewBox="0 0 40 40"
      :role="withWordmark ? undefined : 'img'"
      :aria-label="withWordmark ? undefined : 'Ledge'"
      :aria-hidden="withWordmark ? 'true' : undefined"
      class="ledge-logo-mark"
    >
      <!-- Damier 2x2 — marque Ledge (coherent login + PDF) -->
      <rect x="3" y="3" width="15" height="15" rx="4" fill="currentColor" opacity="0.9" />
      <rect x="22" y="3" width="15" height="15" rx="4" fill="currentColor" opacity="0.6" />
      <rect x="3" y="22" width="15" height="15" rx="4" fill="currentColor" opacity="0.6" />
      <rect x="22" y="22" width="15" height="15" rx="4" fill="currentColor" opacity="0.9" />
    </svg>

    <span v-if="withWordmark" class="ledge-logo-wordmark">Ledge</span>
  </span>
</template>

<style scoped>
.ledge-logo {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  color: var(--ledge-primary);
}

.app-dark .ledge-logo {
  color: var(--p-text-color);
}

.app-dark .ledge-logo-wordmark {
  color: var(--p-text-color);
}

.ledge-logo-mark {
  flex-shrink: 0;
  display: block;
}

.ledge-logo-wordmark {
  font-family: var(--ledge-ff-display);
  font-weight: 500;
  font-size: 1.4rem;
  letter-spacing: 0.01em;
  line-height: 1;
  font-variation-settings: 'opsz' 144, 'SOFT' 30;
  color: var(--ledge-primary);
}
</style>
