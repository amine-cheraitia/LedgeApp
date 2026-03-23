<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div>
    <h2>Portail Client</h2>
    <p>Bienvenue, {{ auth.user?.name }}.</p>
  </div>
</template>
