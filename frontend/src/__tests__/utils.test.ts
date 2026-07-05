import { describe, it, expect } from 'vitest'
import { toIsoDate, parseIsoDate } from '@/utils/date'
import {
  PRIORITE_OPTIONS,
  prioriteLabel,
  prioriteSeverity,
} from '@/utils/priorite'

// ─────────────────────────────────────────────────────────────────────────────
// utils/date.ts — formatage local sans passage UTC (pas de décalage J-1)
// ─────────────────────────────────────────────────────────────────────────────

describe('toIsoDate', () => {
  it('retourne null pour une date nulle', () => {
    expect(toIsoDate(null)).toBeNull()
  })

  it("formate une date locale en 'YYYY-MM-DD'", () => {
    expect(toIsoDate(new Date(2026, 6, 4))).toBe('2026-07-04')
  })

  it('pad le mois et le jour sur 2 chiffres', () => {
    expect(toIsoDate(new Date(2026, 0, 5))).toBe('2026-01-05')
  })

  it("utilise les composants LOCAUX (une date a minuit local ne recule pas d'un jour)", () => {
    // Minuit local le 1er mars : toISOString() en UTC+1 donnerait '2026-02-28'
    const minuitLocal = new Date(2026, 2, 1, 0, 0, 0)
    expect(toIsoDate(minuitLocal)).toBe('2026-03-01')
  })
})

describe('parseIsoDate', () => {
  it('retourne null pour null, undefined ou chaine vide', () => {
    expect(parseIsoDate(null)).toBeNull()
    expect(parseIsoDate(undefined)).toBeNull()
    expect(parseIsoDate('')).toBeNull()
  })

  it("parse 'YYYY-MM-DD' en Date a minuit local", () => {
    const d = parseIsoDate('2026-07-04')!
    expect(d.getFullYear()).toBe(2026)
    expect(d.getMonth()).toBe(6) // juillet = index 6
    expect(d.getDate()).toBe(4)
    expect(d.getHours()).toBe(0)
    expect(d.getMinutes()).toBe(0)
  })

  it('est reciproque de toIsoDate (aller-retour stable)', () => {
    expect(toIsoDate(parseIsoDate('2026-12-31'))).toBe('2026-12-31')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// utils/priorite.ts — source de verite unique des priorites de tache
// ─────────────────────────────────────────────────────────────────────────────

describe('PRIORITE_OPTIONS', () => {
  it('expose les 4 niveaux canoniques dans lordre', () => {
    expect(PRIORITE_OPTIONS).toHaveLength(4)
    expect(PRIORITE_OPTIONS.map((o) => o.label)).toEqual(['Faible', 'Normale', 'Haute', 'Urgente'])
    expect(PRIORITE_OPTIONS.map((o) => o.value)).toEqual([1, 2, 3, 4])
  })
})

describe('prioriteLabel', () => {
  it('retourne le libelle de chaque niveau valide', () => {
    expect(prioriteLabel(1)).toBe('Faible')
    expect(prioriteLabel(2)).toBe('Normale')
    expect(prioriteLabel(3)).toBe('Haute')
    expect(prioriteLabel(4)).toBe('Urgente')
  })

  it('retombe sur la valeur brute en chaine pour un niveau inconnu', () => {
    expect(prioriteLabel(9)).toBe('9')
  })
})

describe('prioriteSeverity', () => {
  it('mappe chaque niveau sur la severite PrimeVue attendue', () => {
    expect(prioriteSeverity(1)).toBe('secondary')
    expect(prioriteSeverity(2)).toBe('info')
    expect(prioriteSeverity(3)).toBe('warn')
    expect(prioriteSeverity(4)).toBe('danger')
  })

  it("retombe sur 'secondary' pour un niveau hors bornes", () => {
    expect(prioriteSeverity(7)).toBe('secondary')
  })
})
