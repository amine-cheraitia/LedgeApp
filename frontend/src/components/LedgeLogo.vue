<script setup lang="ts">
defineProps<{
  size?: number
  withWordmark?: boolean
}>()
</script>

<template>
  <span class="ledge-logo" :class="{ 'ledge-logo-with-wordmark': withWordmark }">
    <svg
      :width="size ?? 32"
      :height="size ?? 32"
      viewBox="0 0 40 40"
      role="img"
      aria-label="Ledge"
      class="ledge-logo-mark"
    >
      <!-- Sceau circulaire — autorite comptable -->
      <circle cx="20" cy="20" r="19" fill="none" stroke="currentColor" stroke-width="1.5" />

      <!-- Lettre L stylisee, branche serif (rappel Fraunces) -->
      <path
        d="M14 11 L14 27 L26 27 L26 24 L17 24 L17 11 Z"
        fill="currentColor"
      />

      <!-- Trait de soulignement type ledger -->
      <line x1="11" y1="30" x2="29" y2="30" stroke="currentColor" stroke-width="0.8" stroke-linecap="round" />
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
