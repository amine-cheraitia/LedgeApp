import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types/api-error'

// ── Mock axios : capture les intercepteurs enregistres par le client ─────────
const { mockAxiosGet, mockCreate, captured, fakeInstance } = vi.hoisted(() => {
  const captured: {
    request?: (config: Record<string, unknown>) => Promise<Record<string, unknown>>
    responseOk?: (response: unknown) => unknown
    responseErr?: (error: unknown) => Promise<never>
  } = {}
  const mockAxiosGet = vi.fn()
  const fakeInstance = {
    interceptors: {
      request: {
        use: vi.fn((fn: (c: Record<string, unknown>) => Promise<Record<string, unknown>>) => {
          captured.request = fn
        }),
      },
      response: {
        use: vi.fn((ok: (r: unknown) => unknown, err: (e: unknown) => Promise<never>) => {
          captured.responseOk = ok
          captured.responseErr = err
        }),
      },
    },
  }
  const mockCreate = vi.fn(() => fakeInstance)
  return { mockAxiosGet, mockCreate, captured, fakeInstance }
})

vi.mock('axios', () => ({
  default: { create: mockCreate, get: mockAxiosGet },
}))

// Import APRES le mock : l'instanciation enregistre les intercepteurs.
import apiClient, { resetCsrf } from '@/api/client'

const JSON_HEADERS = { 'content-type': 'application/json' }

/** Rejoue l'intercepteur d'erreur et renvoie l'erreur rejetee (enrichie de apiError). */
async function normalize(raw: unknown): Promise<AxiosError & { apiError: ApiError }> {
  try {
    await captured.responseErr!(raw)
  } catch (e) {
    return e as AxiosError & { apiError: ApiError }
  }
  throw new Error("l'intercepteur aurait du rejeter")
}

beforeEach(() => {
  resetCsrf()
  mockAxiosGet.mockReset()
  mockAxiosGet.mockResolvedValue({})
})

describe('client API — configuration', () => {
  it("cree l'instance axios avec baseURL /api/v1, credentials et timeout", () => {
    expect(mockCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        baseURL: '/api/v1',
        withCredentials: true,
        timeout: 15000,
        headers: expect.objectContaining({ Accept: 'application/json' }),
      }),
    )
    // Le module exporte bien l'instance creee
    expect(apiClient).toBe(fakeInstance)
    expect(captured.request).toBeTypeOf('function')
    expect(captured.responseErr).toBeTypeOf('function')
  })
})

describe('client API — intercepteur requete (CSRF)', () => {
  it('ne recupere pas le cookie CSRF pour une methode non mutante (GET)', async () => {
    const config = await captured.request!({ method: 'get' })

    expect(config).toEqual({ method: 'get' })
    expect(mockAxiosGet).not.toHaveBeenCalled()
  })

  it('ne recupere pas le cookie CSRF quand la methode est absente', async () => {
    await captured.request!({})

    expect(mockAxiosGet).not.toHaveBeenCalled()
  })

  it('recupere le cookie CSRF une seule fois pour les methodes mutantes', async () => {
    await captured.request!({ method: 'post' })
    await captured.request!({ method: 'put' })
    await captured.request!({ method: 'delete' })

    expect(mockAxiosGet).toHaveBeenCalledTimes(1)
    expect(mockAxiosGet).toHaveBeenCalledWith('/sanctum/csrf-cookie', {
      withCredentials: true,
      timeout: 10000,
    })
  })

  it('resetCsrf force une nouvelle recuperation du cookie', async () => {
    await captured.request!({ method: 'post' })
    resetCsrf()
    await captured.request!({ method: 'post' })

    expect(mockAxiosGet).toHaveBeenCalledTimes(2)
  })

  it("laisse partir la requete si le cookie CSRF echoue (catch silencieux) puis reessaie", async () => {
    mockAxiosGet.mockReset()
    mockAxiosGet.mockRejectedValueOnce(new Error('reseau HS')).mockResolvedValueOnce({})

    // L'echec ne remonte pas et la config est renvoyee telle quelle
    const config = await captured.request!({ method: 'post' })
    expect(config).toEqual({ method: 'post' })
    expect(mockAxiosGet).toHaveBeenCalledTimes(1)

    // csrfInitialized est reste false -> la requete mutante suivante reessaie
    await captured.request!({ method: 'patch' })
    expect(mockAxiosGet).toHaveBeenCalledTimes(2)

    // Cette fois le cookie est en place -> plus d'appel
    await captured.request!({ method: 'post' })
    expect(mockAxiosGet).toHaveBeenCalledTimes(2)
  })
})

describe('client API — intercepteur reponse (succes)', () => {
  it('laisse passer les reponses en succes sans modification', () => {
    const response = { status: 200, data: { data: [] } }
    expect(captured.responseOk!(response)).toBe(response)
  })
})

describe('client API — normalisation des erreurs (sans reponse serveur)', () => {
  it('ECONNABORTED -> timeout avec message dedie', async () => {
    const e = await normalize({ code: 'ECONNABORTED' })

    expect(e.apiError).toEqual({
      kind: 'timeout',
      status: null,
      message: 'Le serveur met trop de temps a repondre. Reessayez dans quelques instants.',
    })
  })

  it('erreur sans reponse ni timeout -> network', async () => {
    const e = await normalize({ code: 'ERR_NETWORK' })

    expect(e.apiError.kind).toBe('network')
    expect(e.apiError.status).toBeNull()
    expect(e.apiError.message).toContain('Connexion au serveur impossible')
  })
})

describe('client API — normalisation des erreurs (reponse non JSON)', () => {
  it('5xx non JSON (text/html) -> server avec message generique', async () => {
    const e = await normalize({
      response: { status: 500, headers: { 'content-type': 'text/html' }, data: null },
    })

    expect(e.apiError).toEqual({
      kind: 'server',
      status: 500,
      message: 'Erreur serveur inattendue. Reessayez plus tard.',
    })
    // Le message expose dans response.data est sanitize
    expect((e.response!.data as { message: string }).message).toBe('Erreur serveur inattendue. Reessayez plus tard.')
  })

  it('4xx non JSON (headers vides) -> unknown "reponse inattendue"', async () => {
    const e = await normalize({ response: { status: 404, headers: {}, data: null } })

    expect(e.apiError.kind).toBe('unknown')
    expect(e.apiError.message).toBe('Reponse inattendue du serveur.')
  })

  it('reponse sans headers -> traitee comme non JSON', async () => {
    const e = await normalize({ response: { status: 400, data: null } })

    expect(e.apiError.kind).toBe('unknown')
  })
})

describe('client API — normalisation des erreurs (reponse JSON)', () => {
  it('401 avec message serveur court -> auth avec ce message', async () => {
    const e = await normalize({
      response: { status: 401, headers: JSON_HEADERS, data: { message: 'Non authentifie.' } },
    })

    expect(e.apiError).toEqual({ kind: 'auth', status: 401, message: 'Non authentifie.' })
  })

  it('401 sans payload -> message de repli "Authentification requise."', async () => {
    const e = await normalize({ response: { status: 401, headers: JSON_HEADERS } })

    expect(e.apiError.message).toBe('Authentification requise.')
    // data etait undefined -> le client reconstruit un objet avec le message sanitize
    expect((e.response!.data as { message: string }).message).toBe('Authentification requise.')
  })

  it('message serveur trop long (>= 300 caracteres) -> ignore au profit du repli', async () => {
    const e = await normalize({
      response: { status: 403, headers: JSON_HEADERS, data: { message: 'x'.repeat(300) } },
    })

    expect(e.apiError).toEqual({ kind: 'forbidden', status: 403, message: 'Acces refuse.' })
  })

  it('404 -> notfound', async () => {
    const e = await normalize({
      response: { status: 404, headers: { 'content-type': 'APPLICATION/JSON; charset=utf-8' }, data: {} },
    })

    expect(e.apiError).toEqual({ kind: 'notfound', status: 404, message: 'Ressource introuvable.' })
  })

  it('419 -> csrf, et la prochaine requete mutante reprend le cookie', async () => {
    // Initialise le CSRF via un POST
    await captured.request!({ method: 'post' })
    expect(mockAxiosGet).toHaveBeenCalledTimes(1)

    const e = await normalize({
      response: { status: 419, headers: JSON_HEADERS, data: { message: 'CSRF token mismatch.' } },
    })
    expect(e.apiError).toEqual({
      kind: 'csrf',
      status: 419,
      message: 'Session expiree. Rechargez la page et reessayez.',
    })

    // csrfInitialized a ete remis a false par l'intercepteur
    await captured.request!({ method: 'post' })
    expect(mockAxiosGet).toHaveBeenCalledTimes(2)
  })

  it('422 -> validation avec les erreurs par champ, recopiees dans response.data', async () => {
    const errors = { email: ['Adresse email invalide.'] }
    const e = await normalize({
      response: { status: 422, headers: JSON_HEADERS, data: { message: 'Donnees invalides', errors } },
    })

    expect(e.apiError.kind).toBe('validation')
    expect(e.apiError.status).toBe(422)
    expect(e.apiError.message).toBe('Donnees invalides')
    expect(e.apiError.errors).toEqual(errors)
    const data = e.response!.data as { message: string; errors: Record<string, string[]> }
    expect(data.errors).toEqual(errors)
    expect(data.message).toBe('Donnees invalides')
  })

  it('429 -> throttle', async () => {
    const e = await normalize({ response: { status: 429, headers: JSON_HEADERS, data: {} } })

    expect(e.apiError).toEqual({ kind: 'throttle', status: 429, message: 'Trop de tentatives. Reessayez plus tard.' })
  })

  it('503 -> unavailable', async () => {
    const e = await normalize({ response: { status: 503, headers: JSON_HEADERS, data: {} } })

    expect(e.apiError).toEqual({ kind: 'unavailable', status: 503, message: 'Service temporairement indisponible.' })
  })

  it('500 JSON -> server (message serveur conserve s\'il est court)', async () => {
    const e = await normalize({
      response: { status: 500, headers: JSON_HEADERS, data: { message: 'Boom SQL' } },
    })

    expect(e.apiError).toEqual({ kind: 'server', status: 500, message: 'Boom SQL' })
  })

  it('502 JSON sans message -> server generique (branche defaut >= 500)', async () => {
    const e = await normalize({ response: { status: 502, headers: JSON_HEADERS, data: {} } })

    expect(e.apiError).toEqual({ kind: 'server', status: 502, message: 'Erreur serveur. Reessayez plus tard.' })
  })

  it('statut inattendu (400) -> unknown avec message serveur', async () => {
    const e = await normalize({
      response: { status: 400, headers: JSON_HEADERS, data: { message: 'Requete malformee.' } },
    })

    expect(e.apiError).toEqual({ kind: 'unknown', status: 400, message: 'Requete malformee.' })
  })

  it('message non textuel dans le payload -> repli generique', async () => {
    const e = await normalize({
      response: { status: 400, headers: JSON_HEADERS, data: { message: 12345 } },
    })

    expect(e.apiError.message).toBe('Une erreur est survenue.')
  })
})
