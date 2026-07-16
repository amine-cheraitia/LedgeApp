import { expect, type Locator, type Page } from '@playwright/test'

/**
 * Aides pour piloter les composants PrimeVue 4 via des locators accessibles.
 *
 * Constat (verifie dans node_modules/primevue) : sur Select/MultiSelect/DatePicker,
 * l'attribut `id` passe en template atterrit sur le conteneur racine du composant
 * (PrimeVue route l'attribut "id" non declare comme prop vers le root via son
 * systeme de pass-through), pas sur l'element focusable interne. Le `<label for="...">`
 * pointe donc vers ce conteneur — cliquer dessus ouvre bien le panneau (Select)
 * ou expose l'input reel en descendant (DatePicker). Sur InputText/Textarea/
 * InputNumber/Password (composants a racine unique = l'input lui-meme), `id`
 * atterrit directement sur l'input : fill() fonctionne sans detour.
 */

function escapeId(id: string): string {
  return id.replace(/([:.[\],~!@$%^&*()+=/'"<>{}|`\s])/g, '\\$1')
}

/** Locator du conteneur associe au <label>texte</label> le plus proche dans `container`. */
export async function fieldRoot(container: Locator, labelText: string): Promise<Locator> {
  const label = container.locator('label', { hasText: labelText }).first()
  const forId = await label.getAttribute('for')
  if (!forId) {
    throw new Error(`Aucun attribut "for" trouve sur le label "${labelText}"`)
  }
  return container.page().locator(`#${escapeId(forId)}`)
}

/** Ouvre un Select/MultiSelect PrimeVue via son label puis choisit l'option par son texte visible. */
export async function selectByLabel(container: Locator, labelText: string, optionText: string): Promise<void> {
  const root = await fieldRoot(container, labelText)
  await root.click()
  await container.page().getByRole('option', { name: optionText, exact: true }).click()
}

/**
 * Resout l'element reellement saisissable (input/textarea) a partir du conteneur
 * associe au label : certains composants PrimeVue (Password, DatePicker) placent
 * l'id du template sur un conteneur racine et l'input reel en descendant.
 */
async function resolveFillable(root: Locator): Promise<Locator> {
  const tag = await root.evaluate((el) => el.tagName.toLowerCase())
  if (tag === 'input' || tag === 'textarea') return root
  const nested = root.locator('input, textarea')
  if ((await nested.count()) > 0) return nested.first()
  return root
}

/** Saisit une valeur dans un champ texte PrimeVue (InputText/Textarea/InputNumber/Password) via son label. */
export async function fillByLabel(container: Locator, labelText: string, value: string): Promise<void> {
  const root = await fieldRoot(container, labelText)
  const target = await resolveFillable(root)
  await target.fill(value)
}

/**
 * Ouvre un DatePicker PrimeVue via son label et selectionne la date du jour
 * (case portant l'attribut `data-p-today="true"`). Plus fiable que la saisie
 * clavier : le "parsing" manuel n'engage le v-model qu'au clic sur une cellule
 * du calendrier (Entree/Echap referment juste le panneau sans le commiter).
 */
export async function selectTodayInDatePicker(container: Locator, labelText: string): Promise<void> {
  const root = await fieldRoot(container, labelText)
  await root.click()
  const page = container.page()
  await page.locator('td[data-p-today="true"] span').first().click()
}

/** Suffixe court unique (horodatage + alea) pour des donnees de test non collisionnelles. */
export function uniqueSuffix(): string {
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`
}

/** Assertion de non-regression : aucun toast d'erreur PrimeVue visible sur la page. */
export async function expectNoErrorToast(page: Page): Promise<void> {
  await expect(page.locator('.p-toast-message-error')).toHaveCount(0)
}

/** Locator de la boite de dialogue PrimeVue actuellement ouverte (role="dialog"). */
export function openDialog(page: Page): Locator {
  return page.getByRole('dialog')
}
