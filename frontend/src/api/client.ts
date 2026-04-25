import axios, { AxiosError, type AxiosResponse } from 'axios'
import type { ApiError } from '@/types/api-error'

const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  timeout: 15000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

let csrfInitialized = false

export function resetCsrf(): void {
  csrfInitialized = false
}

api.interceptors.request.use(async (config) => {
  const mutatingMethods = ['post', 'put', 'patch', 'delete']
  if (mutatingMethods.includes(config.method ?? '') && !csrfInitialized) {
    try {
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true, timeout: 10000 })
      csrfInitialized = true
    } catch {
      // Si le CSRF ne peut pas etre recupere, on laisse partir : l'erreur reseau
      // sera capturee par l'intercepteur reponse avec un message clair.
    }
  }
  return config
})

function isJsonResponse(response: AxiosResponse | undefined): boolean {
  if (!response) return false
  const contentType = (response.headers?.['content-type'] ?? '') as string
  return contentType.toLowerCase().includes('application/json')
}

function normalizeApiError(error: AxiosError): ApiError {
  if (!error.response) {
    if (error.code === 'ECONNABORTED') {
      return {
        kind: 'timeout',
        status: null,
        message: 'Le serveur met trop de temps a repondre. Reessayez dans quelques instants.',
      }
    }
    return {
      kind: 'network',
      status: null,
      message: 'Connexion au serveur impossible. Verifiez votre connexion ou reessayez plus tard.',
    }
  }

  const status = error.response.status

  if (!isJsonResponse(error.response)) {
    return {
      kind: status >= 500 ? 'server' : 'unknown',
      status,
      message: status >= 500
        ? 'Erreur serveur inattendue. Reessayez plus tard.'
        : 'Reponse inattendue du serveur.',
    }
  }

  const payload = (error.response.data ?? {}) as { message?: string; errors?: Record<string, string[]> }
  const serverMessage = typeof payload.message === 'string' && payload.message.length > 0 && payload.message.length < 300
    ? payload.message
    : ''

  switch (status) {
    case 401:
      return { kind: 'auth', status, message: serverMessage || 'Authentification requise.' }
    case 403:
      return { kind: 'forbidden', status, message: serverMessage || 'Acces refuse.' }
    case 404:
      return { kind: 'notfound', status, message: serverMessage || 'Ressource introuvable.' }
    case 419:
      return { kind: 'csrf', status, message: 'Session expiree. Rechargez la page et reessayez.' }
    case 422:
      return {
        kind: 'validation',
        status,
        message: serverMessage || 'Donnees invalides.',
        errors: payload.errors,
      }
    case 429:
      return { kind: 'throttle', status, message: serverMessage || 'Trop de tentatives. Reessayez plus tard.' }
    case 503:
      return { kind: 'unavailable', status, message: serverMessage || 'Service temporairement indisponible.' }
    default:
      if (status >= 500) {
        return { kind: 'server', status, message: serverMessage || 'Erreur serveur. Reessayez plus tard.' }
      }
      return { kind: 'unknown', status, message: serverMessage || 'Une erreur est survenue.' }
  }
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const apiError = normalizeApiError(error)

    // Le CSRF expire (419) : on reset pour qu'une nouvelle requete mutante reprenne le cookie.
    if (apiError.kind === 'csrf') {
      csrfInitialized = false
    }

    // Conserve la forme attendue par le code existant (e.response?.data?.message)
    // tout en sanitizant le message expose.
    if (error.response) {
      const data = (error.response.data ?? {}) as Record<string, unknown>
      data.message = apiError.message
      if (apiError.errors) data.errors = apiError.errors
      error.response.data = data
    }

    // Attache la version typee pour le code qui veut un acces structure.
    ;(error as AxiosError & { apiError: ApiError }).apiError = apiError

    return Promise.reject(error)
  }
)

export default api
