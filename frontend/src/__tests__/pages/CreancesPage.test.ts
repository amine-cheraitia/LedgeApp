import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Facture } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { mockIndex, mockStore } = vi.hoisted(() => ({
  mockIndex: vi.fn(),
  mockStore: vi.fn(),
}))

vi.mock('@/api/modules/creances', () => ({
  creancesApi: { index: mockIndex },
}))

vi.mock('@/api/modules/relances', () => ({
  relancesApi: { store: mockStore },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import CreancesPage from '@/pages/relances/CreancesPage.vue'
import { mountPage, findButton } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
function echeanceIlYa(jours: number): string {
  return new Date(Date.now() - jours * 86400000).toISOString()
}

function makeFacture(overrides: Partial<Facture> = {}): Facture {
  return {
    id: 1,
    entreprise_id: 1,
    exercice_id: 1,
    mission_id: null,
    devis_id: null,
    created_by: 1,
    tva_rate_id: 1,
    numero: 'FF2026-001',
    type: 'FF',
    facture_origine_id: null,
    date_facture: '2026-01-10',
    date_echeance: echeanceIlYa(10),
    montant_ht: 100000,
    taux_tva: 19,
    montant_tva: 19000,
    montant_ttc: 119000,
    montant_paye: 0,
    statut_paiement: 'en_attente',
    mode_paiement: 'non_defini',
    montant_restant: 119000,
    pdf_path: null,
    entreprise: {
      id: 1,
      raison_sociale: 'SARL Etoile',
      email: 'contact@etoile.dz',
    } as Facture['entreprise'],
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

/**
 * Bouton "Envoyer" du footer du dialog. findButton ne convient pas ici :
 * les boutons "Relancer" des lignes portent un aria-label litteral contenant
 * "Envoyer une relance..." qui matcherait en premier.
 */
function boutonEnvoyer(wrapper: any) {
  return wrapper.findAll('button').find((b: any) => b.text() === 'Envoyer')
}

const factureRecente = makeFacture()
const factureAncienne = makeFacture({
  id: 2,
  numero: 'FF2026-002',
  date_echeance: echeanceIlYa(60),
  statut_paiement: 'partiel',
  montant_ttc: 80000,
  montant_restant: 50000,
  entreprise: undefined,
})

beforeEach(() => {
  vi.clearAllMocks()
  mockIndex.mockResolvedValue({ data: { data: [factureRecente, factureAncienne] } })
})

describe('CreancesPage — liste des creances', () => {
  it('charge et affiche les creances avec entreprise, retard et statut', async () => {
    const { wrapper } = await mountPage(CreancesPage)

    expect(mockIndex).toHaveBeenCalledOnce()
    expect(wrapper.text()).toContain('Créances impayées')
    expect(wrapper.text()).toContain('FF2026-001')
    expect(wrapper.text()).toContain('SARL Etoile')
    expect(wrapper.text()).toContain('contact@etoile.dz')
    // Entreprise absente -> tiret
    expect(wrapper.text()).toContain('—')
    // Retard J+10 (warn) et J+60 (danger)
    expect(wrapper.text()).toContain('J+10')
    expect(wrapper.text()).toContain('J+60')
    // Statuts
    expect(wrapper.text()).toContain('Impayée')
    expect(wrapper.text()).toContain('Partiel')
  })

  it('affiche le KPI total restant du en DA', async () => {
    const { wrapper } = await mountPage(CreancesPage)

    // 119 000 + 50 000 = 169 000 DA (separateurs dependant de la locale)
    expect(wrapper.text()).toContain('Total restant dû')
    expect(wrapper.text().replace(/[\s  ,.]/g, '')).toContain('169000')
    expect(wrapper.text()).toContain('DA')
  })

  it('affiche un etat vide accessible quand il n y a aucune creance', async () => {
    mockIndex.mockResolvedValue({ data: { data: [] } })
    const { wrapper } = await mountPage(CreancesPage)

    expect(wrapper.text()).toContain('Aucune créance impayée')
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    // Pas de barre KPI sans creances
    expect(wrapper.text()).not.toContain('Total restant dû')
  })

  it('affiche un toast erreur si le chargement echoue', async () => {
    mockIndex.mockRejectedValue(new Error('500'))
    await mountPage(CreancesPage)

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de charger les créances' }),
    )
  })

  it('recharge la liste au clic sur Actualiser', async () => {
    const { wrapper } = await mountPage(CreancesPage)

    await findButton(wrapper, 'Actualiser')!.trigger('click')
    await flushPromises()

    expect(mockIndex).toHaveBeenCalledTimes(2)
  })
})

describe('CreancesPage — dialog de relance', () => {
  it('ouvre le dialog avec le recap de la facture et le niveau 1 par defaut', async () => {
    const { wrapper } = await mountPage(CreancesPage)

    await findButton(wrapper, 'Relancer')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Envoyer une relance')
    expect(wrapper.text()).toContain('FF2026-001')
    expect(wrapper.text()).toContain('SARL Etoile')
    expect(wrapper.text()).toContain('contact@etoile.dz')
    expect(wrapper.text()).toContain('Niveau 1 — Rappel (J+15)')
    expect(wrapper.text()).toContain('Restant dû')
  })

  it("affiche 'Non renseigné' quand l entreprise n a pas d email", async () => {
    const { wrapper } = await mountPage(CreancesPage)

    // 2e bouton Relancer = facture sans entreprise
    const boutons = wrapper.findAll('button').filter(b => b.text().includes('Relancer'))
    await boutons[1].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Non renseigné')
    expect(wrapper.text()).toContain('FF2026-002')
  })

  it('envoie la relance au niveau choisi et ferme le dialog avec un toast succes', async () => {
    mockStore.mockResolvedValue({ data: { data: { email_destinataire: 'contact@etoile.dz' } } })
    const { wrapper } = await mountPage(CreancesPage)

    await findButton(wrapper, 'Relancer')!.trigger('click')
    await flushPromises()

    // Change le niveau via le vm (Select PrimeVue trop lourd a piloter)
    ;(wrapper.vm as any).relanceNiveau = 3
    await flushPromises()
    expect(wrapper.text()).toContain('Niveau 3 — Mise en demeure (J+45)')

    await boutonEnvoyer(wrapper)!.trigger('click')
    await flushPromises()

    expect(mockStore).toHaveBeenCalledWith(1, { niveau: 3 })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'success',
        summary: 'Relance envoyée',
        detail: expect.stringContaining('contact@etoile.dz'),
      }),
    )
    expect((wrapper.vm as any).relanceDialog).toBe(false)
  })

  it("affiche le message d erreur de l API si l envoi echoue", async () => {
    mockStore.mockRejectedValue({ response: { data: { message: 'Email destinataire manquant' } } })
    const { wrapper } = await mountPage(CreancesPage)

    await findButton(wrapper, 'Relancer')!.trigger('click')
    await boutonEnvoyer(wrapper)!.trigger('click')
    await flushPromises()

    expect(mockStore).toHaveBeenCalledWith(1, { niveau: 1 })
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Email destinataire manquant' }),
    )
    // Le dialog reste ouvert
    expect((wrapper.vm as any).relanceDialog).toBe(true)
  })

  it('retombe sur un message generique quand l erreur ne porte pas de message API', async () => {
    mockStore.mockRejectedValue(new Error('reseau'))
    const { wrapper } = await mountPage(CreancesPage)

    await findButton(wrapper, 'Relancer')!.trigger('click')
    await boutonEnvoyer(wrapper)!.trigger('click')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: "Erreur lors de l'envoi" }),
    )
  })

  it("n envoie rien si aucune facture n est selectionnee (garde)", async () => {
    const { wrapper } = await mountPage(CreancesPage)

    await (wrapper.vm as any).envoyerRelance()

    expect(mockStore).not.toHaveBeenCalled()
  })
})
