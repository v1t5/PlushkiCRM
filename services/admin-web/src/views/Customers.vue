<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '../api/client'
import { listCustomers, type CustomerListRow } from '../api/customers'
import { formatKopecks, formatDateTime } from '../format'

const { t } = useI18n()

// Single search box. The server-side ILIKE matches the term against
// display_name OR any identity value (phone, tg, email, pos_walkin),
// so the same input handles both "find by name" and "find by handle".
const search = ref('')
const limit = ref(50)
const customers = ref<CustomerListRow[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    customers.value = await listCustomers({
      q: search.value.trim() || undefined,
      limit: limit.value,
    })
  } catch (err) {
    loadError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failed')
    customers.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">{{ t('customers.title') }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ t('customers.description') }}</p>

    <form
      class="bg-white rounded-lg shadow-sm p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4"
      @submit.prevent="load"
    >
      <label class="flex flex-col gap-1 sm:col-span-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">
          {{ t('customers.search') }}
        </span>
        <input
          v-model="search"
          type="text"
          :placeholder="t('customers.searchPlaceholder')"
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

    <p
      v-if="loadError"
      class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4"
    >
      {{ loadError }}
    </p>

    <div v-if="!loading && customers.length === 0" class="text-sm text-slate-500">
      {{ t('customers.noMatch') }}
    </div>

    <div v-if="customers.length > 0" class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="px-3 py-2">{{ t('customers.col.name') }}</th>
            <th class="px-3 py-2">{{ t('customers.col.identities') }}</th>
            <th class="px-3 py-2 text-right">{{ t('customers.col.visits') }}</th>
            <th class="px-3 py-2 text-right">{{ t('customers.col.totalSpend') }}</th>
            <th class="px-3 py-2 whitespace-nowrap">{{ t('customers.col.lastVisit') }}</th>
            <th class="px-3 py-2 whitespace-nowrap">{{ t('customers.col.created') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="c in customers"
            :key="c.id"
            class="border-b border-slate-100 last:border-b-0 align-top"
          >
            <td class="px-3 py-2">
              <div class="font-medium text-slate-900">{{ c.display_name || '—' }}</div>
              <div class="font-mono text-xs text-slate-400">{{ c.id }}</div>
            </td>
            <td class="px-3 py-2">
              <div v-if="c.identities.length === 0" class="text-xs text-slate-400">
                {{ t('common.none') }}
              </div>
              <div
                v-for="ident in c.identities"
                :key="ident.id"
                class="text-xs text-slate-700"
              >
                <span class="uppercase tracking-wide text-slate-500">{{ ident.type }}</span>
                {{ ident.value }}
              </div>
            </td>
            <td class="px-3 py-2 text-right font-medium text-slate-900">
              {{ c.visit_count }}
            </td>
            <td class="px-3 py-2 text-right font-medium text-slate-900 whitespace-nowrap">
              {{ formatKopecks(c.total_kopecks) }}
            </td>
            <td class="px-3 py-2 text-slate-600 whitespace-nowrap">
              {{ c.last_visit_at ? formatDateTime(c.last_visit_at) : t('common.none') }}
            </td>
            <td class="px-3 py-2 text-slate-600 whitespace-nowrap">
              {{ formatDateTime(c.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
