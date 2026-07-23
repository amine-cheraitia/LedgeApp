import api from '@/api/client'

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export const authApi = {
  /** Demande un lien de reinitialisation (libre-service). Reponse generique cote back. */
  forgotPassword(email: string): Promise<{ message: string }> {
    return api.post('/forgot-password', { email }).then(r => r.data)
  },

  /** Definit le mot de passe via un jeton a usage unique (invitation ou reinitialisation). */
  resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
    return api.post('/reset-password', payload).then(r => r.data)
  },
}
