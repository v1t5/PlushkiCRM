<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { listProducts, listCategories, type Product, type Category } from '../api/catalog'
import { ApiError } from '../api/client'
import { useAuth } from '../stores/auth'
import { formatKopecks } from '../format'

const { t } = useI18n()
const auth = useAuth()

const products = ref<Product[]>([])
const categories = ref<Category[]>([])
const filter = ref('')
const loading = ref(false)
const loadError = ref<string | null>(null)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [p, c] = await Promise.all([listProducts(), listCategories()])
    products.value = p ?? []
    categories.value = c ?? []
  } catch (err) {
    loadError.value =
      err instanceof ApiError ? err.message : err instanceof Error ? err.message : t('common.failed')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const categoryName = (id?: string | null) => {
  if (!id) return ''
  return categories.value.find((c) => c.id === id)?.name ?? ''
}

const filtered = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return products.value
  return products.value.filter(
    (p) =>
      p.sku.toLowerCase().includes(q) ||
      p.name.toLowerCase().includes(q) ||
      categoryName(p.category_id).toLowerCase().includes(q),
  )
})
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('products.title') }}</h1>
      <div class="flex gap-2">
        <button
          type="button"
          class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-800 rounded px-3 py-1.5"
          @click="load"
          :disabled="loading"
        >
          {{ loading ? t('common.loading') : t('common.refresh') }}
        </button>
        <RouterLink
          v-if="auth.isAdmin"
          to="/products/new"
          class="text-sm bg-slate-900 hover:bg-slate-800 text-white rounded px-3 py-1.5"
        >
          {{ t('products.new') }}
        </RouterLink>
      </div>
    </div>

    <div class="mb-4">
      <input
        v-model="filter"
        type="search"
        :placeholder="t('products.filter')"
        class="w-full max-w-md px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
      />
    </div>

    <p v-if="loadError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4">
      {{ loadError }}
    </p>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="px-3 py-2">{{ t('products.col.sku') }}</th>
            <th class="px-3 py-2">{{ t('products.col.name') }}</th>
            <th class="px-3 py-2">{{ t('products.col.category') }}</th>
            <th class="px-3 py-2 text-right">{{ t('products.col.price') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="p in filtered"
            :key="p.id"
            class="border-b border-slate-100 last:border-b-0 hover:bg-slate-50 cursor-pointer"
            @click="$router.push(`/products/${p.id}`)"
          >
            <td class="px-3 py-2 font-mono text-slate-700">{{ p.sku }}</td>
            <td class="px-3 py-2">
              <div class="font-medium text-slate-900">{{ p.name }}</div>
              <div v-if="p.description" class="text-xs text-slate-500">{{ p.description }}</div>
            </td>
            <td class="px-3 py-2 text-slate-600">{{ categoryName(p.category_id) || t('common.none') }}</td>
            <td class="px-3 py-2 text-right font-medium text-slate-900">
              {{ formatKopecks(p.price_kopecks) }}
            </td>
          </tr>
          <tr v-if="!loading && filtered.length === 0">
            <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ t('products.none') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
