import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import type { Tache, TacheCommentaire } from '@/types'

// ── Mocks API (hoistes) ──────────────────────────────────────────────────────
const { tGetOne, tUpdate, tConflits, comGetAll, comCreate, comUpdate, comDelete, usersGetAll } = vi.hoisted(() => ({
  tGetOne: vi.fn(),
  tUpdate: vi.fn(),
  tConflits: vi.fn(),
  comGetAll: vi.fn(),
  comCreate: vi.fn(),
  comUpdate: vi.fn(),
  comDelete: vi.fn(),
  usersGetAll: vi.fn(),
}))

vi.mock('@/api/modules/taches', () => ({
  tachesApi: {
    getOne: tGetOne,
    update: tUpdate,
    conflits: tConflits,
    getAll: vi.fn(),
    create: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/api/modules/commentaires', () => ({
  commentairesApi: {
    getAll: comGetAll,
    create: comCreate,
    update: comUpdate,
    delete: comDelete,
  },
}))

vi.mock('@/api/modules/users', () => ({
  usersApi: { getAll: usersGetAll },
}))

// ── Mocks PrimeVue services ──────────────────────────────────────────────────
const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))

const confirmRequire = vi.fn()
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))

// ── Import de la page APRES les mocks ────────────────────────────────────────
import TacheDetailPage from '@/pages/missions/TacheDetailPage.vue'
import { mountPage, findButton, makeUser } from '../helpers/mount'
import type { MountPageOptions } from '../helpers/mount'

// ── Fixtures ─────────────────────────────────────────────────────────────────
const tacheBase: Tache = {
  id: 3,
  mission_id: 5,
  assigned_to: 2,
  titre: 'Saisie comptable',
  description: 'Saisir les pièces du premier trimestre',
  statut: 'a_faire',
  date_debut: '2026-03-01',
  date_echeance: '2026-03-15',
  priorite: 3,
  assignee: makeUser('collaborateur', { id: 2, name: 'Karim Benali' }),
  created_at: '2026-02-01T10:00:00.000000Z',
  updated_at: '2026-02-01T10:00:00.000000Z',
}

const commentairesFixture: TacheCommentaire[] = [
  {
    id: 11, tache_id: 3, user_id: 1, contenu: 'Premier commentaire',
    visible_portail: true, user: { id: 1, name: 'Test User' },
    created_at: '2026-03-02T09:30:00.000000Z', updated_at: '2026-03-02T09:30:00.000000Z',
  },
  {
    id: 12, tache_id: 3, user_id: 2, contenu: 'Réponse du collaborateur',
    visible_portail: false, user: { id: 2, name: 'Karim Benali' },
    created_at: '2026-03-03T14:00:00.000000Z', updated_at: '2026-03-03T14:00:00.000000Z',
  },
]

/**
 * Monte la page sur /missions/5/taches/3 et enregistre la route nommee
 * 'mission-detail' AVANT le flush (necessaire au router.push de loadTache/breadcrumb).
 */
async function mountDetail(options: MountPageOptions = {}) {
  const result = await mountPage(TacheDetailPage, {
    path: '/missions/5/taches/3',
    routePattern: '/missions/:id/taches/:tacheId',
    skipFlush: true,
    ...options,
  })
  result.router.addRoute({ path: '/missions/:id', name: 'mission-detail', component: { template: '<div />' } })
  if (!options.skipFlush) await flushPromises()
  return result
}

beforeEach(() => {
  vi.clearAllMocks()
  tGetOne.mockResolvedValue({ data: { ...tacheBase } })
  tConflits.mockResolvedValue({ data: [] })
  comGetAll.mockResolvedValue({ data: commentairesFixture })
  usersGetAll.mockResolvedValue({
    data: [makeUser('admin'), makeUser('collaborateur', { id: 2, name: 'Karim Benali' })],
    meta: { total: 2 },
  })
})

describe('TacheDetailPage — affichage du détail', () => {
  it('charge la tâche depuis les params de route et affiche titre, statut, priorité, assigné', async () => {
    const { wrapper } = await mountDetail()

    expect(tGetOne).toHaveBeenCalledWith(5, 3)
    expect(wrapper.text()).toContain('Saisie comptable')
    expect(wrapper.text()).toContain('À faire')            // statutLabel
    expect(wrapper.text()).toContain('Haute')              // prioriteLabel(3)
    expect(wrapper.text()).toContain('Karim Benali')       // assignee
    expect(wrapper.text()).toContain('KB')                 // initiales
    expect(wrapper.text()).toContain('Saisir les pièces du premier trimestre')
    expect(wrapper.text()).toContain('/2026')              // dates formatées fr-FR
    // Staff assignable chargé pour le dialog d'édition (admin)
    expect(usersGetAll).toHaveBeenCalledWith(expect.objectContaining({ role: ['admin', 'collaborateur'] }))
  })

  it('affiche les commentaires, le compteur accessible et le badge portail pour un admin', async () => {
    const { wrapper } = await mountDetail()

    expect(comGetAll).toHaveBeenCalledWith(3)
    expect(wrapper.text()).toContain('Premier commentaire')
    expect(wrapper.text()).toContain('Réponse du collaborateur')
    const badge = wrapper.find('[role="status"]')
    expect(badge.attributes('aria-label')).toBe('2 commentaire(s)')
    expect(badge.text()).toBe('2')
    // visible_portail + admin -> tag "portail"
    expect(wrapper.text()).toContain('portail')
  })

  it('gère les données absentes : non assignée, dates vides, auteur inconnu, liste vide', async () => {
    tGetOne.mockResolvedValue({
      data: { ...tacheBase, assigned_to: null, assignee: undefined, description: null, date_debut: null, date_echeance: null },
    })
    comGetAll.mockResolvedValue({ data: [] })
    const { wrapper } = await mountDetail()

    expect(wrapper.text()).toContain('Non assignée')
    expect(wrapper.text()).toContain('—')
    expect(wrapper.text()).toContain('Aucun commentaire pour le moment.')
  })

  it('affiche le loader pendant le chargement puis le retire', async () => {
    let resolveTache!: (v: { data: Tache }) => void
    tGetOne.mockReturnValue(new Promise(r => { resolveTache = r }))
    const { wrapper } = await mountDetail({ skipFlush: true })

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)

    resolveTache({ data: { ...tacheBase } })
    await flushPromises()
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Saisie comptable')
  })

  it('tâche introuvable : toast erreur et redirection vers la mission', async () => {
    // Rejet différé : la route nommée 'mission-detail' doit être enregistrée
    // (dans mountDetail) avant que le catch de loadTache ne déclenche le push.
    let rejectTache!: (e: unknown) => void
    tGetOne.mockReturnValue(new Promise((_, rej) => { rejectTache = rej }))
    const { router } = await mountDetail({ skipFlush: true })

    rejectTache(new Error('404'))
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Tâche introuvable.' }),
    )
    expect(router.currentRoute.value.name).toBe('mission-detail')
    expect(router.currentRoute.value.params.id).toBe('5')
  })

  it('le fil d’Ariane renvoie vers la mission', async () => {
    const { wrapper, router } = await mountDetail()

    expect(findButton(wrapper, 'Retour à la mission')).toBeTruthy()
    await wrapper.find('.breadcrumb-mission').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('mission-detail')
  })
})

describe('TacheDetailPage — rôles et permissions', () => {
  it('collaborateur non assigné : ni édition, ni changement de statut, ni commentaire', async () => {
    const { wrapper } = await mountDetail({ role: 'collaborateur', user: { id: 9, name: 'Autre Collab' } })

    expect(findButton(wrapper, 'Modifier cette tâche')).toBeUndefined()
    // Statut en simple Tag (pas de Select editable)
    expect(wrapper.find('[aria-label*="Statut de la tâche"]').exists()).toBe(false)
    // Formulaire de commentaire masqué
    expect(wrapper.find('#nouveau-commentaire').exists()).toBe(false)
    // Aucun commentaire modifiable (ni auteur ni admin)
    expect(findButton(wrapper, 'Modifier le commentaire')).toBeUndefined()
    // La liste des assignables n'est pas chargée pour un collaborateur
    expect(usersGetAll).not.toHaveBeenCalled()
  })

  it('collaborateur assigné : peut changer le statut, commenter et modifier SES commentaires', async () => {
    const { wrapper } = await mountDetail({ role: 'collaborateur', user: { id: 2, name: 'Karim Benali' } })

    expect(wrapper.find('[aria-label*="Statut de la tâche"]').exists()).toBe(true)
    expect(wrapper.find('#nouveau-commentaire').exists()).toBe(true)
    // Pas de bouton d'édition de la tâche pour un collaborateur
    expect(findButton(wrapper, 'Modifier cette tâche')).toBeUndefined()
    // Peut modifier son propre commentaire mais pas celui d'un autre
    expect(findButton(wrapper, 'Modifier le commentaire de Karim Benali')).toBeTruthy()
    expect(findButton(wrapper, 'Modifier le commentaire de Test User')).toBeUndefined()
    // Badge portail réservé à l'admin
    expect(wrapper.text()).not.toContain('portail')
  })

  it('secrétaire : statut éditable mais ne peut pas commenter', async () => {
    const { wrapper } = await mountDetail({ role: 'secretaire' })

    expect(wrapper.find('[aria-label*="Statut de la tâche"]').exists()).toBe(true)
    expect(wrapper.find('#nouveau-commentaire').exists()).toBe(false)
  })
})

describe('TacheDetailPage — changement de statut', () => {
  it('met à jour le statut via l’API et affiche le nouveau tag', async () => {
    tUpdate.mockResolvedValue({ data: { ...tacheBase, statut: 'terminee' } })
    const { wrapper } = await mountDetail()

    await (wrapper.vm as any).changerStatut('terminee')
    await flushPromises()

    expect(tUpdate).toHaveBeenCalledWith(5, 3, { statut: 'terminee' })
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Statut mis à jour.' }))
    expect(wrapper.text()).toContain('Terminée')
  })

  it('affiche un toast erreur si la mise à jour échoue', async () => {
    tUpdate.mockRejectedValue(new Error('500'))
    const { wrapper } = await mountDetail()

    await (wrapper.vm as any).changerStatut('bloquee')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Erreur lors de la mise à jour.' }),
    )
    // Le statut affiché reste inchangé
    expect(wrapper.text()).toContain('À faire')
  })
})

describe('TacheDetailPage — commentaires', () => {
  it('envoie un nouveau commentaire et reflète la transition a_faire → en_cours', async () => {
    comCreate.mockResolvedValue({ data: { ...commentairesFixture[0], id: 99 } })
    const { wrapper } = await mountDetail()

    await wrapper.find('#nouveau-commentaire').setValue('Nouveau message')
    await wrapper.find('form.nouveau-commentaire-form').trigger('submit')
    await flushPromises()

    expect(comCreate).toHaveBeenCalledWith(3, 'Nouveau message')
    // refetch : montage + apres createCommentaire (interne) + apres envoi (page)
    expect(comGetAll.mock.calls.length).toBeGreaterThanOrEqual(2)
    // Le champ est vidé et le statut passe visuellement en cours
    expect((wrapper.find('#nouveau-commentaire').element as HTMLTextAreaElement).value).toBe('')
    expect(wrapper.text()).toContain('En cours')
  })

  it('ignore un commentaire vide (espaces uniquement)', async () => {
    const { wrapper } = await mountDetail()

    await wrapper.find('#nouveau-commentaire').setValue('   ')
    await wrapper.find('form.nouveau-commentaire-form').trigger('submit')
    await flushPromises()

    expect(comCreate).not.toHaveBeenCalled()
  })

  it('édite un commentaire : textarea pré-rempli puis sauvegarde', async () => {
    comUpdate.mockResolvedValue({ data: commentairesFixture[0] })
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Modifier le commentaire de Test User')!.trigger('click')

    const textarea = wrapper.find('#edit-11')
    expect((textarea.element as HTMLTextAreaElement).value).toBe('Premier commentaire')

    await textarea.setValue('Contenu corrigé')
    await findButton(wrapper, 'Sauvegarder')!.trigger('click')
    await flushPromises()

    expect(comUpdate).toHaveBeenCalledWith(3, 11, 'Contenu corrigé')
    // Sortie du mode édition
    expect(wrapper.find('#edit-11').exists()).toBe(false)
  })

  it("annule l'édition d'un commentaire sans appeler l'API", async () => {
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Modifier le commentaire de Test User')!.trigger('click')
    expect(wrapper.find('#edit-11').exists()).toBe(true)

    await findButton(wrapper, 'Annuler')!.trigger('click')

    expect(wrapper.find('#edit-11').exists()).toBe(false)
    expect(comUpdate).not.toHaveBeenCalled()
  })

  it('supprime un commentaire après confirmation', async () => {
    comDelete.mockResolvedValue(undefined)
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Supprimer le commentaire de Test User')!.trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    const config = confirmRequire.mock.calls[0][0]
    expect(config.message).toBe('Supprimer ce commentaire ?')

    await config.accept()
    await flushPromises()

    expect(comDelete).toHaveBeenCalledWith(3, 11)
    expect(comGetAll.mock.calls.length).toBeGreaterThanOrEqual(2)
  })
})

describe('TacheDetailPage — édition de la tâche', () => {
  it('ouvre le dialog pré-rempli et soumet avec les dates ISO bornées', async () => {
    tUpdate.mockResolvedValue({ data: { ...tacheBase, titre: 'Titre modifié' } })
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Modifier cette tâche')!.trigger('click')
    await flushPromises()

    const titre = wrapper.find('#e-titre')
    expect((titre.element as HTMLInputElement).value).toBe('Saisie comptable')
    expect(wrapper.find('#e-debut').exists()).toBe(true)
    expect(wrapper.find('#e-echeance').exists()).toBe(true)

    await titre.setValue('Titre modifié')
    await wrapper.find('form.dialog-form').trigger('submit')
    await flushPromises()

    // Dates renvoyées au format YYYY-MM-DD sans décalage de fuseau
    expect(tUpdate).toHaveBeenCalledWith(5, 3, expect.objectContaining({
      titre: 'Titre modifié',
      assigned_to: 2,
      priorite: 3,
      date_debut: '2026-03-01',
      date_echeance: '2026-03-15',
    }))
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success', detail: 'Tâche mise à jour.' }))
    // Dialog fermé + titre rafraîchi
    expect(wrapper.find('#e-titre').exists()).toBe(false)
    expect(wrapper.text()).toContain('Titre modifié')
  })

  it("reste ouvert avec un toast erreur si l'API échoue", async () => {
    tUpdate.mockRejectedValue(new Error('422'))
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Modifier cette tâche')!.trigger('click')
    await wrapper.find('form.dialog-form').trigger('submit')
    await flushPromises()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Impossible de modifier la tâche.' }),
    )
    expect(wrapper.find('#e-titre').exists()).toBe(true)
  })

  it('affiche l’avertissement de conflit d’affectation (non bloquant) à l’ouverture', async () => {
    tConflits.mockResolvedValue({
      data: [{
        id: 40, titre: 'Autre tâche urgente',
        date_debut: '2026-03-05', date_echeance: '2026-03-10',
        mission: { id: 9, reference: 'M2026-002' },
      }],
    })
    const { wrapper } = await mountDetail()

    await findButton(wrapper, 'Modifier cette tâche')!.trigger('click')
    // Debounce de 400ms dans useTacheConflits
    await new Promise(r => setTimeout(r, 450))
    await flushPromises()

    expect(tConflits).toHaveBeenCalledWith(expect.objectContaining({
      collaborateur_id: 2,
      date_debut: '2026-03-01',
      date_echeance: '2026-03-15',
      exclude_tache_id: 3,
    }))
    expect(wrapper.text()).toContain('chevauchent cette période')
    expect(wrapper.text()).toContain('Autre tâche urgente')
    expect(wrapper.text()).toContain('M2026-002')
  })
})
