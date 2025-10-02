import { request } from './client'

export type OrderItem = {
  line_no: number
  product_id: string
  name: string
  sku: string
  price_kopecks: number
  qty: number
}

export type Order = {
  id: string
  tenant_id: string
  channel: string
  customer_ref: string
  status: string
  total_kopecks: number
  items: OrderItem[]
  created_at: string
  updated_at: string
}

function items<T>(p: Promise<{ items: T[] }>) {
  return p.then((r) => r.items)
}

// listByCustomer keeps the original Phase 1 by-customer history call. The
// orders endpoint accepts customer_ref as a single-customer mode trigger
// when no other filters are present.
export function listByCustomer(customerRef: string, limit = 50) {
  return items<Order>(
    request(
      `/api/orders/v1/orders?customer_ref=${encodeURIComponent(customerRef)}&limit=${limit}`,
    ),
  )
}

// listOrders is the generic admin list. Any combination of filters works;
// when only customer_ref is set it's equivalent to listByCustomer.
export type OrdersFilter = {
  status?: string
  channel?: string
  customer_ref?: string
  from?: string
  to?: string
  limit?: number
}

export function listOrders(f: OrdersFilter = {}) {
  const q = new URLSearchParams()
  if (f.status) q.set('status', f.status)
  if (f.channel) q.set('channel', f.channel)
  if (f.customer_ref) q.set('customer_ref', f.customer_ref)
  if (f.from) q.set('from', f.from)
  if (f.to) q.set('to', f.to)
  q.set('limit', String(f.limit ?? 50))
  return items<Order>(request(`/api/orders/v1/orders?${q.toString()}`))
}

export function getOrder(id: string) {
  return request<Order>(`/api/orders/v1/orders/${id}`)
}
