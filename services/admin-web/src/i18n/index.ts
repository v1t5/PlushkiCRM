// Vue-i18n setup. Two locales for now: English (default) and Russian.
// The chosen locale persists in localStorage under `plushki-admin-locale-v1`
// (bumping the version key invalidates a stale persisted choice without
// needing a migration). format.ts also reads the active locale from this
// instance so date/money rendering follows the UI language.
import { createI18n } from 'vue-i18n'
import en from './locales/en'
import ru from './locales/ru'

export const SUPPORTED_LOCALES = ['en', 'ru'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

const STORAGE_KEY = 'plushki-admin-locale-v1'

function detectInitialLocale(): Locale {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored && (SUPPORTED_LOCALES as readonly string[]).includes(stored)) {
      return stored as Locale
    }
  } catch {
    // ignore — private browsing modes can throw on localStorage access
  }
  const nav = (typeof navigator !== 'undefined' ? navigator.language : '').toLowerCase()
  if (nav.startsWith('ru')) return 'ru'
  return 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: detectInitialLocale(),
  fallbackLocale: 'en',
  messages: { en, ru },
})

export function setLocale(locale: Locale) {
  i18n.global.locale.value = locale
  try {
    localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    // private browsing modes can throw on localStorage access
  }
  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale
  }
}

export function currentLocale(): Locale {
  return i18n.global.locale.value as Locale
}

// Map our app locale → BCP-47 tag for Intl APIs (toLocaleString, etc.).
export function intlLocale(): string {
  return currentLocale() === 'ru' ? 'ru-RU' : 'en-US'
}
