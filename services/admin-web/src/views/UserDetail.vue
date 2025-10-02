<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getUser,
  updateUserProfile,
  updateUserRoles,
  resetUserPassword,
  archiveUser,
  restoreUser,
  ALLOWED_ROLES,
  type AdminUser,
  type AllowedRole,
} from '../api/users'
import { ApiError } from '../api/client'
import { useAuth } from '../stores/auth'
import { formatDateTime } from '../format'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuth()

const userID = computed(() => route.params.id as string)
const user = ref<AdminUser | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)

// Profile + roles share their own working copy. Password/archive/restore
// each have their own busy flag so a slow network can't accidentally
// double-fire from a re-clicked button.
const draftDisplayName = ref('')
const draftRoles = ref<AllowedRole[]>([])
const profileBusy = ref(false)
const profileError = ref<string | null>(null)
const profileOk = ref(false)

const password = ref('')
const passwordBusy = ref(false)
const passwordError = ref<string | null>(null)
const passwordOk = ref(false)

const archiveBusy = ref(false)
const archiveError = ref<string | null>(null)

const isSelf = computed(() => user.value?.id === auth.user?.id)

function applyUser(u: AdminUser) {
  user.value = u
  draftDisplayName.value = u.display_name
  draftRoles.value = u.roles.filter((r): r is AllowedRole =>
    (ALLOWED_ROLES as readonly string[]).includes(r),
  )
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    applyUser(await getUser(userID.value))
  } catch (err) {
    loadError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function saveProfile() {
  if (profileBusy.value || !user.value) return
  profileBusy.value = true
  profileError.value = null
  profileOk.value = false
  try {
    let next = user.value
    const trimmed = draftDisplayName.value.trim()
    if (trimmed && trimmed !== user.value.display_name) {
      next = await updateUserProfile(user.value.id, trimmed)
    }
    // Always send roles — server validates non-empty + allowed set.
    const rolesEqual =
      draftRoles.value.length === user.value.roles.length &&
      draftRoles.value.every((r) => user.value!.roles.includes(r))
    if (!rolesEqual) {
      next = await updateUserRoles(user.value.id, draftRoles.value)
    }
    applyUser(next)
    profileOk.value = true
  } catch (err) {
    profileError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failed')
  } finally {
    profileBusy.value = false
  }
}

async function submitPassword() {
  if (passwordBusy.value || !user.value) return
  passwordBusy.value = true
  passwordError.value = null
  passwordOk.value = false
  try {
    await resetUserPassword(user.value.id, password.value)
    password.value = ''
    passwordOk.value = true
  } catch (err) {
    passwordError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failed')
  } finally {
    passwordBusy.value = false
  }
}

async function toggleArchive() {
  if (archiveBusy.value || !user.value) return
  archiveBusy.value = true
  archiveError.value = null
  try {
    const next = user.value.archived_at
      ? await restoreUser(user.value.id)
      : await archiveUser(user.value.id)
    applyUser(next)
  } catch (err) {
    archiveError.value =
      err instanceof ApiError
        ? err.detail || err.message
        : err instanceof Error
          ? err.message
          : t('common.failed')
  } finally {
    archiveBusy.value = false
  }
}
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto">
    <button
      type="button"
      class="text-sm text-slate-500 hover:text-slate-900 mb-3"
      @click="router.push('/users')"
    >
      {{ t('users.backUsers') }}
    </button>

    <p
      v-if="loadError"
      class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4"
    >
      {{ loadError }}
    </p>

    <div v-if="user">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">{{ user.email }}</h1>
          <p class="text-sm text-slate-500">{{ user.display_name }}</p>
          <p class="text-xs text-slate-400 mt-1 font-mono">{{ user.id }}</p>
          <p class="text-xs text-slate-400">
            {{ t('users.createdAt', { when: formatDateTime(user.created_at) }) }}
          </p>
          <p v-if="user.archived_at" class="text-xs text-rose-700 mt-1">
            {{ t('users.archivedAt', { when: formatDateTime(user.archived_at) }) }}
          </p>
        </div>
        <span
          v-if="user.archived_at"
          class="inline-block bg-rose-50 text-rose-700 text-xs rounded px-2 py-0.5"
          >{{ t('users.archived') }}</span
        >
        <span
          v-else
          class="inline-block bg-emerald-50 text-emerald-700 text-xs rounded px-2 py-0.5"
          >{{ t('users.active') }}</span
        >
      </div>

      <section class="bg-white rounded-lg shadow-sm p-4 mb-4">
        <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('users.sectionProfile') }}</h2>
        <form class="grid grid-cols-1 sm:grid-cols-2 gap-3" @submit.prevent="saveProfile">
          <label class="flex flex-col gap-1 sm:col-span-2">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{
              t('users.displayName')
            }}</span>
            <input
              v-model="draftDisplayName"
              type="text"
              required
              class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
            />
          </label>
          <fieldset class="sm:col-span-2 flex flex-col gap-1">
            <legend class="text-xs font-medium uppercase tracking-wide text-slate-600">
              {{ t('users.roles') }}
            </legend>
            <div class="flex flex-wrap gap-3 text-sm">
              <label
                v-for="role in ALLOWED_ROLES"
                :key="role"
                class="flex items-center gap-1.5 cursor-pointer"
              >
                <input v-model="draftRoles" type="checkbox" :value="role" />
                <span class="text-slate-800">{{ t(`users.roleValues.${role}`) }}</span>
              </label>
            </div>
          </fieldset>
          <p
            v-if="profileError"
            class="sm:col-span-2 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded"
          >
            {{ profileError }}
          </p>
          <p
            v-if="profileOk"
            class="sm:col-span-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-3 py-2 rounded"
          >
            {{ t('common.saved') }}
          </p>
          <div class="sm:col-span-2 flex justify-end">
            <button
              type="submit"
              :disabled="profileBusy"
              class="bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
            >
              {{ profileBusy ? t('common.saving') : t('common.save') }}
            </button>
          </div>
        </form>
      </section>

      <section class="bg-white rounded-lg shadow-sm p-4 mb-4">
        <h2 class="text-sm font-medium text-slate-700 mb-3">{{ t('users.sectionPassword') }}</h2>
        <p class="text-xs text-slate-500 mb-3">{{ t('users.passwordHint') }}</p>
        <form class="grid grid-cols-1 sm:grid-cols-3 gap-3" @submit.prevent="submitPassword">
          <label class="flex flex-col gap-1 sm:col-span-2">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{
              t('users.newPassword')
            }}</span>
            <input
              v-model="password"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
            />
          </label>
          <div class="flex items-end">
            <button
              type="submit"
              :disabled="passwordBusy"
              class="w-full bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
            >
              {{ passwordBusy ? t('common.saving') : t('users.resetPassword') }}
            </button>
          </div>
          <p
            v-if="passwordError"
            class="sm:col-span-3 bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded"
          >
            {{ passwordError }}
          </p>
          <p
            v-if="passwordOk"
            class="sm:col-span-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-3 py-2 rounded"
          >
            {{ t('users.passwordChanged') }}
          </p>
        </form>
      </section>

      <section class="bg-white rounded-lg shadow-sm p-4">
        <h2 class="text-sm font-medium text-slate-700 mb-3">
          {{ user.archived_at ? t('users.sectionRestore') : t('users.sectionArchive') }}
        </h2>
        <p class="text-xs text-slate-500 mb-3">
          {{ user.archived_at ? t('users.restoreHint') : t('users.archiveHint') }}
        </p>
        <p
          v-if="archiveError"
          class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-3"
        >
          {{ archiveError }}
        </p>
        <button
          type="button"
          :disabled="archiveBusy || isSelf"
          class="text-sm font-medium px-4 py-2 rounded disabled:opacity-50"
          :class="
            user.archived_at
              ? 'bg-emerald-700 hover:bg-emerald-800 text-white'
              : 'bg-rose-700 hover:bg-rose-800 text-white'
          "
          @click="toggleArchive"
        >
          {{
            archiveBusy
              ? t('common.saving')
              : user.archived_at
                ? t('users.restore')
                : t('users.archive')
          }}
        </button>
        <p v-if="isSelf" class="text-xs text-slate-500 mt-2">{{ t('users.cannotArchiveSelf') }}</p>
      </section>
    </div>
  </div>
</template>
