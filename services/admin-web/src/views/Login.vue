<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '../stores/auth'
import { ApiError } from '../api/client'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const auth = useAuth()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

async function submit() {
  if (busy.value) return
  busy.value = true
  error.value = null
  try {
    await auth.login(email.value.trim(), password.value)
    const next = (route.query.next as string | undefined) || '/'
    await router.replace(next)
  } catch (err) {
    if (err instanceof ApiError) {
      error.value = err.detail || err.message
    } else if (err instanceof Error) {
      error.value = err.message
    } else {
      error.value = t('login.failed')
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 flex items-center justify-center px-4">
    <div class="w-full max-w-sm flex flex-col gap-3">
      <div class="flex justify-end">
        <LanguageSwitcher variant="light" />
      </div>
      <form
        class="bg-white shadow-sm rounded-lg p-6 flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <h1 class="text-xl font-semibold text-slate-900">{{ t('app.title') }}</h1>
        <p class="text-sm text-slate-500 -mt-2">{{ t('login.subtitle') }}</p>

        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('login.email') }}</span>
          <input
            v-model="email"
            type="email"
            autocomplete="username"
            required
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>

        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ t('login.password') }}</span>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
          />
        </label>

        <p v-if="error" class="text-sm text-rose-700 bg-rose-50 border border-rose-200 px-3 py-2 rounded">
          {{ error }}
        </p>

        <button
          type="submit"
          :disabled="busy"
          class="bg-slate-900 hover:bg-slate-800 active:bg-slate-700 disabled:opacity-50 text-white text-sm font-medium py-2 rounded"
        >
          {{ busy ? t('login.signingIn') : t('login.signIn') }}
        </button>
      </form>
    </div>
  </div>
</template>
