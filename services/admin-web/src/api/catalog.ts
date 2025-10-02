import { request } from './client'

// Shapes match the catalog service's HTTP DTOs.

export type Product = {
  id: string
  tenant_id: string
  category_id?: string | null
  sku: string
  name: string
  description: string
  price_kopecks: number
  created_at: string
  updated_at: string
}

export type Category = {
  id: string
  tenant_id: string
  name: string
  slug: string
  sort_order: number
  created_at: string
  updated_at: string
}

export type Unit = {
  id: string
  tenant_id: string
  code: string
  name: string
  base_unit_id?: string | null
  factor: number
  created_at: string
  updated_at: string
}

export type Ingredient = {
  id: string
  tenant_id: string
  sku: string
  name: string
  default_unit_id: string
  low_stock_threshold_qty: number
  created_at: string
  updated_at: string
}

export type RecipeLine = {
  id: string
  ingredient_id: string
  qty: number
  unit_id: string
}

export type Recipe = {
  product_id: string
  lines: RecipeLine[]
}

function items<T>(p: Promise<{ items: T[] }>) {
  return p.then((r) => r.items)
}

export function listProducts() {
  return items<Product>(request('/api/catalog/v1/products'))
}

export function getProduct(id: string) {
  return request<Product>(`/api/catalog/v1/products/${id}`)
}

export function createProduct(body: {
  sku: string
  name: string
  description: string
  price_kopecks: number
  category_id?: string | null
}) {
  return request<Product>('/api/catalog/v1/products', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function getRecipe(productID: string) {
  return request<Recipe>(`/api/catalog/v1/products/${productID}/recipe`)
}

export function setRecipe(productID: string, lines: { ingredient_id: string; qty: number; unit_id: string }[]) {
  return request<Recipe>(`/api/catalog/v1/products/${productID}/recipe`, {
    method: 'PUT',
    body: JSON.stringify({ lines }),
  })
}

export function listCategories() {
  return items<Category>(request('/api/catalog/v1/categories'))
}

export function createCategory(body: { name: string; slug: string; sort_order: number }) {
  return request<Category>('/api/catalog/v1/categories', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function listUnits() {
  return items<Unit>(request('/api/catalog/v1/units'))
}

export function createUnit(body: {
  code: string
  name: string
  base_unit_id?: string | null
  factor: number
}) {
  return request<Unit>('/api/catalog/v1/units', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function listIngredients() {
  return items<Ingredient>(request('/api/catalog/v1/ingredients'))
}

export function createIngredient(body: {
  sku: string
  name: string
  default_unit_id: string
  low_stock_threshold_qty: number
}) {
  return request<Ingredient>('/api/catalog/v1/ingredients', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}
