import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Product } from '../api/catalog'

export interface CartLine {
  product: Product
  qty: number
}

// The cart is intentionally minimal: tap a product → qty++; another tap on
// the same card → qty++ again. No quantity editor, no notes — Phase 3 is a
// "ring up a walk-in customer" story, not a full POS.
export const useCartStore = defineStore('cart', () => {
  const lines = ref<Map<string, CartLine>>(new Map())

  const items = computed(() => Array.from(lines.value.values()))

  const itemCount = computed(() =>
    items.value.reduce((acc, l) => acc + l.qty, 0),
  )

  const totalKopecks = computed(() =>
    items.value.reduce((acc, l) => acc + l.product.price_kopecks * l.qty, 0),
  )

  function add(product: Product) {
    const existing = lines.value.get(product.id)
    if (existing) {
      existing.qty += 1
    } else {
      lines.value.set(product.id, { product, qty: 1 })
    }
  }

  function remove(productId: string) {
    lines.value.delete(productId)
  }

  function decrement(productId: string) {
    const existing = lines.value.get(productId)
    if (!existing) return
    if (existing.qty <= 1) {
      lines.value.delete(productId)
    } else {
      existing.qty -= 1
    }
  }

  function clear() {
    lines.value.clear()
  }

  return { lines, items, itemCount, totalKopecks, add, remove, decrement, clear }
})
