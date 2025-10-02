<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '../stores/auth'

const auth = useAuth()
const router = useRouter()
const { t } = useI18n()

function signOut() {
  auth.logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="p-10 max-w-xl mx-auto text-center">
    <h1 class="text-2xl font-semibold text-slate-900 mb-2">{{ t('forbidden.title') }}</h1>
    <p class="text-sm text-slate-600 mb-6">
      {{ t('forbidden.bodyAuthed') }}
      <span v-if="auth.user" class="font-medium">
        {{ t('forbidden.bodyAs', { email: auth.user.email }) }}
      </span>
      <i18n-t keypath="forbidden.bodyRest" tag="span">
        <template #role><code>admin</code></template>
      </i18n-t>
    </p>
    <button
      type="button"
      class="bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2 rounded"
      @click="signOut"
    >
      {{ t('common.signOut') }}
    </button>
  </div>
</template>
