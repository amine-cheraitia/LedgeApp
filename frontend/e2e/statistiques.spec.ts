import { test, expect } from '@playwright/test'
import { fillByLabel, selectByLabel } from './utils'

test.use({ storageState: 'e2e/.auth/admin.json' })

test.describe('Statistiques', () => {
  test('onglet Cabinet : les 4 blocs sont visibles', async ({ page }) => {
    await page.goto('/statistiques')
    await expect(page.getByRole('heading', { name: 'Statistiques', level: 1 })).toBeVisible()

    await expect(page.getByRole('heading', { name: 'Top entreprises contributrices' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Répartition des missions par prestation' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Missions par état' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Créances', exact: true })).toBeVisible()
  })

  test('onglet Collaborateurs : KPI + objectifs pour Collaborateur E2E', async ({ page }) => {
    await page.goto('/statistiques')
    await page.getByRole('tab', { name: 'Collaborateurs' }).click()

    const body = page.locator('body')
    await selectByLabel(body, 'Collaborateur', 'Collaborateur E2E')

    await expect(page.getByRole('article', { name: 'CA HT réalisé' })).toBeVisible()
    await expect(page.getByRole('article', { name: 'Missions clôturées' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Missions par prestation' })).toBeVisible()

    await fillByLabel(body, 'CA HT cible (DA)', '500000')
    await page.getByRole('button', { name: /Sauvegarder les objectifs/ }).click()

    await expect(page.getByText('Sauvegardé')).toBeVisible()
  })
})
