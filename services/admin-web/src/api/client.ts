// Thin fetch wrapper. The browser hits the gateway at the same origin
// as the SPA: in dev that's the Vite proxy at :5174 forwarding /api/*
// to :8080, in prod the central Caddy serves both the SPA at /admin/ and
// /api/* from :8080. So all requests use a relative URL — no base URL.
//
// admin-web auth is bearer-token based. The login flow stores the access
// token in the Pinia auth store, which wires setTokenProvider at startup
// so the request helper can fetch the current token without importing
// the store directly (avoids circular dependency).

export class ApiError extends Error {
  status: number
  type: string
  detail: string

  constructor(status: number, type: string, title: string, detail: string) {
    super(title)
    this.status = status
    this.type = type
    this.detail = detail
  }
}

let tokenProvider: () => string | null = () => null

export function setTokenProvider(fn: () => string | null) {
  tokenProvider = fn
}

let unauthorizedHandler: () => void = () => {}

// setUnauthorizedHandler lets the router redirect to /login on 401 from
// any endpoint without each caller having to special-case it.
export function setUnauthorizedHandler(fn: () => void) {
  unauthorizedHandler = fn
}

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = tokenProvider()
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...((init.headers as Record<string, string>) ?? {}),
  }
  if (token) headers['Authorization'] = `Bearer ${token}`

  const res = await fetch(path, { ...init, headers })

  if (res.status === 401) {
    unauthorizedHandler()
  }

  if (!res.ok) {
    let type = 'unknown'
    let title = `HTTP ${res.status}`
    let detail = ''
    try {
      const body = await res.json()
      type = body.type ?? type
      title = body.title ?? title
      detail = body.detail ?? ''
    } catch {
      // Non-JSON body — keep defaults.
    }
    throw new ApiError(res.status, type, title, detail)
  }
  if (res.status === 204) {
    return undefined as T
  }
  return (await res.json()) as T
}
