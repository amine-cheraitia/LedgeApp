import { describe, it, expect } from 'vitest'
import { AxiosError } from 'axios'
import { getApiError, getApiErrorMessage } from '@/composables/useApiError'
import type { ApiError } from '@/types/api-error'

function axiosErrorWith(apiError: ApiError): AxiosError {
  const err = new AxiosError('boom') as AxiosError & { apiError: ApiError }
  err.apiError = apiError
  return err
}

describe('getApiError', () => {
  it('retourne le apiError attache a une AxiosError', () => {
    const err = axiosErrorWith({ kind: 'validation', status: 422, message: 'Champ requis' })

    expect(getApiError(err)).toEqual({ kind: 'validation', status: 422, message: 'Champ requis' })
  })

  it('retourne un fallback unknown pour une AxiosError sans apiError', () => {
    const result = getApiError(new AxiosError('boom'))

    expect(result.kind).toBe('unknown')
    expect(result.status).toBeNull()
    expect(result.message).toBe('Une erreur est survenue.')
  })

  it('retourne un fallback unknown pour une erreur non-axios', () => {
    expect(getApiError(new Error('plain')).kind).toBe('unknown')
  })
})

describe('getApiErrorMessage', () => {
  it('retourne le message du apiError quand present', () => {
    const err = axiosErrorWith({ kind: 'server', status: 500, message: 'Erreur serveur' })

    expect(getApiErrorMessage(err)).toBe('Erreur serveur')
  })

  it('utilise le fallback fourni quand le message du apiError est vide', () => {
    const err = axiosErrorWith({ kind: 'server', status: 500, message: '' })

    expect(getApiErrorMessage(err, 'Impossible de charger')).toBe('Impossible de charger')
  })

  it('retombe sur le message global par defaut pour une erreur non-axios', () => {
    expect(getApiErrorMessage(new Error('x'))).toBe('Une erreur est survenue.')
  })
})
