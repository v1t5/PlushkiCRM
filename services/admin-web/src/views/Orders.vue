<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { listOrders, type Order, type OrdersFilter } from '../api/orders'
import { ApiError } from '../api/client'
import { formatKopecks, formatDateTime, todayISO, daysAgoISO } from '../format'

const { t, te } = useI18n()

// Default to the last 7 days, all statuses, all channels. Owner can
// narrow with the controls below.
const filter = ref<OrdersFilter>({
  status: '',
  channel: '',
  customer_ref: '',
  from: daysAgoISO(7),
  to: todayISO(),
  limit: 50,
})

const orders = ref<Order[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    // Pass empty strings as undefined so we don't send pointless query params.
    orders.value = await listOrders({
      status: filter.value.status || undefined,
      channel: filter.value.channel || undefined,
      customer_ref: filter.value.customer_ref || undefined,
      from: filter.value.from || undefined,
      to: filter.value.to || undefined,
      limit: filter.value.limit,
    })
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.detail || err.message : err instanceof Error ? err.message : t('common.failed')
    orders.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

const statusColor = (s: string) => {
  switch (s) {
    case 'placed':
      return 'bg-slate-100 text-slate-700'
    case 'confirmed':
      return 'bg-sky-100 text-sky-800'
    case 'fulfilled':
      return 'bg-emerald-100 text-emerald-800'
    case 'cancelled':
      return 'bg-rose-100 text-rose-800'
    default:
      return 'bg-slate-100 text-slate-700'
  }
}

const statusLabel = (s: string) => {
  const key = `orders.statusValues.${s}`
  return te(key) ? t(key) : s
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">{{ t('orders.title') }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ t('orders.description') }}</p>

    <form
      class="bg-white rounded-lg shadow-sm p-4 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4"
      @submit.prevent="load"
    >
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('orders.status') }}</span>
        <select
          v-model="filter.status"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        >
          <option value="">{{ t('common.any') }}</option>
          <option value="placed">{{ t('orders.statusValues.placed') }}</option>
          <option value="confirmed">{{ t('orders.statusValues.confirmed') }}</option>
          <option value="fulfilled">{{ t('orders.statusValues.fulfilled') }}</option>
          <option value="cancelled">{{ t('orders.statusValues.cancelled') }}</option>
        </select>
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('orders.channel') }}</span>
        <select
          v-model="filter.channel"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        >
          <option value="">{{ t('common.any') }}</option>
          <option value="pos">pos</option>
          <option value="tg">tg</option>
        </select>
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('orders.customerRef') }}</span>
        <input
          v-model="filter.customer_ref"
          type="text"
          :placeholder="t('orders.customerRefPlaceholder')"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('orders.from') }}</span>
        <input
          v-model="filter.from"
          type="date"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('orders.to') }}</span>
        <input
          v-model="filter.to"
          type="date"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <div class="flex items-end">
        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
        >
          {{ loading ? t('common.loading') : t('common.apply') }}
        </button>
      </div>
    </form>

    <p v-if="loadError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4">
      {{ loadError }}
    </p>

    <div v-if="!loading && orders.length === 0" class="text-sm text-slate-500">
      {{ t('orders.noMatch') }}
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden" v-if="orders.length > 0">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="px-3 py-2">{{ t('orders.col.when') }}</th>
            <th class="px-3 py-2">{{ t('orders.col.channel') }}</th>
            <th class="px-3 py-2">{{ t('orders.col.customer') }}</th>
            <th class="px-3 py-2">{{ t('orders.col.items') }}</th>
            <th class="px-3 py-2">{{ t('orders.col.status') }}</th>
            <th class="px-3 py-2 text-right">{{ t('orders.col.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id" class="border-b border-slate-100 last:border-b-0">
            <td class="px-3 py-2 text-slate-600 whitespace-nowrap">
              {{ formatDateTime(o.created_at) }}
            </td>
            <td class="px-3 py-2 capitalize">{{ o.channel }}</td>
            <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ o.customer_ref }}</td>
            <td class="px-3 py-2">
              <ul class="text-xs text-slate-700">
                <li v-for="it in o.items" :key="it.line_no">
                  {{ it.qty }}× {{ it.name }}
                </li>
              </ul>
            </td>
            <td class="px-3 py-2">
              <span :class="['px-2 py-0.5 rounded text-xs font-medium', statusColor(o.status)]">
                {{ statusLabel(o.status) }}
              </span>
            </td>
            <td class="px-3 py-2 text-right font-medium text-slate-900 whitespace-nowrap">
              {{ formatKopecks(o.total_kopecks) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
