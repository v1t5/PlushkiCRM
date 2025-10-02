<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getPlan,
  publishPlan,
  listTasks,
  startTask,
  completeTask,
  cancelTask,
  type Plan,
  type Task,
} from '../api/production'
import { listProducts, type Product } from '../api/catalog'
import { ApiError } from '../api/client'
import { todayISO, formatDateTime } from '../format'

const { t, te } = useI18n()

const date = ref(todayISO())
const plan = ref<Plan | null>(null)
const tasks = ref<Task[]>([])
const products = ref<Product[]>([])

const loading = ref(false)
const loadError = ref<string | null>(null)

const busy = ref<string | null>(null) // task id currently transitioning, or 'publish'
const opError = ref<string | null>(null)

const productLabel = (id: string) => {
  const p = products.value.find((x) => x.id === id)
  return p ? `${p.name} (${p.sku})` : id
}

async function load() {
  loading.value = true
  loadError.value = null
  opError.value = null
  try {
    // Products only need to load once per session.
    if (products.value.length === 0) {
      products.value = (await listProducts()) ?? []
    }
    plan.value = await getPlan(date.value)
    tasks.value = plan.value ? (await listTasks({ plan_id: plan.value.id })) ?? [] : []
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function doPublish() {
  if (!plan.value || busy.value) return
  busy.value = 'publish'
  opError.value = null
  try {
    const resp = await publishPlan(date.value)
    plan.value = resp.plan
    tasks.value = resp.tasks
  } catch (err) {
    opError.value =
      err instanceof ApiError ? err.detail || err.message : err instanceof Error ? err.message : t('common.failed')
  } finally {
    busy.value = null
  }
}

async function transition(task: Task, op: 'start' | 'complete' | 'cancel') {
  if (busy.value) return
  busy.value = task.id
  opError.value = null
  try {
    const updated = op === 'start' ? await startTask(task.id) : op === 'complete' ? await completeTask(task.id) : await cancelTask(task.id)
    const idx = tasks.value.findIndex((x) => x.id === updated.id)
    if (idx >= 0) tasks.value[idx] = updated
  } catch (err) {
    opError.value =
      err instanceof ApiError ? err.detail || err.message : err instanceof Error ? err.message : t('common.failed')
  } finally {
    busy.value = null
  }
}

const statusColor = (s: string) => {
  switch (s) {
    case 'draft':
    case 'open':
      return 'bg-slate-100 text-slate-700'
    case 'published':
    case 'in_progress':
      return 'bg-sky-100 text-sky-800'
    case 'completed':
      return 'bg-emerald-100 text-emerald-800'
    case 'cancelled':
      return 'bg-rose-100 text-rose-800'
    default:
      return 'bg-slate-100 text-slate-700'
  }
}

// Translate FSM status values; fall back to the raw string when no key matches
// (forward-compat for any new statuses added before locale files catch up).
const statusLabel = (s: string) => {
  const key = `production.statusValues.${s}`
  return te(key) ? t(key) : s
}

// Group tasks by status so the board reads top-to-bottom: things that
// need attention (open / in_progress) above things that are done.
const order = ['open', 'in_progress', 'completed', 'cancelled']
const groupedTasks = computed(() => {
  const groups: Record<string, Task[]> = {}
  for (const tk of tasks.value) {
    ;(groups[tk.status] ||= []).push(tk)
  }
  return order.filter((s) => groups[s]?.length).map((s) => ({ status: s, items: groups[s] }))
})
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-end justify-between gap-4 mb-4">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">{{ t('production.title') }}</h1>
        <p class="text-sm text-slate-500">{{ t('production.subtitle') }}</p>
      </div>
      <div class="flex items-end gap-2">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('production.planDate') }}</span>
          <input
            v-model="date"
            type="date"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <button
          type="button"
          class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-800 rounded px-3 py-2"
          @click="load"
          :disabled="loading"
        >
          {{ loading ? t('common.loading') : t('common.load') }}
        </button>
      </div>
    </div>

    <p v-if="loadError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4">
      {{ loadError }}
    </p>
    <p v-if="opError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4">
      {{ opError }}
    </p>

    <section v-if="plan" class="bg-white rounded-lg shadow-sm p-5 mb-6">
      <header class="flex items-start justify-between mb-3">
        <div>
          <div class="text-xs uppercase tracking-wide text-slate-500">{{ t('production.planLabel') }}</div>
          <div class="text-lg font-semibold text-slate-900">{{ plan.plan_date }}</div>
          <div class="font-mono text-xs text-slate-500">{{ plan.id }}</div>
        </div>
        <div class="flex items-center gap-3">
          <span :class="['px-2 py-0.5 rounded text-xs font-medium', statusColor(plan.status)]">
            {{ statusLabel(plan.status) }}
          </span>
          <button
            v-if="plan.status === 'draft'"
            type="button"
            :disabled="busy !== null"
            class="bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-1.5 rounded"
            @click="doPublish"
          >
            {{ busy === 'publish' ? t('production.publishing') : t('production.publishPlan') }}
          </button>
        </div>
      </header>
      <table v-if="plan.items.length" class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <th class="py-1.5">{{ t('production.col.product') }}</th>
            <th class="py-1.5 text-right">{{ t('production.col.qty') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="it in plan.items" :key="it.id" class="border-b border-slate-100">
            <td class="py-1.5">{{ productLabel(it.product_id) }}</td>
            <td class="py-1.5 text-right font-mono">{{ it.qty }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="text-sm text-slate-500">{{ t('production.noPlanItems') }}</p>
    </section>
    <p v-else-if="!loading" class="text-sm text-slate-500 mb-6">
      {{ t('production.noPlan', { date }) }}
    </p>

    <section v-if="plan">
      <h2 class="text-base font-semibold text-slate-900 mb-3">{{ t('production.tasks') }}</h2>
      <div v-if="tasks.length === 0" class="text-sm text-slate-500">
        {{ t('production.noTasks') }}
      </div>
      <div v-for="group in groupedTasks" :key="group.status" class="mb-5">
        <div class="text-xs uppercase tracking-wider text-slate-500 mb-2">
          {{ statusLabel(group.status) }} · {{ group.items.length }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <article
            v-for="tk in group.items"
            :key="tk.id"
            class="bg-white rounded-lg shadow-sm p-3 flex items-start justify-between gap-3"
          >
            <div class="min-w-0">
              <div class="font-medium text-slate-900 truncate">
                {{ productLabel(tk.product_id) }}
              </div>
              <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap gap-x-3">
                <span><span class="font-mono">{{ tk.qty }}</span> {{ t('production.pcs') }}</span>
                <span v-if="tk.started_at">{{ t('production.started', { when: formatDateTime(tk.started_at) }) }}</span>
                <span v-if="tk.completed_at">{{ t('production.completed', { when: formatDateTime(tk.completed_at) }) }}</span>
              </div>
            </div>
            <div class="flex flex-col items-end gap-1 shrink-0">
              <span :class="['px-2 py-0.5 rounded text-[0.65rem] font-medium uppercase tracking-wide', statusColor(tk.status)]">
                {{ statusLabel(tk.status) }}
              </span>
              <div class="flex gap-1">
                <button
                  v-if="tk.status === 'open'"
                  type="button"
                  :disabled="busy !== null"
                  class="text-xs bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white rounded px-2 py-1"
                  @click="transition(tk, 'start')"
                >
                  {{ t('production.actions.start') }}
                </button>
                <button
                  v-if="tk.status === 'in_progress'"
                  type="button"
                  :disabled="busy !== null"
                  class="text-xs bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white rounded px-2 py-1"
                  @click="transition(tk, 'complete')"
                >
                  {{ t('production.actions.complete') }}
                </button>
                <button
                  v-if="tk.status === 'open' || tk.status === 'in_progress'"
                  type="button"
                  :disabled="busy !== null"
                  class="text-xs bg-slate-200 hover:bg-slate-300 disabled:opacity-50 text-slate-800 rounded px-2 py-1"
                  @click="transition(tk, 'cancel')"
                >
                  {{ t('production.actions.cancel') }}
                </button>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>
