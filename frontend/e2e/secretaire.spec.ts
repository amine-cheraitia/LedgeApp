import { test, expect } from '@playwright/test'
import { expectNoErrorToast } from './utils'

test.use({ storageState: 'e2e/.auth/secretaire.json' })

// Non-regression 403 en serie : la secretaire visite les pages auxquelles elle a
// acces et ne doit jamais voir de toast d'erreur PrimeVue (appel API rejete en
// silence cote composant, cf. onMounted des pages Devis/Factures qui evitent de
// charger les referentiels interdits a la secretaire).
const pagesAutorisees = ['/', '/entreprises', '/devis', '/factures', '/creances']

test.describe('Secretaire — non-regression des 403 en serie', () => {
  for (const path of pagesAutorisees) {
    test(`aucun toast d'erreur sur ${path}`, async ({ page }) => {
      await page.goto(path)
      await page.waitForLoadState('networkidle')
      await expectNoErrorToast(page)
    })
  }

  test('les boutons de creation devis/facture sont absents', async ({ page }) => {
    await page.goto('/devis')
    await page.waitForLoadState('networkidle')
    await expect(page.getByRole('button', { name: 'Nouveau devis' })).toHaveCount(0)

    await page.goto('/factures')
    await page.waitForLoadState('networkidle')
    await expect(page.getByRole('button', { name: 'Creer une facture' })).toHaveCount(0)
  })

  test('/statistiques -> acces refuse', async ({ page }) => {
    await page.goto('/statistiques')
    await expect(page).toHaveURL(/\/acces-refuse/)
    await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible()
  })
})
