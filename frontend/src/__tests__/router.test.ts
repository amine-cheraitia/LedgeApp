import { describe, it, expect } from 'vitest'
import router from '@/router'

// Garde de non-regression pour le correctif d'autorisation :
// la route /audit-logs (journal d'audit) doit rester reservee aux admins,
// en coherence avec le middleware `role:admin` cote backend et le menu (isAdmin).
describe('router — garde de role sur /audit-logs', () => {
  const auditRoute = router.getRoutes().find((r) => r.name === 'audit-logs')

  it('la route existe', () => {
    expect(auditRoute).toBeDefined()
  })

  it('declare des roles autorises (meta.roles)', () => {
    expect(auditRoute?.meta?.roles).toBeDefined()
  })

  it('est reservee au seul role admin', () => {
    const roles = auditRoute?.meta?.roles as string[] | undefined
    expect(roles).toContain('admin')
    expect(roles).not.toContain('secretaire')
    expect(roles).not.toContain('collaborateur')
  })
})

// Garde de non-regression pour la page 404 : toute URL inconnue doit resoudre
// vers la route catch-all `page-introuvable` (et non un ecran vide).
describe('router — page 404 (catch-all)', () => {
  it('resout une URL inconnue vers la route page-introuvable', () => {
    const resolved = router.resolve('/une-url-qui-nexiste-pas-123')
    expect(resolved.name).toBe('page-introuvable')
  })

  it('n\'impose aucune restriction d\'auth (accessible a tous)', () => {
    const resolved = router.resolve('/autre-url-inconnue')
    expect(resolved.meta?.requiresAuth).toBeFalsy()
    expect(resolved.meta?.guest).toBeFalsy()
    expect(resolved.meta?.roles).toBeUndefined()
  })
})
