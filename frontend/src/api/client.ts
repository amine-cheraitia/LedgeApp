import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Intercepteur : CSRF cookie avant les requêtes mutantes
let csrfInitialized = false

api.interceptors.request.use(async (config) => {
  const mutatingMethods = ['post', 'put', 'patch', 'delete']
  if (mutatingMethods.includes(config.method ?? '') && !csrfInitialized) {
    await axios.get(
      (import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000') + '/sanctum/csrf-cookie',
      { withCredentials: true }
    )
    csrfInitialized = true
  }
  return config
})

// Intercepteur réponse : gestion erreurs globales
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirection vers login si non authentifié
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
