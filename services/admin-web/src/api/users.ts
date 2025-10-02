import { request } from './client'

// Identity admin endpoints.
// All endpoints under /admin/users are admin-only; the SPA gates the routes
// with `meta.adminOnly`, but the gate of record is the JWT roles[] claim
// the identity service enforces server-side.

export type AdminUser = {
  id: string
  tenant_id: string
  email: string
  display_name: string
  roles: string[]
  created_at: string
  archived_at?: string | null
}

export type ListUsersParams = {
  q?: string
  limit?: number
  offset?: number
  include_archived?: boolean
}

export function listUsers(params: ListUsersParams = {}) {
  const qs = new URLSearchParams()
  if (params.q) qs.set('q', params.q)
  if (params.limit !== undefined) qs.set('limit', String(params.limit))
  if (params.offset !== undefined) qs.set('offset', String(params.offset))
  if (params.include_archived) qs.set('include_archived', 'true')
  const tail = qs.toString()
  const path = tail ? `/api/identity/admin/users?${tail}` : '/api/identity/admin/users'
  return request<AdminUser[]>(path)
}

export function getUser(id: string) {
  return request<AdminUser>(`/api/identity/admin/users/${encodeURIComponent(id)}`)
}

export function createUser(body: {
  email: string
  password: string
  display_name: string
  roles?: string[]
}) {
  return request<AdminUser>('/api/identity/admin/users', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function updateUserProfile(id: string, display_name: string) {
  return request<AdminUser>(`/api/identity/admin/users/${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: JSON.stringify({ display_name }),
  })
}

export function updateUserRoles(id: string, roles: string[]) {
  return request<AdminUser>(`/api/identity/admin/users/${encodeURIComponent(id)}/roles`, {
    method: 'PUT',
    body: JSON.stringify({ roles }),
  })
}

export function resetUserPassword(id: string, password: string) {
  return request<void>(`/api/identity/admin/users/${encodeURIComponent(id)}/password`, {
    method: 'PUT',
    body: JSON.stringify({ password }),
  })
}

export function archiveUser(id: string) {
  return request<AdminUser>(`/api/identity/admin/users/${encodeURIComponent(id)}/archive`, {
    method: 'POST',
  })
}

export function restoreUser(id: string) {
  return request<AdminUser>(`/api/identity/admin/users/${encodeURIComponent(id)}/restore`, {
    method: 'POST',
  })
}

// Mirrors the identity service's allowed-roles set. Drift here doesn't
// break the app — the server still validates — but the role multi-select
// needs the list to render checkboxes.
export const ALLOWED_ROLES = ['user', 'admin', 'baker', 'cashier'] as const
export type AllowedRole = (typeof ALLOWED_ROLES)[number]
