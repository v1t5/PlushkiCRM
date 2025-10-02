<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  listUsers,
  createUser,
  ALLOWED_ROLES,
  type AdminUser,
  type AllowedRole,
} from '../api/users'
import { ApiError } from '../api/client'
import { formatDateTime } from '../format'

const { t } = useI18n()

const users = ref<AdminUser[]>([])
const search = ref('')
const includeArchived = ref(false)
const loading = ref(false)
const loadError = ref<string | null>(null)

const form = ref({
  email: '',
  password: '',
  display_name: '',
  roles: [] as AllowedRole[],
})
const submitting = ref(false)
const submitError = ref<string | null>(null)
const submitOk = ref(false)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    users.value = await listUsers({
      q: search.value.trim() || undefined,
      include_archived: includeArchived.value,
      limit: 200,
    })
  } catch (err) {
    loadError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failed')
    users.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function submit() {
  if (submitting.value) return
  submitError.value = null
  submitOk.value = false
  submitting.value = true
  try {
    await createUser({
      email: form.value.email.trim(),
      password: form.value.password,
      display_name: form.value.display_name.trim(),
      roles: form.value.roles.length > 0 ? form.value.roles : undefined,
    })
    form.value = { email: '', password: '', display_name: '', roles: [] }
    submitOk.value = true
    await load()
  } catch (err) {
    submitError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">{{ t('users.title') }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ t('users.description') }}</p>

    <section class="bg-white rounded-lg shadow-sm p-4 mb-6">
      <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('users.sectionNew') }}</h2>
      <form class="grid grid-cols-1 sm:grid-cols-3 gap-3" @submit.prevent="submit">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{
            t('users.email')
          }}</span>
          <input
            v-model="form.email"
            type="email"
            required
            autocomplete="off"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{
            t('users.displayName')
          }}</span>
          <input
            v-model="form.display_name"
            type="text"
            required
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{
            t('users.password')
          }}</span>
          <input
            v-model="form.password"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>
        <fieldset class="sm:col-span-3 flex flex-col gap-1">
          <legend class="text-xs font-medium uppercase tracking-wide text-slate-600">
            {{ t('users.extraRoles') }}
          </legend>
          <div class="flex flex-wrap gap-3 text-sm">
            <label
              v-for="role in ALLOWED_ROLES"
              :key="role"
              class="flex items-center gap-1.5 cursor-pointer"
            >
              <input
                v-model="form.roles"
                type="checkbox"
                :value="role"
                :disabled="role === 'user'"
              />
              <span :class="role === 'user' ? 'text-slate-400' : 'text-slate-800'">{{
                t(`users.roleValues.${role}`)
              }}</span>
            </label>
          </div>
          <p class="text-xs text-slate-500">{{ t('users.userRoleAlways') }}</p>
        </fieldset>

        <p
          v-if="submitError"
          class="sm:col-span-3 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded"
        >
          {{ submitError }}
        </p>
        <p
          v-if="submitOk"
          class="sm:col-span-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-3 py-2 rounded"
        >
          {{ t('users.created') }}
        </p>
        <div class="sm:col-span-3 flex justify-end">
          <button
            type="submit"
            :disabled="submitting"
            class="bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
          >
            {{ submitting ? t('common.saving') : t('users.submitCreate') }}
          </button>
        </div>
      </form>
    </section>

    <form
      class="bg-white rounded-lg shadow-sm p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4"
      @submit.prevent="load"
    >
      <label class="flex flex-col gap-1 sm:col-span-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{
          t('users.search')
        }}</span>
        <input
          v-model="search"
          type="text"
          :placeholder="t('users.searchPlaceholder')"
          class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
        />
      </label>
      <label class="flex items-end gap-2 text-sm text-slate-700">
        <input v-model="includeArchived" type="checkbox" class="mt-1" />
        <span>{{ t('users.includeArchived') }}</span>
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

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="px-3 py-2">{{ t('users.col.email') }}</th>
            <th class="px-3 py-2">{{ t('users.col.name') }}</th>
            <th class="px-3 py-2">{{ t('users.col.roles') }}</th>
            <th class="px-3 py-2">{{ t('users.col.status') }}</th>
            <th class="px-3 py-2 whitespace-nowrap">{{ t('users.col.created') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="u in users"
            :key="u.id"
            class="border-b border-slate-100 last:border-b-0 hover:bg-slate-50 cursor-pointer"
            @click="$router.push(`/users/${u.id}`)"
          >
            <td class="px-3 py-2 font-medium text-slate-900">{{ u.email }}</td>
            <td class="px-3 py-2 text-slate-700">{{ u.display_name }}</td>
            <td class="px-3 py-2">
              <span
                v-for="r in u.roles"
                :key="r"
                class="inline-block bg-slate-100 text-slate-700 text-xs rounded px-2 py-0.5 mr-1"
                >{{ t(`users.roleValues.${r}`, r) }}</span
              >
            </td>
            <td class="px-3 py-2">
              <span
                v-if="u.archived_at"
                class="inline-block bg-rose-50 text-rose-700 text-xs rounded px-2 py-0.5"
                >{{ t('users.archived') }}</span
              >
              <span
                v-else
                class="inline-block bg-emerald-50 text-emerald-700 text-xs rounded px-2 py-0.5"
                >{{ t('users.active') }}</span
              >
            </td>
            <td class="px-3 py-2 text-slate-500 whitespace-nowrap">
              {{ formatDateTime(u.created_at) }}
            </td>
          </tr>
          <tr v-if="!loading && users.length === 0">
            <td colspan="5" class="px-3 py-6 text-center text-slate-500">
              {{ t('users.none') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
