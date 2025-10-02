import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login as apiLogin, type User, type TokenPair } from '../api/identity'
import { setTokenProvider } from '../api/client'

const STORAGE_KEY = 'plushki-admin-auth-v1'

type Persisted = {
  user: User
  tokens: TokenPair
}

function loadPersisted(): Persisted | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    return JSON.parse(raw) as Persisted
  } catch {
    return null
  }
}

// useAuth holds the currently logged-in user + tokens. Access token is
// also exposed to the API client via setTokenProvider so all /api calls
// auto-attach Authorization. Persistence is localStorage keyed
// 'plushki-admin-auth-v1' — bumping the version invalidates stale
// sessions without needing a migration.
export const useAuth = defineStore('auth', () => {
  const persisted = loadPersisted()
  const user = ref<User | null>(persisted?.user ?? null)
  const tokens = ref<TokenPair | null>(persisted?.tokens ?? null)

  const accessToken = computed(() => tokens.value?.access_token ?? null)
  const isAuthed = computed(() => !!accessToken.value)
  const roles = computed(() => user.value?.roles ?? [])
  const isAdmin = computed(() => roles.value.includes('admin'))

  function hasRole(role: string): boolean {
    return roles.value.includes(role)
  }

  // Wire the api client. Single source of truth for the bearer.
  setTokenProvider(() => accessToken.value)

  function persist() {
    if (user.value && tokens.value) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ user: user.value, tokens: tokens.value }))
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  async function login(email: string, password: string) {
    const resp = await apiLogin(email, password)
    user.value = resp.user
    tokens.value = resp.tokens
    persist()
  }

  function logout() {
    user.value = null
    tokens.value = null
    persist()
  }

  return { user, tokens, accessToken, isAuthed, roles, isAdmin, hasRole, login, logout }
})
