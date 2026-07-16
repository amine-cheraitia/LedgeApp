import { test, expect } from '@playwright/test'
import { fieldRoot, fillByLabel, selectByLabel, selectTodayInDatePicker, uniqueSuffix } from './utils'

test.use({ storageState: 'e2e/.auth/admin.json' })

// Parcours metier complet, en admin : creation d'une entreprise -> devis -> envoi
// -> acceptation -> conversion en mission -> facturation (tranche 1) -> paiement
// integral -> statut "solde". Chaque etape est asserree avant de poursuivre pour
// isoler precisement un eventuel point de blocage.
test('parcours complet devis -> mission -> facture -> paiement solde', async ({ page }) => {
  const suffix = uniqueSuffix()
  const raisonSociale = `E2E Entreprise ${suffix}`
  const nif = `NIF${suffix}`

  // ===== 1. Creation de l'entreprise =====
  await page.goto('/entreprises')
  await page.getByRole('button', { name: 'Nouvelle entreprise' }).click()

  const entrepriseDialog = page.getByRole('dialog', { name: 'Nouvelle entreprise' })
  await expect(entrepriseDialog).toBeVisible()
  await fillByLabel(entrepriseDialog, 'Raison sociale *', raisonSociale)
  await fillByLabel(entrepriseDialog, 'NIF', nif)
  await fillByLabel(entrepriseDialog, 'Email', `e2e.${suffix}@ledge-test.dz`)
  await selectByLabel(entrepriseDialog, 'Regime fiscal *', 'Reel')
  await selectByLabel(entrepriseDialog, 'Categorie *', 'PME')
  await entrepriseDialog.getByRole('button', { name: 'Creer' }).click()

  await expect(entrepriseDialog).toBeHidden()
  await expect(page.locator('tr', { hasText: raisonSociale })).toBeVisible()

  // ===== 2. Creation du devis =====
  await page.goto('/devis')
  // Attend le chargement des referentiels (entreprises/prestations/exercices) charges
  // au montage : les Select du dialog seraient sinon vides au moment de l'ouverture.
  await page.waitForLoadState('networkidle')
  await page.getByRole('button', { name: 'Nouveau devis' }).click()

  const devisDialog = page.getByRole('dialog', { name: 'Nouveau devis' })
  await expect(devisDialog).toBeVisible()
  await selectByLabel(devisDialog, 'Entreprise *', raisonSociale)
  await selectByLabel(devisDialog, 'Prestation *', 'Assistance Comptable')
  // Date devis / date validite / categorie TVA : valeurs par defaut valides (aujourd'hui + 2 mois, TVA standard).
  await devisDialog.getByRole('button', { name: 'Creer' }).click()

  await expect(devisDialog).toBeHidden()
  const devisRow = page.locator('tr', { hasText: raisonSociale })
  await expect(devisRow).toBeVisible()
  await expect(devisRow.getByText('brouillon', { exact: true })).toBeVisible()

  // ===== 3. Envoi du devis (brouillon -> envoye) =====
  await devisRow.getByRole('button', { name: 'Envoyer le devis par mail au client' }).click()
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click()

  await expect(devisRow.getByText('envoye', { exact: true })).toBeVisible()

  // ===== 4. Acceptation du devis (envoye -> accepte) =====
  await devisRow.getByRole('button', { name: 'Accepter', exact: true }).click()
  await expect(devisRow.getByText('accepte', { exact: true })).toBeVisible()

  // ===== 5. Conversion en mission =====
  await devisRow.getByRole('button', { name: 'Convertir en mission' }).click()
  const conversionDialog = page.getByRole('dialog', { name: /Convertir .* en mission/ })
  await expect(conversionDialog).toBeVisible()
  await selectTodayInDatePicker(conversionDialog, 'Date debut *')
  await conversionDialog.getByRole('button', { name: 'Creer la mission' }).click()
  await expect(conversionDialog).toBeHidden()

  // ===== 6. Facture depuis la mission (tranche 1) =====
  await page.goto('/missions')
  const missionRow = page.locator('tr', { hasText: raisonSociale })
  await expect(missionRow).toBeVisible()
  await missionRow.getByRole('button', { name: 'Voir le détail de la mission' }).click()

  await expect(page.getByRole('heading', { name: 'Tranches de facturation suggérées' })).toBeVisible()
  await page.getByRole('button', { name: 'Créer une facture' }).click()
  await expect(page).toHaveURL(/\/factures$/)
  // Attend le chargement de la liste des missions (fetchMissions, admin uniquement)
  // avant d'ouvrir le dialog : sinon le Select "Mission *" serait vide au clic.
  await page.waitForLoadState('networkidle')

  await page.getByRole('button', { name: 'Creer une facture' }).click()
  const factureDialog = page.getByRole('dialog', { name: 'Nouvelle facture' })
  await expect(factureDialog).toBeVisible()
  await fieldRoot(factureDialog, 'Mission *').then((root) => root.click())
  await expect(page.getByRole('option').first()).toBeVisible()
  await page.getByRole('option').first().click()
  await expect(factureDialog.getByText('T1 — 30%')).toBeVisible()
  await factureDialog.getByRole('button', { name: 'Creer' }).click()
  await expect(factureDialog).toBeHidden()

  const factureRow = page.locator('tr', { hasText: raisonSociale })
  await expect(factureRow).toBeVisible()
  await expect(factureRow.getByText('en_attente', { exact: true })).toBeVisible()

  // ===== 7. Paiement integral de la facture =====
  await factureRow.getByRole('button', { name: 'Voir les encaissements' }).click()
  await expect(page.getByRole('heading', { name: 'Paiements enregistrés' })).toBeVisible()
  await page.getByRole('button', { name: 'Ajouter un paiement' }).click()

  // Le montant est pre-rempli avec le solde restant (paiement integral).
  await page.getByRole('button', { name: 'Vérifier →' }).click()
  await page.getByRole('button', { name: "Confirmer l'enregistrement du paiement" }).click()

  await expect(page.getByText('Paiement enregistré')).toBeVisible()

  // ===== 8. Verification du statut "solde" =====
  // Le drawer revient automatiquement a l'etape historique apres confirmation.
  await expect(page.getByRole('heading', { name: 'Paiements enregistrés' })).toBeVisible()
  await page.keyboard.press('Escape')

  // Statut sur la liste des factures (valeur brute non traduite, cf. FactureListPage).
  await expect(factureRow.getByText('solde', { exact: true })).toBeVisible()
})
