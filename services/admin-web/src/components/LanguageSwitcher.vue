<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, setLocale, type Locale } from '../i18n'

// Two flavours: the default (`variant="dark"`) is for the dark sidebar
// in Shell.vue; `variant="light"` suits the white login form. Both render
// the same control — a small <select> — but with palette-appropriate colors.
const props = defineProps<{ variant?: 'dark' | 'light' }>()

const { t, locale } = useI18n()

function onChange(e: Event) {
  const val = (e.target as HTMLSelectElement).value as Locale
  setLocale(val)
}

const selectClass =
  props.variant === 'light'
    ? 'px-2 py-1 border border-slate-300 rounded text-sm bg-white text-slate-700 focus:outline-none focus:border-slate-900'
    : 'px-2 py-1 rounded text-sm bg-slate-800 text-slate-100 border border-slate-700 focus:outline-none focus:border-slate-500'

const labelClass =
  props.variant === 'light'
    ? 'text-xs font-medium uppercase tracking-wide text-slate-500'
    : 'text-[0.65rem] font-semibold uppercase tracking-wider text-slate-500'
</script>

<template>
  <label class="flex flex-col gap-1">
    <span :class="labelClass">{{ t('language.label') }}</span>
    <select :class="selectClass" :value="locale" @change="onChange">
      <option v-for="l in SUPPORTED_LOCALES" :key="l" :value="l">
        {{ t(`language.${l}`) }}
      </option>
    </select>
  </label>
</template>
