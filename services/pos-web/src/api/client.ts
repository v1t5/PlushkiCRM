// Thin fetch wrapper. The browser hits the gateway at the same origin
// as the SPA: in dev that's the Vite proxy at :5173 forwarding /api/*
// to :8080, in prod the central Caddy serves both the SPA and /api/*
// from :8080. So all requests use a relative URL — no base URL config.

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

export async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(path, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init.headers ?? {}),
    },
  })
  if (!res.ok) {
    // Backend services emit RFC 7807 problem+json on errors. Try to
    // surface their type/detail so the UI has something useful to log.
    let type = 'unknown'
    let title = `HTTP ${res.status}`
    let detail = ''
    try {
      const body = await res.json()
      type = body.type ?? type
      title = body.title ?? title
      detail = body.detail ?? ''
    } catch {
      // Non-JSON error body — keep defaults.
    }
    throw new ApiError(res.status, type, title, detail)
  }
  if (res.status === 204) {
    return undefined as T
  }
  return (await res.json()) as T
}
