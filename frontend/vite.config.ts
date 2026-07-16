/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import { defaultExclude } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import Components from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from '@primevue/auto-import-resolver'
import { resolve } from 'path'

// Proxy vers l'API Laravel — partage entre le serveur de dev et `vite preview`.
// Cible configurable (VITE_PROXY_TARGET) : localhost:8000 en dev local,
// http://nginx:80 dans la stack Docker (le backend n'y est pas sur localhost).
const proxyTarget = process.env.VITE_PROXY_TARGET || 'http://localhost:8000'
const apiProxy = {
  '/api': { target: proxyTarget, changeOrigin: true },
  '/sanctum': { target: proxyTarget, changeOrigin: true },
}

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    Components({
      resolvers: [PrimeVueResolver()],
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: apiProxy,
  },
  // `vite preview` sert le build de prod. On reprend le meme port + proxy que
  // le dev pour que l'auth Sanctum (stateful domain localhost:5173) et les
  // appels /api fonctionnent -> Lighthouse mesure le vrai score en conditions
  // reelles. (Arreter `npm run dev` avant de lancer `npm run preview`.)
  preview: {
    port: 5173,
    strictPort: true,
    proxy: apiProxy,
  },
  css: {
    preprocessorOptions: {
      scss: {
        silenceDeprecations: ['legacy-js-api'],
      },
    },
  },
  test: {
    environment: 'happy-dom',
    globals: true,
    // La suite E2E Playwright (frontend/e2e/*.spec.ts) vit hors de src/, mais le
    // glob par defaut de Vitest scanne tout le repo : on l'exclut explicitement
    // pour que `npx vitest run` ne la ramasse jamais.
    exclude: [...defaultExclude, 'e2e/**'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      reportsDirectory: 'coverage',
      include: ['src/**/*.{ts,vue}'],
      exclude: ['src/**/*.d.ts', 'src/main.ts', 'src/**/__tests__/**', 'src/types/**'],
      // Garde-fou anti-regression (equivalent du --min=80 backend). npm run test:coverage
      // echoue si la couverture repasse sous ces seuils.
      thresholds: {
        lines: 80,
        statements: 80,
        branches: 75,
        functions: 65,
      },
    },
  },
})
