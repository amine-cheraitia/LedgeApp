// Helpers de date sans conversion UTC.
// Le PrimeVue DatePicker emet une Date a minuit LOCAL ; passer par toISOString()
// repasse a la veille dans les fuseaux a l'est de UTC (Algerie = UTC+1) -> decalage J-1.
// On formate / parse donc a partir des composants LOCAUX.

/** Date -> 'YYYY-MM-DD' a partir des composants locaux (pas de decalage de jour). */
export function toIsoDate(d: Date | null): string | null {
  if (!d) return null
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/** 'YYYY-MM-DD' -> Date a minuit local (le DatePicker affiche le bon jour partout). */
export function parseIsoDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}
