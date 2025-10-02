<script setup lang="ts">
import type { Product } from '../api/catalog'
import { formatRub } from '../format'

defineProps<{ products: Product[] }>()
const emit = defineEmits<{ pick: [product: Product] }>()
</script>

<template>
  <div
    class="grid gap-3 grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 p-4 overflow-y-auto"
  >
    <button
      v-for="p in products"
      :key="p.id"
      type="button"
      class="flex flex-col items-start text-left rounded-2xl bg-white shadow-sm border border-slate-200 p-4 active:scale-[0.97] active:bg-slate-50 transition select-none focus:outline-none focus:ring-4 focus:ring-emerald-300"
      @click="emit('pick', p)"
    >
      <div class="text-sm font-mono text-slate-400">{{ p.sku }}</div>
      <div class="mt-1 text-base font-semibold leading-tight">{{ p.name }}</div>
      <div class="mt-auto pt-3 text-lg font-bold text-emerald-700">
        {{ formatRub(p.price_kopecks) }}
      </div>
    </button>

    <div
      v-if="products.length === 0"
      class="col-span-full text-center text-slate-500 p-8"
    >
      No products yet — add some via /api/catalog/v1/products.
    </div>
  </div>
</template>
