import { defineConfig, devices } from '@playwright/test'

/**
 * Configuration Playwright — suite E2E dediee (environnement APP_ENV=e2e).
 *
 * Ports dedies (jamais ceux du dev quotidien 8000/5173) :
 *   - backend  : http://localhost:8001 (php artisan serve --env=e2e)
 *   - frontend : http://localhost:5174 (vite dev server)
 *
 * Base de donnees : ledge_e2e (MySQL local), jamais la base « ledge ».
 * `workers: 1` : les specs partagent une seule base -> determinisme avant vitesse.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 1,
  reporter: [
    ['list'],
    ['html', { open: 'never' }],
  ],
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL: 'http://localhost:5174',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
  webServer: [
    {
      command: 'php artisan serve --env=e2e --port=8001',
      cwd: '../backend',
      url: 'http://localhost:8001/up',
      reuseExistingServer: !process.env.CI,
      timeout: 30_000,
    },
    {
      command: 'npm run dev -- --port 5174 --strictPort',
      cwd: '.',
      url: 'http://localhost:5174',
      reuseExistingServer: !process.env.CI,
      timeout: 30_000,
      env: {
        // baseURL relatif '/api/v1' cote front -> proxy Vite vers le backend e2e.
        // (VITE_API_URL n'est actuellement lu nulle part dans src/, conserve pour
        // coherence avec la variable declaree dans vite-env.d.ts.)
        VITE_PROXY_TARGET: 'http://localhost:8001',
        VITE_API_URL: 'http://localhost:8001',
      },
    },
  ],
})
