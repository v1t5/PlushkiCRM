<script setup lang="ts">
import { onMounted, ref } from 'vue'

import ProductGrid from './components/ProductGrid.vue'
import CartPanel from './components/CartPanel.vue'
import ReceiptModal from './components/ReceiptModal.vue'
import { useCartStore } from './stores/cart'
import { listProducts, type Product } from './api/catalog'
import { confirmOrder, fulfillOrder, placeOrder, type Order } from './api/orders'
import { ApiError } from './api/client'

const cart = useCartStore()
const products = ref<Product[]>([])
const loadError = ref<string | null>(null)
const busy = ref(false)
const lastOrder = ref<Order | null>(null)
const flashError = ref<string | null>(null)

onMounted(loadProducts)

async function loadProducts() {
  loadError.value = null
  try {
    products.value = await listProducts()
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : 'failed to load products'
  }
}

async function pay() {
  if (cart.items.length === 0 || busy.value) return
  busy.value = true
  flashError.value = null
  try {
    // POS flow has no separate "kitchen ready" gate — the cashier rings
    // the order and immediately confirms + fulfils. Three sequential
    // calls keep the orders FSM honest; the spinner masks the latency.
    let order = await placeOrder({
      channel: 'pos',
      customer_ref: 'pos:walk-in',
      items: cart.items.map((l) => ({ product_id: l.product.id, qty: l.qty })),
    })
    order = await confirmOrder(order.id)
    order = await fulfillOrder(order.id)
    lastOrder.value = order
    cart.clear()
  } catch (err) {
    flashError.value =
      err instanceof ApiError
        ? `${err.message}${err.detail ? ' · ' + err.detail : ''}`
        : err instanceof Error
          ? err.message
          : 'sale failed'
  } finally {
    busy.value = false
  }
}

function dismissReceipt() {
  lastOrder.value = null
}
</script>

<template>
  <div class="h-screen flex flex-col">
    <header class="flex items-center justify-between px-4 py-3 bg-slate-900 text-white">
      <div class="flex items-baseline gap-3">
        <h1 class="text-lg font-bold">plushki POS</h1>
        <span class="text-xs uppercase tracking-wide text-slate-300">walk-in</span>
      </div>
      <button
        type="button"
        class="text-sm bg-slate-800 hover:bg-slate-700 active:bg-slate-600 rounded px-3 py-1"
        @click="loadProducts"
      >
        Refresh
      </button>
    </header>

    <div
      v-if="loadError"
      class="bg-rose-100 border-b border-rose-200 px-4 py-2 text-sm text-rose-800"
    >
      Could not load products: {{ loadError }}
    </div>
    <div
      v-if="flashError"
      class="bg-amber-100 border-b border-amber-200 px-4 py-2 text-sm text-amber-800"
    >
      {{ flashError }}
    </div>

    <main class="flex-1 flex overflow-hidden">
      <div class="flex-1 overflow-y-auto bg-slate-100">
        <ProductGrid :products="products" @pick="cart.add" />
      </div>
      <CartPanel :busy="busy" @pay="pay" />
    </main>

    <ReceiptModal
      v-if="lastOrder"
      :order="lastOrder"
      @close="dismissReceipt"
    />
  </div>
</template>
