<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { listUnits, createUnit, type Unit } from '../api/catalog'
import { ApiError } from '../api/client'

const { t } = useI18n()

const units = ref<Unit[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)

const form = ref({ code: '', name: '', base_unit_id: '' as string | '', factor: 1 })
const submitting = ref(false)
const submitError = ref<string | null>(null)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    units.value = (await listUnits()) ?? []
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Base units (factor=1, no base_unit_id) — these are the canonical mg/ml/pcs.
// Non-base units must point at a base unit and carry a >1 factor.
const baseUnits = computed(() => units.value.filter((u) => !u.base_unit_id))

const unitName = (id?: string | null) => {
  if (!id) return ''
  return units.value.find((u) => u.id === id)?.code ?? id
}

async function submit() {
  if (submitting.value) return
  submitError.value = null
  submitting.value = true
  try {
    await createUnit({
      code: form.value.code.trim(),
      name: form.value.name.trim(),
      base_unit_id: form.value.base_unit_id || null,
      factor: Number(form.value.factor) || 1,
    })
    form.value = { code: '', name: '', base_unit_id: '', factor: 1 }
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
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">{{ t('units.title') }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ t('units.description') }}</p>

    <section class="bg-white rounded-lg shadow-sm p-4 mb-6">
      <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('units.sectionNew') }}</h2>
      <form class="grid grid-cols-1 sm:grid-cols-4 gap-3" @submit.prevent="submit">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('units.code') }}</span>
          <input
            v-model="form.code"
            type="text"
            required
            maxlength="32"
            :placeholder="t('units.codePlaceholder')"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('units.name') }}</span>
          <input
            v-model="form.name"
            type="text"
            required
            maxlength="200"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('units.baseUnit') }}</span>
          <select
            v-model="form.base_unit_id"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          >
            <option value="">{{ t('units.baseUnitOption') }}</option>
            <option v-for="u in baseUnits" :key="u.id" :value="u.id">
              {{ u.code }} ({{ u.name }})
            </option>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('units.factor') }}</span>
          <input
            v-model.number="form.factor"
            type="number"
            min="1"
            step="1"
            required
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
            {{ submitting ? t('common.saving') : t('units.submit') }}
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
            <th class="px-3 py-2">{{ t('units.col.code') }}</th>
            <th class="px-3 py-2">{{ t('units.col.name') }}</th>
            <th class="px-3 py-2">{{ t('units.col.baseUnit') }}</th>
            <th class="px-3 py-2 text-right">{{ t('units.col.factor') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in units" :key="u.id" class="border-b border-slate-100 last:border-b-0">
            <td class="px-3 py-2 font-mono text-slate-700">{{ u.code }}</td>
            <td class="px-3 py-2 text-slate-900">{{ u.name }}</td>
            <td class="px-3 py-2 text-slate-600">
              {{ u.base_unit_id ? unitName(u.base_unit_id) : t('common.none') }}
            </td>
            <td class="px-3 py-2 text-right font-mono">{{ u.factor }}</td>
          </tr>
          <tr v-if="!loading && units.length === 0">
            <td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ t('units.none') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
