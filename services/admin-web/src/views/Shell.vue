<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '../stores/auth'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const auth = useAuth()
const router = useRouter()
const { t } = useI18n()

// Sidebar groups. Each section heading renders as a small uppercase
// label above its links. Active route gets the highlight via
// active-class / exact-active-class.
// adminOnly items are filtered out for non-admin users. Routes themselves
// also have `meta.adminOnly` so deep-linking still bounces to /forbidden,
// but hiding them from the sidebar keeps the UI honest.
type NavItem = { to: string; labelKey: string; exact?: boolean; adminOnly?: boolean }
type NavSection = { headingKey: string; items: NavItem[] }

const allSections: NavSection[] = [
  {
    headingKey: '',
    items: [
      { to: '/', labelKey: 'nav.dashboard', exact: true },
      { to: '/orders', labelKey: 'nav.orders' },
      { to: '/customers', labelKey: 'nav.customers' },
    ],
  },
  {
    headingKey: 'nav.catalog',
    items: [
      { to: '/products', labelKey: 'nav.products' },
      { to: '/categories', labelKey: 'nav.categories', adminOnly: true },
      { to: '/ingredients', labelKey: 'nav.ingredients', adminOnly: true },
      { to: '/units', labelKey: 'nav.units', adminOnly: true },
    ],
  },
  {
    headingKey: 'nav.operations',
    items: [{ to: '/production', labelKey: 'nav.production', adminOnly: true }],
  },
  {
    headingKey: 'nav.settings',
    items: [{ to: '/users', labelKey: 'nav.users', adminOnly: true }],
  },
]

const sections = computed(() =>
  allSections
    .map((s) => ({
      ...s,
      items: s.items.filter((i) => !i.adminOnly || auth.isAdmin),
    }))
    .filter((s) => s.items.length > 0),
)

function signOut() {
  auth.logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen flex bg-slate-100">
    <aside class="w-56 bg-slate-900 text-slate-100 flex flex-col">
      <div class="px-4 py-4 border-b border-slate-800">
        <div class="text-base font-semibold">{{ t('app.title') }}</div>
        <div v-if="auth.user" class="text-xs text-slate-400 truncate">
          {{ auth.user.display_name }}
        </div>
      </div>
      <nav class="flex-1 px-2 py-3 flex flex-col gap-1 text-sm overflow-y-auto">
        <template v-for="(section, i) in sections" :key="i">
          <div
            v-if="section.headingKey"
            class="mt-3 px-3 text-[0.65rem] font-semibold uppercase tracking-wider text-slate-500"
          >
            {{ t(section.headingKey) }}
          </div>
          <RouterLink
            v-for="item in section.items"
            :key="item.to"
            :to="item.to"
            class="px-3 py-1.5 rounded hover:bg-slate-800"
            :active-class="item.exact ? '' : 'bg-slate-800 text-white'"
            exact-active-class="bg-slate-800 text-white"
          >
            {{ t(item.labelKey) }}
          </RouterLink>
        </template>
      </nav>
      <div class="px-3 py-2 border-t border-slate-800">
        <LanguageSwitcher variant="dark" />
      </div>
      <button
        type="button"
        class="m-3 px-3 py-2 rounded text-sm bg-slate-800 hover:bg-slate-700 text-left"
        @click="signOut"
      >
        {{ t('common.signOut') }}
      </button>
    </aside>

    <main class="flex-1 overflow-y-auto">
      <RouterView />
    </main>
  </div>
</template>
