import { request } from './client'

export type SalesDailyRow = {
  day: string
  order_count: number
  revenue_kopecks: number
}

export type SalesChannelRow = {
  channel: string
  order_count: number
  revenue_kopecks: number
}

export type TopItemRow = {
  product_id: string
  sku: string
  name: string
  qty_sold: number
  revenue_kopecks: number
}

export type StockLowRow = {
  id: string
  event_id: string
  ingredient_id: string
  sku: string
  name: string
  warehouse_id?: string
  threshold_qty_in_base: number
  current_qty_in_base: number
  default_unit_code: string
  default_unit_factor: number
  occurred_at: string
}

export type WasteSummary = {
  from: string
  to: string
  waste_qty_in_base: number
  deduction_qty_in_base: number
  percentage: number
}

function items<T>(p: Promise<{ items: T[] }>) {
  return p.then((r) => r.items)
}

export function salesDaily(from: string, to: string) {
  return items<SalesDailyRow>(
    request(`/api/reporting/v1/sales/daily?from=${from}&to=${to}`),
  )
}

export function salesByChannel(date: string) {
  return items<SalesChannelRow>(
    request(`/api/reporting/v1/sales/by-channel?date=${date}`),
  )
}

export function topItems(date: string, limit = 10, orderBy: 'qty' | 'revenue' = 'qty') {
  return items<TopItemRow>(
    request(`/api/reporting/v1/sales/top-items?date=${date}&limit=${limit}&order_by=${orderBy}`),
  )
}

export function lowStockEvents(from: string, to: string, limit = 20) {
  return items<StockLowRow>(
    request(`/api/reporting/v1/inventory/low-stock-events?from=${from}&to=${to}&limit=${limit}`),
  )
}

export function wasteSummary(from: string, to: string) {
  return request<WasteSummary>(`/api/reporting/v1/inventory/waste-percentage?from=${from}&to=${to}`)
}
