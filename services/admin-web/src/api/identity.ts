import { request } from './client'

// Identity service DTOs. Shapes match the service's HTTP DTOs.

export type User = {
  id: string
  tenant_id: string
  email: string
  display_name: string
  roles: string[]
}

export type TokenPair = {
  access_token: string
  access_expiry: string
  refresh_token: string
  refresh_expiry: string
  token_type: string
}

export type LoginResp = {
  user: User
  tokens: TokenPair
}

export function login(email: string, password: string) {
  return request<LoginResp>('/api/identity/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })
}

export function register(email: string, password: string, display_name: string) {
  return request<LoginResp>('/api/identity/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email, password, display_name }),
  })
}

export function me() {
  return request<User>('/api/identity/me')
}

export function refresh(refresh_token: string) {
  return request<LoginResp>('/api/identity/auth/refresh', {
    method: 'POST',
    body: JSON.stringify({ refresh_token }),
  })
}
