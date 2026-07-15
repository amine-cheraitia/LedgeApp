// Palette categorielle partagee des graphiques (page Statistiques).
// Tons distincts, lisibles en clair comme en sombre — cyclage par modulo.
export const CHART_PALETTE = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b', '#ec4899']

export function chartColor(i: number): string {
  return CHART_PALETTE[i % CHART_PALETTE.length]
}
