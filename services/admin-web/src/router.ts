import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuth } from './stores/auth'
import { setUnauthorizedHandler } from './api/client'

import LoginView from './views/Login.vue'
import ForbiddenView from './views/Forbidden.vue'
import DashboardView from './views/Dashboard.vue'
import OrdersView from './views/Orders.vue'
import ProductsView from './views/Products.vue'
import ProductNewView from './views/ProductNew.vue'
import ProductDetailView from './views/ProductDetail.vue'
import CategoriesView from './views/Categories.vue'
import IngredientsView from './views/Ingredients.vue'
import UnitsView from './views/Units.vue'
import ProductionView from './views/Production.vue'
import CustomersView from './views/Customers.vue'
import UsersView from './views/Users.vue'
import UserDetailView from './views/UserDetail.vue'

// Admin role gating — write-heavy routes set `meta.adminOnly: true`.
// The router guard redirects non-admin users to /forbidden.
//
// Read-only views (Dashboard, Orders, Customers, Products list, Product
// detail) are open to any authed user; create/edit screens and recipe-edit
// require admin. The Production view is whole-page admin-only because the
// only meaningful actions are publish + task transitions.
const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
  {
    path: '/',
    component: () => import('./views/Shell.vue'),
    children: [
      { path: '', name: 'dashboard', component: DashboardView },
      { path: 'orders', name: 'orders', component: OrdersView },
      { path: 'products', name: 'products', component: ProductsView },
      {
        path: 'products/new',
        name: 'product-new',
        component: ProductNewView,
        meta: { adminOnly: true },
      },
      { path: 'products/:id', name: 'product-detail', component: ProductDetailView },
      {
        path: 'categories',
        name: 'categories',
        component: CategoriesView,
        meta: { adminOnly: true },
      },
      {
        path: 'ingredients',
        name: 'ingredients',
        component: IngredientsView,
        meta: { adminOnly: true },
      },
      { path: 'units', name: 'units', component: UnitsView, meta: { adminOnly: true } },
      {
        path: 'production',
        name: 'production',
        component: ProductionView,
        meta: { adminOnly: true },
      },
      { path: 'customers', name: 'customers', component: CustomersView },
      { path: 'users', name: 'users', component: UsersView, meta: { adminOnly: true } },
      {
        path: 'users/:id',
        name: 'user-detail',
        component: UserDetailView,
        meta: { adminOnly: true },
      },
      { path: 'forbidden', name: 'forbidden', component: ForbiddenView },
    ],
  },
  // Hash-bang fallback: deep-linked unknown paths land on dashboard.
  { path: '/:pathMatch(.*)*', redirect: { name: 'dashboard' } },
]

// History base must match Vite's `base: '/admin/'` so deep links and
// asset URLs agree on the prefix. The history adapter handles the
// stripping for the router internally.
export const router = createRouter({
  history: createWebHistory('/admin/'),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuth()
  if (to.meta.public) return true
  if (!auth.isAuthed) {
    return { name: 'login', query: { next: to.fullPath } }
  }
  if (to.meta.adminOnly && !auth.isAdmin) {
    return { name: 'forbidden' }
  }
  return true
})

// Wire 401 handler: any /api/* call returning 401 logs the user out and
// redirects to /login while preserving the page they were on.
setUnauthorizedHandler(() => {
  const auth = useAuth()
  if (!auth.isAuthed) return
  auth.logout()
  router.replace({ name: 'login', query: { next: router.currentRoute.value.fullPath } })
})
