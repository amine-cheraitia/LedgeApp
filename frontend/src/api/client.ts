import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
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
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
    csrfInitialized = true
  }
  return config
})

// Intercepteur réponse : gestion erreurs globales
// Les 401 sont gérés par le router guard (beforeEach) et fetchUser()
// Ne JAMAIS faire window.location.href ici : ça recharge la page et vide Pinia
api.interceptors.response.use(
  (response) => response,
  (error) => Promise.reject(error)
)

export default api
