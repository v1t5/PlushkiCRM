<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { listCategories, createCategory, type Category } from '../api/catalog'
import { ApiError } from '../api/client'
import { formatDateTime } from '../format'

const { t } = useI18n()

const categories = ref<Category[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

const form = ref({ name: '', slug: '', sort_order: 0 })
const submitting = ref(false)
const submitError = ref<string | null>(null)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    categories.value = (await listCategories()) ?? []
    categories.value.sort((a, b) => a.sort_order - b.sort_order)
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function submit() {
  if (submitting.value) return
  submitError.value = null
  submitting.value = true
  try {
    await createCategory({
      name: form.value.name.trim(),
      slug: form.value.slug.trim(),
      sort_order: Number(form.value.sort_order) || 0,
    })
    form.value = { name: '', slug: '', sort_order: 0 }
    await load()
  } catch (err) {
    submitError.value =
      err instanceof ApiError ? err.detail || err.message : err instanceof Error ? err.message : t('common.failed')
  } finally {
    submitting.value = false
  }
}

// slug auto-derivation: lowercase, non-alphanum → '-'. Only auto-fills
// if the user hasn't typed into slug yet.
let slugTouched = false
function onSlugInput() {
  slugTouched = true
}
function onNameInput() {
  if (slugTouched) return
  form.value.slug = form.value.name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-4">{{ t('categories.title') }}</h1>

    <section class="bg-white rounded-lg shadow-sm p-4 mb-6">
      <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('categories.sectionNew') }}</h2>
      <form class="grid grid-cols-1 sm:grid-cols-4 gap-3" @submit.prevent="submit">
        <label class="flex flex-col gap-1 sm:col-span-2">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('categories.name') }}</span>
          <input
            v-model="form.name"
            @input="onNameInput"
            type="text"
            required
            maxlength="200"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('categories.slug') }}</span>
          <input
            v-model="form.slug"
            @input="onSlugInput"
            type="text"
            required
            maxlength="64"
            pattern="[a-z0-9-]+"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('categories.sort') }}</span>
          <input
            v-model.number="form.sort_order"
            type="number"
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
            {{ submitting ? t('common.saving') : t('categories.submit') }}
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
            <th class="px-3 py-2">{{ t('categories.col.sort') }}</th>
            <th class="px-3 py-2">{{ t('categories.col.name') }}</th>
            <th class="px-3 py-2">{{ t('categories.col.slug') }}</th>
            <th class="px-3 py-2">{{ t('categories.col.created') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in categories" :key="c.id" class="border-b border-slate-100 last:border-b-0">
            <td class="px-3 py-2 text-slate-600">{{ c.sort_order }}</td>
            <td class="px-3 py-2 font-medium text-slate-900">{{ c.name }}</td>
            <td class="px-3 py-2 font-mono text-slate-600">{{ c.slug }}</td>
            <td class="px-3 py-2 text-slate-500">{{ formatDateTime(c.created_at) }}</td>
          </tr>
          <tr v-if="!loading && categories.length === 0">
            <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ t('categories.none') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
