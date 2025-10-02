import { request } from './client'

export interface Product {
  id: string
  tenant_id: string
  category_id?: string
  sku: string
  name: string
  description: string
  price_kopecks: number
  created_at: string
  updated_at: string
}

interface ProductList {
  items: Product[]
}

export async function listProducts(): Promise<Product[]> {
  const body = await request<ProductList>('/api/catalog/v1/products')
  return body.items ?? []
}
