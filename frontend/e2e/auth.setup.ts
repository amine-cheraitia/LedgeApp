import { test as setup, expect } from '@playwright/test'
import { fillByLabel } from './utils'

const ADMIN_AUTH_FILE = 'e2e/.auth/admin.json'
const SECRETAIRE_AUTH_FILE = 'e2e/.auth/secretaire.json'

async function loginViaUi(page: import('@playwright/test').Page, email: string, password: string) {
  await page.goto('/login')
  const body = page.locator('body')
  await fillByLabel(body, 'Adresse e-mail', email)
  await fillByLabel(body, 'Mot de passe', password)
  await page.getByRole('button', { name: 'Se connecter à Ledge' }).click()
  await expect(page.getByRole('heading', { name: 'Tableau de bord', level: 1 })).toBeVisible()
}

setup('authentifier admin@ledge.dz', async ({ page }) => {
  await loginViaUi(page, 'admin@ledge.dz', 'password')
  await page.context().storageState({ path: ADMIN_AUTH_FILE })
})

setup('authentifier secretaire.e2e@ledge.dz', async ({ page }) => {
  await loginViaUi(page, 'secretaire.e2e@ledge.dz', 'password')
  await page.context().storageState({ path: SECRETAIRE_AUTH_FILE })
})
