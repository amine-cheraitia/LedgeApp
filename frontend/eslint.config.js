// Configuration ESLint (flat config) — Vue 3 + TypeScript.
// Philosophie : les ERREURS bloquent la CI (vrais defauts), le style tech-debt
// reste en WARNING (visible, non bloquant). Voir docs/STRATEGIE-TESTS.md.
import pluginVue from 'eslint-plugin-vue'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'

export default defineConfigWithVueTs(
  { files: ['**/*.{ts,mts,tsx,vue}'] },
  { ignores: ['dist/**', 'coverage/**', 'node_modules/**', '*.config.*'] },
  pluginVue.configs['flat/essential'],
  vueTsConfigs.recommended,
  {
    rules: {
      // Noms de composants mono-mot autorises (pages, layouts).
      'vue/multi-word-component-names': 'off',
      // Dette technique visible mais non bloquante.
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
    },
  },
)
