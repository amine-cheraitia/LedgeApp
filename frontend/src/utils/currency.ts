// Formatage des montants en Dinars Algeriens (DA) — source unique.
// Remplace les definitions locales dupliquees des pages dashboard/KPI
// (regles de decimales divergentes d'une page a l'autre).

const EXACT = new Intl.NumberFormat('fr-DZ', {
  style: 'decimal',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const ENTIER = new Intl.NumberFormat('fr-DZ', { maximumFractionDigits: 0 })

const COMPACT_AXE = new Intl.NumberFormat('fr-DZ', { notation: 'compact', maximumFractionDigits: 1 })

const COMPACT_KPI = new Intl.NumberFormat('fr-DZ', { notation: 'compact', maximumFractionDigits: 2 })

/** Montant exact avec centimes : « 125 000,00 DA » (tables, listes de factures). */
export function formatDA(montant: number): string {
  return EXACT.format(montant) + ' DA'
}

/** Montant exact sans centimes : « 125 000 DA » (cartes KPI sous le million). */
export function formatDAEntier(montant: number): string {
  return ENTIER.format(montant) + ' DA'
}

/** Montant compact court : « 1,3 M DA » / « 532 k DA » (axes et pastilles de graphiques). */
export function formatDACompact(montant: number): string {
  return COMPACT_AXE.format(montant) + ' DA'
}

/**
 * Montant de carte KPI : compact au-dela d'un million (« 1,25 M DA »),
 * sinon exact sans centimes (« 532 400 DA »).
 * Toujours accompagner l'affichage d'un `title` + `aria-label` portant
 * le montant exact (formatDA) pour ne pas perdre la precision.
 */
export function formatDAKpi(montant: number): string {
  return Math.abs(montant) >= 1_000_000 ? COMPACT_KPI.format(montant) + ' DA' : formatDAEntier(montant)
}
