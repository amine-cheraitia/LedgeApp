import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Entreprise } from '@/types'

// ── Mock du client axios (le store auth reel s'execute lors du logout) ───────
const { mockGet, mockPost, mockResetCsrf } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockResetCsrf: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: mockGet,
    post: mockPost,
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
  resetCsrf: mockResetCsrf,
}))

// ── Import APRES les mocks ───────────────────────────────────────────────────
import PortailLayout from '@/layout/PortailLayout.vue'
import { mountPage, findButton } from '../helpers/mount'

const entreprise = {
  id: 1,
  raison_sociale: 'SARL Atlas Conseil',
  nif: null, nis: null, num_rc: null, article_imposition: null,
  regime_fiscal: 'reel', categorie: 'pme',
  secteur_activite: null, adresse: null, ville: null, wilaya: null,
  telephone: null, email: null, contact_principal: null,
  statut: 'client', notes: null, created_at: '', updated_at: '',
} as Entreprise

beforeEach(() => {
  vi.clearAllMocks()
})

describe('PortailLayout — structure et navigation client', () => {
  it('affiche la topbar avec le logo, la raison sociale et le nom du client', async () => {
    const { wrapper } = await mountPage(PortailLayout, {
      role: 'client',
      user: { name: 'Karim Client', entreprise },
    })

    expect(wrapper.text()).toContain('LEDGE')
    expect(wrapper.text()).toContain('SARL Atlas Conseil')
    expect(wrapper.text()).toContain('Karim Client')
  })

  it("affiche le repli 'Portail Client' quand l'entreprise n'est pas chargee", async () => {
    const { wrapper } = await mountPage(PortailLayout, { role: 'client' })

    expect(wrapper.text()).toContain('Portail Client')
  })

  it('propose le menu client complet avec les bons liens', async () => {
    const { wrapper } = await mountPage(PortailLayout, { role: 'client', user: { entreprise } })

    const nav = wrapper.find('nav[aria-label="Navigation portail"]')
    expect(nav.exists()).toBe(true)

    const hrefs = nav.findAll('a').map(a => a.attributes('href'))
    expect(hrefs).toEqual(['/portail', '/portail/factures', '/portail/missions', '/portail/documents'])
    expect(nav.text()).toContain('Accueil')
    expect(nav.text()).toContain('Mes factures')
    expect(nav.text()).toContain('Mes missions')
    expect(nav.text()).toContain('Mes documents')
  })

  it('respecte les reperes RGAA : skip link, landmarks et footer', async () => {
    const { wrapper } = await mountPage(PortailLayout, { role: 'client', user: { entreprise } })

    const skip = wrapper.find('a.skip-link')
    expect(skip.attributes('href')).toBe('#main-content')
    expect(skip.text()).toBe('Aller au contenu principal')

    expect(wrapper.find('header[role="banner"]').exists()).toBe(true)
    expect(wrapper.find('main#main-content[role="main"]').exists()).toBe(true)
    expect(wrapper.find('footer[role="contentinfo"]').text()).toContain(String(new Date().getFullYear()))
    expect(wrapper.find('footer').text()).toContain('Ledge')
  })
})

describe('PortailLayout — deconnexion', () => {
  it('deconnecte le client puis redirige vers /login', async () => {
    mockPost.mockResolvedValue({})
    const { wrapper, router, auth } = await mountPage(PortailLayout, {
      role: 'client',
      user: { entreprise },
    })

    const logoutBtn = findButton(wrapper, 'Se déconnecter')
    expect(logoutBtn, 'bouton de deconnexion accessible').toBeTruthy()
    await logoutBtn!.trigger('click')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith('/logout')
    expect(auth.user).toBeNull()
    expect(mockResetCsrf).toHaveBeenCalled()
    expect(router.currentRoute.value.fullPath).toBe('/login')
  })
})
