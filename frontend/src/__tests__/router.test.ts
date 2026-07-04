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
