<script setup lang="ts">
const annee = new Date().getFullYear()
</script>

<template>
  <footer class="layout-footer" role="contentinfo">
    <span>Ledge · {{ annee }}</span>
    <span aria-hidden="true">·</span>
    <span>Cabinet de conseil &amp; comptabilité</span>
  </footer>
</template>
