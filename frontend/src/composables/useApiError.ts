import { AxiosError } from 'axios'
import type { ApiError } from '@/types/api-error'

const FALLBACK = 'Une erreur est survenue.'

export function getApiError(e: unknown): ApiError {
  if (e instanceof AxiosError && (e as AxiosError & { apiError?: ApiError }).apiError) {
    return (e as AxiosError & { apiError: ApiError }).apiError
  }
  return { kind: 'unknown', status: null, message: FALLBACK }
}

export function getApiErrorMessage(e: unknown, fallback: string = FALLBACK): string {
  const err = getApiError(e)
  return err.message || fallback
}
