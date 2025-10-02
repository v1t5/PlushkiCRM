import { request } from './client'

// Shapes match the production service's HTTP DTOs.

export type PlanItem = {
  id: string
  product_id: string
  qty: number
  created_at: string
  updated_at: string
}

export type Plan = {
  id: string
  tenant_id: string
  plan_date: string // YYYY-MM-DD
  status: string // draft | published
  published_at?: string | null
  items: PlanItem[]
  created_at: string
  updated_at: string
}

export type Task = {
  id: string
  tenant_id: string
  plan_id: string
  product_id: string
  qty: number
  status: string // open | in_progress | completed | cancelled
  baker_id?: string | null
  started_at?: string | null
  completed_at?: string | null
  created_at: string
  updated_at: string
}

export type PublishResp = {
  plan: Plan
  tasks: Task[]
}

function items<T>(p: Promise<{ items: T[] }>) {
  return p.then((r) => r.items)
}

// getPlan returns the plan for the given YYYY-MM-DD or 404 (we surface
// the 404 as null so the view can render an empty state without an
// error banner).
export async function getPlan(date: string): Promise<Plan | null> {
  try {
    return await request<Plan>(`/api/production/v1/plans/${date}`)
  } catch (err) {
    if ((err as { status?: number }).status === 404) return null
    throw err
  }
}

export function publishPlan(date: string) {
  return request<PublishResp>(`/api/production/v1/plans/${date}/publish`, {
    method: 'POST',
  })
}

export function listTasks(opts: { plan_id?: string; status?: string } = {}) {
  const q = new URLSearchParams()
  if (opts.plan_id) q.set('plan_id', opts.plan_id)
  if (opts.status) q.set('status', opts.status)
  const qs = q.toString()
  return items<Task>(request(`/api/production/v1/tasks${qs ? '?' + qs : ''}`))
}

export function startTask(id: string, baker_id?: string) {
  const init: RequestInit = { method: 'POST' }
  if (baker_id) init.body = JSON.stringify({ baker_id })
  return request<Task>(`/api/production/v1/tasks/${id}/start`, init)
}

export function completeTask(id: string) {
  return request<Task>(`/api/production/v1/tasks/${id}/complete`, { method: 'POST' })
}

export function cancelTask(id: string) {
  return request<Task>(`/api/production/v1/tasks/${id}/cancel`, { method: 'POST' })
}
