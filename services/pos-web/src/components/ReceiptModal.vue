<script setup lang="ts">
import type { Order } from '../api/orders'
import { formatRub } from '../format'

defineProps<{ order: Order }>()
defineEmits<{ close: [] }>()

function shortId(id: string): string {
  return id.slice(-12)
}
</script>

<template>
  <div
    class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
      <header class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold">Receipt</h2>
          <p class="text-xs text-slate-500 font-mono">#{{ shortId(order.id) }}</p>
        </div>
        <span
          class="text-xs uppercase tracking-wide font-semibold px-2 py-1 rounded bg-emerald-100 text-emerald-800"
        >
          {{ order.status }}
        </span>
      </header>

      <div class="px-6 py-4 font-mono text-sm">
        <div class="space-y-1">
          <div
            v-for="it in order.items"
            :key="it.line_no"
            class="flex justify-between"
          >
            <div class="flex-1 min-w-0">
              <div class="truncate">{{ it.name }}</div>
              <div class="text-slate-400 text-xs">
                {{ it.qty }} × {{ formatRub(it.price_kopecks) }}
              </div>
            </div>
            <div class="font-semibold tabular-nums">
              {{ formatRub(it.price_kopecks * it.qty) }}
            </div>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-dashed border-slate-300 flex justify-between text-base">
          <span class="font-semibold">Total</span>
          <span class="font-bold tabular-nums">{{ formatRub(order.total_kopecks) }}</span>
        </div>

        <div class="mt-3 text-xs text-slate-400">
          Channel: {{ order.channel }} · Customer: {{ order.customer_ref }}
        </div>
      </div>

      <footer class="px-6 py-4 bg-slate-50 border-t border-slate-200">
        <button
          type="button"
          class="w-full h-12 rounded-xl bg-slate-900 hover:bg-slate-800 active:bg-black text-white text-base font-semibold"
          @click="$emit('close')"
        >
          New sale
        </button>
      </footer>
    </div>
  </div>
</template>
