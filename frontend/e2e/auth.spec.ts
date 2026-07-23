import { test, expect } from '@playwright/test'
import { fillByLabel } from './utils'

// Pas de storageState ici : chaque test part d'un contexte non authentifie
// pour exercer reellement le formulaire de connexion.

test.describe('Authentification', () => {
  test('login admin -> tableau de bord', async ({ page }) => {
    await page.goto('/login')
    const body = page.locator('body')
    await fillByLabel(body, 'Adresse e-mail', 'admin@ledge.dz')
    await fillByLabel(body, 'Mot de passe', 'password')
    await page.getByRole('button', { name: 'Se connecter à Ledge' }).click()

    await expect(page).toHaveURL('/')
    await expect(page.getByRole('heading', { name: 'Tableau de bord', level: 1 })).toBeVisible()
  })

  test('mauvais mot de passe -> message d\'erreur visible', async ({ page }) => {
    await page.goto('/login')
    const body = page.locator('body')
    await fillByLabel(body, 'Adresse e-mail', 'admin@ledge.dz')
    await fillByLabel(body, 'Mot de passe', 'mauvais-mot-de-passe')
    await page.getByRole('button', { name: 'Se connecter à Ledge' }).click()

    await expect(page.getByRole('alert').getByText('Identifiants incorrects.')).toBeVisible()
    await expect(page).toHaveURL('/login')
  })

  test('login secretaire -> son tableau de bord', async ({ page }) => {
    await page.goto('/login')
    const body = page.locator('body')
    await fillByLabel(body, 'Adresse e-mail', 'secretaire.e2e@ledge.dz')
    await fillByLabel(body, 'Mot de passe', 'password')
    await page.getByRole('button', { name: 'Se connecter à Ledge' }).click()

    await expect(page).toHaveURL('/')
    await expect(page.getByRole('heading', { name: 'Tableau de bord', level: 1 })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Facturation & recouvrement' })).toBeVisible()
  })
})
