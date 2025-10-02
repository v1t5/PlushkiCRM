import { request } from './client'

export type CustomerIdentity = {
  id: string
  type: string
  value: string
  verified_at: string | null
  created_at: string
}

export type Customer = {
  id: string
  tenant_id: string
  display_name: string
  identities: CustomerIdentity[]
  created_at: string
  updated_at: string
}

// CustomerListRow extends Customer with the loyalty totals the list
// endpoint inlines, so the table can render visit count + lifetime spend
// without a per-row /loyalty fan-out.
export type CustomerListRow = Customer & {
  visit_count: number
  total_kopecks: number
  last_visit_at: string | null
}

function items<T>(p: Promise<{ items: T[] }>) {
  return p.then((r) => r.items)
}

export type ListCustomersFilter = {
  q?: string
  limit?: number
}

export function listCustomers(f: ListCustomersFilter = {}) {
  const q = new URLSearchParams()
  if (f.q) q.set('q', f.q)
  q.set('limit', String(f.limit ?? 50))
  return items<CustomerListRow>(request(`/api/crm/v1/customers?${q.toString()}`))
}
