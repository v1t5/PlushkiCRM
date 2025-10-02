<script setup lang="ts">
import { useCartStore } from '../stores/cart'
import { formatRub } from '../format'

const cart = useCartStore()
defineProps<{ busy: boolean }>()
const emit = defineEmits<{ pay: [] }>()
</script>

<template>
  <aside class="flex flex-col w-full md:w-96 bg-white border-l border-slate-200 shadow-md">
    <header class="px-4 py-3 border-b border-slate-200">
      <h2 class="text-lg font-semibold">Cart</h2>
      <p class="text-xs text-slate-500">{{ cart.itemCount }} item(s)</p>
    </header>

    <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
      <div
        v-for="line in cart.items"
        :key="line.product.id"
        class="flex items-center px-4 py-3 gap-3"
      >
        <div class="flex-1 min-w-0">
          <div class="font-medium truncate">{{ line.product.name }}</div>
          <div class="text-xs text-slate-500 font-mono">{{ line.product.sku }}</div>
        </div>
        <div class="flex items-center gap-1">
          <button
            class="w-8 h-8 rounded-full bg-slate-100 active:bg-slate-200 text-lg leading-none"
            type="button"
            @click="cart.decrement(line.product.id)"
          >−</button>
          <span class="w-8 text-center font-semibold">{{ line.qty }}</span>
          <button
            class="w-8 h-8 rounded-full bg-slate-100 active:bg-slate-200 text-lg leading-none"
            type="button"
            @click="cart.add(line.product)"
          >+</button>
        </div>
        <div class="w-20 text-right font-semibold">
          {{ formatRub(line.product.price_kopecks * line.qty) }}
        </div>
      </div>

      <div v-if="cart.items.length === 0" class="text-center text-slate-400 p-8">
        Tap a product to start a sale.
      </div>
    </div>

    <footer class="border-t border-slate-200 p-4 space-y-3">
      <div class="flex items-baseline justify-between">
        <span class="text-sm uppercase tracking-wide text-slate-500">Total</span>
        <span class="text-2xl font-bold tabular-nums">{{ formatRub(cart.totalKopecks) }}</span>
      </div>
      <button
        type="button"
        class="w-full h-14 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white text-lg font-semibold shadow"
        :disabled="busy || cart.items.length === 0"
        @click="emit('pay')"
      >
        {{ busy ? 'Processing…' : 'Cash' }}
      </button>
    </footer>
  </aside>
</template>
