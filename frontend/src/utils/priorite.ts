// Source de verite unique des priorites de tache.
// 4 niveaux canoniques (alignes sur les formulaires et les badges PrimeVue).
// Eviter toute duplication de ce mapping ailleurs dans l'app.

export interface PrioriteOption {
  label: string
  value: number
}

export const PRIORITE_OPTIONS: PrioriteOption[] = [
  { label: 'Faible', value: 1 },
  { label: 'Normale', value: 2 },
  { label: 'Haute', value: 3 },
  { label: 'Urgente', value: 4 },
]

const LABELS = ['', 'Faible', 'Normale', 'Haute', 'Urgente'] as const
const SEVERITIES = ['', 'secondary', 'info', 'warn', 'danger'] as const

// Couleurs alignees sur les severites des badges PrimeVue (gris / bleu / ambre / rouge).
export const PRIORITE_COLORS: Record<number, string> = {
  1: '#64748B',
  2: '#3B82F6',
  3: '#F59E0B',
  4: '#EF4444',
}

export function prioriteLabel(p: number): string {
  return LABELS[p] ?? String(p)
}

export function prioriteSeverity(p: number): string {
  return SEVERITIES[p] ?? 'secondary'
}

export function prioriteColor(p: number): string {
  return PRIORITE_COLORS[Math.min(4, Math.max(1, p))] ?? '#94A3B8'
}
