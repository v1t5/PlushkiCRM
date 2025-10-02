import { request } from './client'

export interface OrderItem {
  line_no: number
  product_id: string
  name: string
  sku: string
  price_kopecks: number
  qty: number
}

export interface Order {
  id: string
  tenant_id: string
  channel: string
  customer_ref: string
  status: 'placed' | 'confirmed' | 'fulfilled' | 'cancelled'
  total_kopecks: number
  items: OrderItem[]
  created_at: string
  updated_at: string
}

export interface PlaceOrderInput {
  channel: string
  customer_ref: string
  items: Array<{ product_id: string; qty: number }>
}

export function placeOrder(in_: PlaceOrderInput): Promise<Order> {
  return request<Order>('/api/orders/v1/orders', {
    method: 'POST',
    body: JSON.stringify(in_),
  })
}

export function confirmOrder(id: string): Promise<Order> {
  return request<Order>(`/api/orders/v1/orders/${id}/confirm`, { method: 'POST' })
}

export function fulfillOrder(id: string): Promise<Order> {
  return request<Order>(`/api/orders/v1/orders/${id}/fulfill`, { method: 'POST' })
}
