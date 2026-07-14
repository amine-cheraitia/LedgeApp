import { describe, it, expect } from 'vitest'
import { formatDA, formatDACompact, formatDAKpi } from '@/utils/currency'

// Intl peut emettre des espaces insecables (U+00A0 / U+202F) selon la version
// d'ICU : on normalise pour des assertions stables.
function norm(s: string): string {
  return s.replace(/\s/g, ' ')
}

describe('formatDA — montant exact avec centimes (tables, cartes)', () => {
  it('formate avec separateurs de milliers et 2 decimales', () => {
    expect(norm(formatDA(125000))).toBe('125 000,00 DA')
    expect(norm(formatDA(315000.5))).toBe('315 000,50 DA')
  })

  it('gere zero et les negatifs (avoirs)', () => {
    expect(norm(formatDA(0))).toBe('0,00 DA')
    expect(norm(formatDA(-11900))).toBe('-11 900,00 DA')
  })
})

describe('formatDACompact — axes de graphiques', () => {
  it('compacte milliers et millions avec 1 decimale max', () => {
    expect(norm(formatDACompact(3200000))).toBe('3,2 M DA')
    expect(norm(formatDACompact(532000))).toBe('532 k DA')
  })
})

describe('formatDAKpi — cartes KPI (compact au-dela du million)', () => {
  it('reste exact avec centimes sous le million (regle projet : toujours ,00)', () => {
    expect(norm(formatDAKpi(532400))).toBe('532 400,00 DA')
    expect(norm(formatDAKpi(999999))).toBe('999 999,00 DA')
  })

  it('passe en compact a partir du million avec 2 decimales max', () => {
    expect(norm(formatDAKpi(1000000))).toBe('1 M DA')
    expect(norm(formatDAKpi(1250000))).toBe('1,25 M DA')
    expect(norm(formatDAKpi(12345678))).toBe('12,35 M DA')
  })

  it('applique le seuil sur la valeur absolue (impayes negatifs)', () => {
    expect(norm(formatDAKpi(-1500000))).toBe('-1,5 M DA')
    expect(norm(formatDAKpi(-4500))).toBe('-4 500,00 DA')
  })
})
