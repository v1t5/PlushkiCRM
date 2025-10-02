<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  salesDaily,
  salesByChannel,
  topItems,
  lowStockEvents,
  wasteSummary,
  type SalesDailyRow,
  type SalesChannelRow,
  type TopItemRow,
  type StockLowRow,
  type WasteSummary,
} from '../api/reporting'
import { formatKopecks, formatPercent, todayISO, daysAgoISO, formatDateTime } from '../format'

const { t } = useI18n()

const daily = ref<SalesDailyRow[]>([])
const byChannel = ref<SalesChannelRow[]>([])
const top = ref<TopItemRow[]>([])
const lowStock = ref<StockLowRow[]>([])
const waste = ref<WasteSummary | null>(null)
const loadError = ref<string | null>(null)
const loading = ref(false)

async function load() {
  loading.value = true
  loadError.value = null
  const today = todayISO()
  const from30 = daysAgoISO(30)
  try {
    const [d, c, tt, ls, w] = await Promise.all([
      salesDaily(from30, today),
      salesByChannel(today),
      topItems(today, 5, 'qty'),
      lowStockEvents(from30, today, 10),
      wasteSummary(from30, today),
    ])
    daily.value = d ?? []
    byChannel.value = c ?? []
    top.value = tt ?? []
    lowStock.value = ls ?? []
    waste.value = w
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

function totalRevenue30d(): number {
  return daily.value.reduce((acc, r) => acc + r.revenue_kopecks, 0)
}
function totalOrders30d(): number {
  return daily.value.reduce((acc, r) => acc + r.order_count, 0)
}
function wasteColor(): string {
  if (!waste.value) return 'text-slate-900'
  const p = waste.value.percentage
  if (p < 0.05) return 'text-emerald-700'
  if (p < 0.15) return 'text-amber-700'
  return 'text-rose-700'
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('dashboard.title') }}</h1>
      <button
        type="button"
        class="text-sm bg-slate-900 hover:bg-slate-800 text-white rounded px-3 py-1.5"
        @click="load"
        :disabled="loading"
      >
        {{ loading ? t('common.loading') : t('common.refresh') }}
      </button>
    </div>

    <p
      v-if="loadError"
      class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4"
    >
      {{ loadError }}
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('dashboard.revenue30d') }}</div>
        <div class="text-2xl font-semibold text-slate-900 mt-1">
          {{ formatKopecks(totalRevenue30d()) }}
        </div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('dashboard.orders30d') }}</div>
        <div class="text-2xl font-semibold text-slate-900 mt-1">{{ totalOrders30d() }}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('dashboard.waste30d') }}</div>
        <div class="text-2xl font-semibold mt-1" :class="wasteColor()">
          {{ waste ? formatPercent(waste.percentage) : t('common.none') }}
        </div>
      </div>
      <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('dashboard.lowStock30d') }}</div>
        <div class="text-2xl font-semibold text-slate-900 mt-1">{{ lowStock.length }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <section class="bg-white rounded-lg shadow-sm p-4">
        <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('dashboard.topItems') }}</h2>
        <div v-if="top.length === 0" class="text-sm text-slate-500">{{ t('dashboard.noSalesYet') }}</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
              <th class="py-1">{{ t('dashboard.col.item') }}</th>
              <th class="py-1 text-right">{{ t('dashboard.col.qty') }}</th>
              <th class="py-1 text-right">{{ t('dashboard.col.revenue') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in top" :key="row.product_id" class="border-b border-slate-100">
              <td class="py-1.5">
                <div class="font-medium text-slate-800">{{ row.name }}</div>
                <div class="text-xs text-slate-500">{{ row.sku }}</div>
              </td>
              <td class="py-1.5 text-right">{{ row.qty_sold }}</td>
              <td class="py-1.5 text-right">{{ formatKopecks(row.revenue_kopecks) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="bg-white rounded-lg shadow-sm p-4">
        <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('dashboard.channels') }}</h2>
        <div v-if="byChannel.length === 0" class="text-sm text-slate-500">{{ t('dashboard.noSalesYet') }}</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
              <th class="py-1">{{ t('dashboard.col.channel') }}</th>
              <th class="py-1 text-right">{{ t('dashboard.col.orders') }}</th>
              <th class="py-1 text-right">{{ t('dashboard.col.revenue') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in byChannel" :key="row.channel" class="border-b border-slate-100">
              <td class="py-1.5 capitalize">{{ row.channel }}</td>
              <td class="py-1.5 text-right">{{ row.order_count }}</td>
              <td class="py-1.5 text-right">{{ formatKopecks(row.revenue_kopecks) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="bg-white rounded-lg shadow-sm p-4 lg:col-span-2">
        <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('dashboard.recentLowStock') }}</h2>
        <div v-if="lowStock.length === 0" class="text-sm text-slate-500">
          {{ t('dashboard.noLowStock') }}
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
              <th class="py-1">{{ t('dashboard.col.time') }}</th>
              <th class="py-1">{{ t('dashboard.col.ingredient') }}</th>
              <th class="py-1 text-right">{{ t('dashboard.col.current') }}</th>
              <th class="py-1 text-right">{{ t('dashboard.col.threshold') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ev in lowStock" :key="ev.id" class="border-b border-slate-100">
              <td class="py-1.5 text-slate-600">{{ formatDateTime(ev.occurred_at) }}</td>
              <td class="py-1.5">
                <div class="font-medium text-slate-800">{{ ev.name }}</div>
                <div class="text-xs text-slate-500">{{ ev.sku }}</div>
              </td>
              <td class="py-1.5 text-right">
                {{ ev.current_qty_in_base }} {{ ev.default_unit_code }}
              </td>
              <td class="py-1.5 text-right">
                {{ ev.threshold_qty_in_base }} {{ ev.default_unit_code }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>
