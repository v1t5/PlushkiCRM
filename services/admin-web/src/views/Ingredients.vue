<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  listIngredients,
  createIngredient,
  listUnits,
  type Ingredient,
  type Unit,
} from '../api/catalog'
import { ApiError } from '../api/client'

const { t } = useI18n()

const ingredients = ref<Ingredient[]>([])
const units = ref<Unit[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

const form = ref({ sku: '', name: '', default_unit_id: '', low_stock_threshold_qty: 0 })
const submitting = ref(false)
const submitError = ref<string | null>(null)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [i, u] = await Promise.all([listIngredients(), listUnits()])
    ingredients.value = i ?? []
    units.value = u ?? []
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const unitLabel = (id?: string) => {
  if (!id) return ''
  const u = units.value.find((x) => x.id === id)
  return u ? `${u.code} (${u.name})` : id
}

async function submit() {
  if (submitting.value) return
  submitError.value = null
  if (!form.value.default_unit_id) {
    submitError.value = t('ingredients.pickUnit')
    return
  }
  submitting.value = true
  try {
    await createIngredient({
      sku: form.value.sku.trim(),
      name: form.value.name.trim(),
      default_unit_id: form.value.default_unit_id,
      low_stock_threshold_qty: Number(form.value.low_stock_threshold_qty) || 0,
    })
    form.value = { sku: '', name: '', default_unit_id: '', low_stock_threshold_qty: 0 }
    await load()
  } catch (err) {
    submitError.value =
      err instanceof ApiError ? err.detail || err.message : err instanceof Error ? err.message : t('common.failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">{{ t('ingredients.title') }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ t('ingredients.description') }}</p>

    <section class="bg-white rounded-lg shadow-sm p-4 mb-6">
      <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('ingredients.sectionNew') }}</h2>
      <form class="grid grid-cols-1 sm:grid-cols-4 gap-3" @submit.prevent="submit">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('ingredients.sku') }}</span>
          <input
            v-model="form.sku"
            type="text"
            required
            maxlength="64"
            :placeholder="t('ingredients.skuPlaceholder')"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1 sm:col-span-2">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('ingredients.name') }}</span>
          <input
            v-model="form.name"
            type="text"
            required
            maxlength="200"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('ingredients.defaultUnit') }}</span>
          <select
            v-model="form.default_unit_id"
            required
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          >
            <option value="" disabled>{{ t('common.pick') }}</option>
            <option v-for="u in units" :key="u.id" :value="u.id">
              {{ u.code }} ({{ u.name }})
            </option>
          </select>
        </label>
        <label class="flex flex-col gap-1 sm:col-span-2">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">
            {{ t('ingredients.threshold') }}
          </span>
          <input
            v-model.number="form.low_stock_threshold_qty"
            type="number"
            min="0"
            step="1"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <p
          v-if="submitError"
          class="sm:col-span-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded"
        >
          {{ submitError }}
        </p>
        <div class="sm:col-span-4 flex justify-end">
          <button
            type="submit"
            :disabled="submitting"
            class="bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
          >
            {{ submitting ? t('common.saving') : t('ingredients.submit') }}
          </button>
        </div>
      </form>
    </section>

    <p v-if="loadError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4">
      {{ loadError }}
    </p>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="px-3 py-2">{{ t('ingredients.col.sku') }}</th>
            <th class="px-3 py-2">{{ t('ingredients.col.name') }}</th>
            <th class="px-3 py-2">{{ t('ingredients.col.defaultUnit') }}</th>
            <th class="px-3 py-2 text-right">{{ t('ingredients.col.threshold') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in ingredients" :key="i.id" class="border-b border-slate-100 last:border-b-0">
            <td class="px-3 py-2 font-mono text-slate-700">{{ i.sku }}</td>
            <td class="px-3 py-2 text-slate-900">{{ i.name }}</td>
            <td class="px-3 py-2 text-slate-600">{{ unitLabel(i.default_unit_id) }}</td>
            <td class="px-3 py-2 text-right">{{ i.low_stock_threshold_qty }}</td>
          </tr>
          <tr v-if="!loading && ingredients.length === 0">
            <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ t('ingredients.none') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
