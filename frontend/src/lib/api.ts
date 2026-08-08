import axios from 'axios'

/**
 * Central Axios instance for all IronDesk API calls.
 *
 * Base URL points to the Laravel backend. In development Vite proxies
 * /api/* to http://localhost:8000 (configured in vite.config.ts).
 *
 * When auth is added, this is the single place to attach the
 * Sanctum CSRF cookie interceptor and 401 redirect logic.
 */
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,       // required for Sanctum SPA cookie auth
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// ── Response interceptor ──────────────────────────────────────────────────────
// Normalise errors so every caller gets a consistent shape:
//   error.response.data.error.message  — human-readable
//   error.response.data.error.code     — machine-readable
// The interceptor re-throws so individual stores/views can still catch.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // TODO: when auth is added, redirect to /login on 401
    return Promise.reject(error)
  },
)

export default api
