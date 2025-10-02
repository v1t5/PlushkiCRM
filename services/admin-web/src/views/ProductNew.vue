<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { listCategories, createProduct, type Category } from '../api/catalog'
import { ApiError } from '../api/client'

const { t } = useI18n()
const router = useRouter()

const categories = ref<Category[]>([])
const loadError = ref<string | null>(null)

const form = ref({
  sku: '',
  name: '',
  description: '',
  // Owner-friendly: enter price in rubles, store as int64 kopecks.
  price_rub: 0 as number,
  category_id: '' as string | '',
})
const submitting = ref(false)
const submitError = ref<string | null>(null)

onMounted(async () => {
  try {
    categories.value = (await listCategories()) ?? []
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  }
})

async function submit() {
  if (submitting.value) return
  submitError.value = null
  submitting.value = true
  try {
    // Math.round to avoid float-binary surprises on inputs like "12.34"
    // → 1234.0000000002 → 1234 kopecks.
    const price_kopecks = Math.round(Number(form.value.price_rub) * 100)
    const created = await createProduct({
      sku: form.value.sku.trim(),
      name: form.value.name.trim(),
      description: form.value.description.trim(),
      price_kopecks,
      category_id: form.value.category_id || null,
    })
    // Hop straight into the recipe editor — most products need a recipe.
    await router.push({ name: 'product-detail', params: { id: created.id } })
  } catch (err) {
    submitError.value =
      err instanceof ApiError ? err.detail || err.message : err instanceof Error ? err.message : t('common.failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-4">
      <RouterLink to="/products" class="text-sm text-slate-500 hover:text-slate-900">{{ t('productNew.backProducts') }}</RouterLink>
    </div>
    <h1 class="text-2xl font-semibold text-slate-900 mb-4">{{ t('productNew.title') }}</h1>

    <p v-if="loadError" class="bg-amber-50 border border-amber-200 text-amber-800 text-sm px-3 py-2 rounded mb-4">
      {{ t('productNew.failedCategories', { err: loadError }) }}
    </p>

    <form class="bg-white rounded-lg shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submit">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('productNew.sku') }}</span>
        <input
          v-model="form.sku"
          type="text"
          required
          maxlength="64"
          :placeholder="t('productNew.skuPlaceholder')"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('productNew.category') }}</span>
        <select
          v-model="form.category_id"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        >
          <option value="">{{ t('productNew.uncategorisedOption') }}</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="flex flex-col gap-1 sm:col-span-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('productNew.name') }}</span>
        <input
          v-model="form.name"
          type="text"
          required
          maxlength="200"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <label class="flex flex-col gap-1 sm:col-span-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('productNew.description') }}</span>
        <textarea
          v-model="form.description"
          rows="3"
          maxlength="2000"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900 resize-y"
        />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('productNew.pricePlaceholder') }}</span>
        <input
          v-model.number="form.price_rub"
          type="number"
          min="0"
          step="0.01"
          required
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <p
        v-if="submitError"
        class="sm:col-span-2 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded"
      >
        {{ submitError }}
      </p>
      <div class="sm:col-span-2 flex justify-end gap-2">
        <RouterLink
          to="/products"
          class="text-sm font-medium px-4 py-2 rounded border border-slate-300 text-slate-700 hover:bg-slate-100"
        >
          {{ t('common.cancel') }}
        </RouterLink>
        <button
          type="submit"
          :disabled="submitting"
          class="bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
        >
          {{ submitting ? t('productNew.creating') : t('productNew.create') }}
        </button>
      </div>
    </form>
  </div>
</template>
