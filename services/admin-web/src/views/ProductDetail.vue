<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getProduct,
  getRecipe,
  setRecipe,
  listIngredients,
  listUnits,
  listCategories,
  type Product,
  type Recipe,
  type Ingredient,
  type Unit,
  type Category,
} from '../api/catalog'
import { ApiError } from '../api/client'
import { useAuth } from '../stores/auth'
import { formatKopecks } from '../format'

const { t } = useI18n()
const auth = useAuth()

const route = useRoute()
const productID = computed(() => route.params.id as string)

const product = ref<Product | null>(null)
const recipe = ref<Recipe | null>(null)
const ingredients = ref<Ingredient[]>([])
const units = ref<Unit[]>([])
const categories = ref<Category[]>([])

const loadError = ref<string | null>(null)
const loading = ref(false)

// Editable working copy. Each row carries the same fields as RecipeLine
// minus `id`, since on save we replace the entire recipe.
type DraftLine = { ingredient_id: string; qty: number; unit_id: string }
const draft = ref<DraftLine[]>([])

const submitting = ref(false)
const submitError = ref<string | null>(null)
const submitOk = ref(false)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [p, r, i, u, c] = await Promise.all([
      getProduct(productID.value),
      getRecipe(productID.value).catch(() => null), // recipe may not exist yet
      listIngredients(),
      listUnits(),
      listCategories(),
    ])
    product.value = p
    recipe.value = r
    ingredients.value = i ?? []
    units.value = u ?? []
    categories.value = c ?? []
    draft.value = (r?.lines ?? []).map((l) => ({
      ingredient_id: l.ingredient_id,
      qty: l.qty,
      unit_id: l.unit_id,
    }))
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : t('common.failedToLoad')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const ingredientLabel = (id: string) => {
  const i = ingredients.value.find((x) => x.id === id)
  return i ? `${i.name} (${i.sku})` : id
}
const unitCode = (id: string) => units.value.find((u) => u.id === id)?.code ?? id
const categoryName = (id?: string | null) => {
  if (!id) return t('productDetail.uncategorised')
  return categories.value.find((c) => c.id === id)?.name ?? t('common.none')
}

function addRow() {
  draft.value.push({
    ingredient_id: ingredients.value[0]?.id ?? '',
    qty: 0,
    unit_id: '',
  })
}

function removeRow(idx: number) {
  draft.value.splice(idx, 1)
}

// Default a row's unit_id to the ingredient's default_unit_id when the
// ingredient changes and the unit is empty / unmatched.
function onIngredientChange(idx: number) {
  const line = draft.value[idx]
  const ing = ingredients.value.find((i) => i.id === line.ingredient_id)
  if (ing && (!line.unit_id || line.unit_id === '')) {
    line.unit_id = ing.default_unit_id
  }
}

async function saveRecipe() {
  if (submitting.value) return
  submitError.value = null
  submitOk.value = false
  // Drop empty rows so users don't have to clear every blank.
  const lines = draft.value.filter((l) => l.ingredient_id && l.unit_id && l.qty > 0)
  submitting.value = true
  try {
    const updated = await setRecipe(productID.value, lines)
    recipe.value = updated
    submitOk.value = true
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
    <div class="flex items-center gap-3 mb-2">
      <RouterLink to="/products" class="text-sm text-slate-500 hover:text-slate-900">{{ t('productDetail.backProducts') }}</RouterLink>
    </div>
    <p v-if="loadError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mb-4">
      {{ loadError }}
    </p>

    <header v-if="product" class="mb-6">
      <h1 class="text-2xl font-semibold text-slate-900">{{ product.name }}</h1>
      <div class="text-sm text-slate-500 mt-1 flex flex-wrap gap-x-4">
        <span class="font-mono">{{ product.sku }}</span>
        <span>{{ categoryName(product.category_id) }}</span>
        <span class="font-medium text-slate-700">{{ formatKopecks(product.price_kopecks) }}</span>
      </div>
      <p v-if="product.description" class="text-sm text-slate-600 mt-2">{{ product.description }}</p>
    </header>

    <!-- Read-only recipe view for non-admins. Render a static table and
         skip the editor entirely. The same component handles both modes
         to avoid duplicating the row layout. -->
    <section v-if="!auth.isAdmin" class="bg-white rounded-lg shadow-sm p-5">
      <h2 class="text-base font-semibold text-slate-900 mb-4">{{ t('productDetail.recipe') }}</h2>
      <p v-if="!recipe || recipe.lines.length === 0" class="text-sm text-slate-500">
        {{ t('productDetail.noRecipe') }}
      </p>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <th class="py-2">{{ t('productDetail.col.ingredient') }}</th>
            <th class="py-2 w-32 text-right">{{ t('productDetail.col.qty') }}</th>
            <th class="py-2 w-32">{{ t('productDetail.col.unit') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="line in recipe.lines" :key="line.id" class="border-b border-slate-100">
            <td class="py-2">{{ ingredientLabel(line.ingredient_id) }}</td>
            <td class="py-2 text-right font-mono">{{ line.qty }}</td>
            <td class="py-2 font-mono">{{ unitCode(line.unit_id) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-else class="bg-white rounded-lg shadow-sm p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-slate-900">{{ t('productDetail.recipe') }}</h2>
        <button
          type="button"
          class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-800 rounded px-3 py-1.5"
          @click="addRow"
          :disabled="ingredients.length === 0"
        >
          {{ t('productDetail.addIngredient') }}
        </button>
      </div>

      <p v-if="ingredients.length === 0" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 px-3 py-2 rounded mb-3">
        {{ t('productDetail.noIngredientsYet') }}
        <RouterLink to="/ingredients" class="underline">{{ t('productDetail.ingredientsLink') }}</RouterLink>.
      </p>

      <table v-if="draft.length > 0" class="w-full text-sm mb-4">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <th class="py-2">{{ t('productDetail.col.ingredient') }}</th>
            <th class="py-2 w-32 text-right">{{ t('productDetail.col.qty') }}</th>
            <th class="py-2 w-32">{{ t('productDetail.col.unit') }}</th>
            <th class="py-2 w-12"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line, idx) in draft" :key="idx" class="border-b border-slate-100">
            <td class="py-2 pr-3">
              <select
                v-model="line.ingredient_id"
                @change="onIngredientChange(idx)"
                class="w-full px-2 py-1.5 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
              >
                <option v-for="i in ingredients" :key="i.id" :value="i.id">
                  {{ ingredientLabel(i.id) }}
                </option>
              </select>
            </td>
            <td class="py-2 pr-3">
              <input
                v-model.number="line.qty"
                type="number"
                min="1"
                step="1"
                class="w-full px-2 py-1.5 text-right border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
              />
            </td>
            <td class="py-2 pr-3">
              <select
                v-model="line.unit_id"
                class="w-full px-2 py-1.5 border border-slate-300 rounded text-sm focus:outline-none focus:border-slate-900"
              >
                <option value="" disabled>{{ t('common.none') }}</option>
                <option v-for="u in units" :key="u.id" :value="u.id">{{ unitCode(u.id) }}</option>
              </select>
            </td>
            <td class="py-2 text-right">
              <button
                type="button"
                class="text-rose-600 hover:text-rose-800 text-sm"
                @click="removeRow(idx)"
                :aria-label="t('common.remove')"
              >
                ✕
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="text-sm text-slate-500 mb-4">{{ t('productDetail.noLines') }}</p>

      <div class="flex items-center justify-end gap-3">
        <p v-if="submitError" class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-3 py-2 rounded mr-auto">
          {{ submitError }}
        </p>
        <p v-if="submitOk" class="text-sm text-emerald-700 mr-auto">{{ t('common.saved') }}</p>
        <button
          type="button"
          :disabled="submitting"
          class="bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
          @click="saveRecipe"
        >
          {{ submitting ? t('productDetail.savingRecipe') : t('productDetail.saveRecipe') }}
        </button>
      </div>
    </section>
  </div>
</template>
