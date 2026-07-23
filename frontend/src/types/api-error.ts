export type ApiErrorKind =
  | 'network'
  | 'timeout'
  | 'validation'
  | 'auth'
  | 'forbidden'
  | 'notfound'
  | 'csrf'
  | 'throttle'
  | 'server'
  | 'unavailable'
  | 'unknown'

export interface ApiError {
  kind: ApiErrorKind
  status: number | null
  message: string
  errors?: Record<string, string[]>
}
