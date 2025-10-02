// Cross-view formatters. Money is stored as int64 kopecks across the
// backend; the SPA renders it as ₽ at presentation time only — no float
// math anywhere. Locale follows the active i18n locale so number/date
// separators match the UI language.
import { intlLocale } from './i18n'

export function formatKopecks(k: number): string {
  const sign = k < 0 ? '-' : ''
  const abs = Math.abs(k)
  const rub = Math.floor(abs / 100)
  const kop = abs % 100
  return `${sign}${rub.toLocaleString(intlLocale())}.${kop.toString().padStart(2, '0')} ₽`
}

export function formatPercent(frac: number): string {
  return `${(frac * 100).toFixed(1)}%`
}

export function formatDate(iso: string): string {
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleDateString(intlLocale())
}

export function formatDateTime(iso: string): string {
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleString(intlLocale())
}

// todayISO returns YYYY-MM-DD in UTC, matching reporting's day key.
export function todayISO(): string {
  const d = new Date()
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()))
    .toISOString()
    .slice(0, 10)
}

// daysAgoISO returns YYYY-MM-DD in UTC, n days before today.
export function daysAgoISO(n: number): string {
  const d = new Date()
  const utc = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()))
  utc.setUTCDate(utc.getUTCDate() - n)
  return utc.toISOString().slice(0, 10)
}
