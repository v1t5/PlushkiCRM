import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { i18n, currentLocale } from './i18n'
import './style.css'

// Pinia must be installed before the router so the router's beforeEach
// guard (and the /api 401 handler that imports the auth store) can
// resolve the active store on the first navigation.
const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(i18n)
if (typeof document !== 'undefined') {
  document.documentElement.lang = currentLocale()
}
app.mount('#app')
