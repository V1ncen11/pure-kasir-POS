<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { watch } from 'vue'
import {
  Plus, Search, Filter, Package, Pencil, Trash2,
  ArrowUpCircle, AlertTriangle, ChevronDown,
} from '@lucide/vue'
import StockAdjustModal from './StockAdjustModal.vue'

const props = defineProps({
  products: Object,
  categories: Array,
  filters: Object,
})

const toast = useToast()
const page  = usePage()

watch(() => page.props.flash, (flash) => {
  if (flash.success) toast.success(flash.success)
  if (flash.error)   toast.error(flash.error)
}, { immediate: true })

// Filters
const search    = ref(props.filters?.search ?? '')
const category  = ref(props.filters?.category ?? '')
const lowStock  = ref(props.filters?.low_stock ?? false)
let searchTimer = null

function applyFilters() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(route('products.index'), {
      search:    search.value,
      category:  category.value,
      low_stock: lowStock.value ? '1' : '',
    }, { preserveState: true })
  }, 400)
}

// Delete
const deleteTarget = ref(null)
function confirmDelete(product) {
  deleteTarget.value = product
}
function performDelete() {
  if (!deleteTarget.value) return
  router.delete(route('products.destroy', deleteTarget.value.id), {
    onSuccess: () => {
      deleteTarget.value = null
    }
  })
}

// Stock adjust modal
const adjustTarget = ref(null)
function openAdjust(product) { adjustTarget.value = product }
function closeAdjust()        { adjustTarget.value = null }

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value ?? 0)
}
</script>

<template>
  <AppLayout>
    <template #title>Produk</template>

    <div class="page-header">
      <div>
        <h1 class="page-title">Daftar Produk</h1>
        <p class="page-subtitle">{{ products.total }} produk terdaftar</p>
      </div>
      <Link :href="route('products.create')" class="btn-primary">
        <Plus :size="16" />
        Tambah Produk
      </Link>
    </div>

    <!-- Filters -->
    <div class="filters">
      <div class="search-bar">
        <Search :size="15" class="search-icon" />
        <input v-model="search" @input="applyFilters" type="text" placeholder="Cari nama, SKU, barcode..." class="search-input" />
      </div>

      <select v-model="category" @change="applyFilters" class="input w-auto min-w-40">
        <option value="">Semua Kategori</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>

      <label class="flex items-center gap-2 cursor-pointer select-none">
        <input v-model="lowStock" @change="applyFilters" type="checkbox" class="w-4 h-4 rounded text-amber-500" />
        <span class="text-sm text-amber-600 font-medium flex items-center gap-1">
          <AlertTriangle :size="14" /> Stok Menipis
        </span>
      </label>
    </div>

    <!-- Table -->
    <div class="card mt-4">
      <div v-if="products.data.length === 0" class="empty-state">
        <Package :size="40" />
        <p>Tidak ada produk ditemukan</p>
      </div>

      <div v-else class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Produk</th>
              <th>Kategori</th>
              <th>Harga Jual</th>
              <th>Stok</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products.data" :key="product.id">
              <td>
                <div class="product-cell">
                  <div class="product-img">
                    <img v-if="product.image" :src="`/storage/${product.image}`" :alt="product.name" />
                    <Package v-else :size="16" />
                  </div>
                  <div>
                    <p class="font-medium text-slate-900">{{ product.name }}</p>
                    <p class="text-xs text-slate-400">SKU: {{ product.sku }}</p>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge-neutral">{{ product.category?.name ?? '—' }}</span>
              </td>
              <td class="font-semibold text-slate-800">{{ formatCurrency(product.sell_price) }}</td>
              <td>
                <div class="stock-cell">
                  <span
                    class="font-semibold"
                    :class="product.stock <= product.min_stock ? 'text-rose-600' : 'text-slate-800'"
                  >
                    {{ product.stock }}
                  </span>
                  <span class="text-xs text-slate-400">{{ product.unit }}</span>
                  <AlertTriangle v-if="product.stock <= product.min_stock" :size="14" class="text-amber-500" />
                </div>
              </td>
              <td>
                <span :class="product.is_active ? 'badge-success' : 'badge-danger'">
                  {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td>
                <div class="flex items-center gap-1">
                  <button class="btn-ghost btn-sm" title="Tambah Stok" @click="openAdjust(product)">
                    <ArrowUpCircle :size="15" class="text-emerald-600" />
                  </button>
                  <Link :href="route('products.edit', product.id)" class="btn-ghost btn-sm">
                    <Pencil :size="14" />
                  </Link>
                  <button class="btn-ghost btn-sm text-rose-500 hover:bg-rose-50" @click="confirmDelete(product)">
                    <Trash2 :size="14" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="products.last_page > 1" class="pagination">
        <button
          v-for="link in products.links"
          :key="link.label"
          v-html="link.label"
          :disabled="!link.url"
          :class="['page-btn', link.active ? 'page-btn--active' : '']"
          @click="link.url && router.get(link.url)"
        />
      </div>
    </div>

    <!-- Stock Adjust Modal -->
    <StockAdjustModal
      v-if="adjustTarget"
      :product="adjustTarget"
      @close="closeAdjust"
    />

    <!-- Custom Delete Confirmation Modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl w-80 shadow-2xl p-6 text-center space-y-4 modal-bounce">
        <!-- Clean, simple Icon -->
        <div class="mx-auto w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-450 flex items-center justify-center">
          <Trash2 :size="20" />
        </div>
        
        <!-- Title & brief text -->
        <div class="space-y-1.5">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hapus Produk?</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-1">
            Apakah Anda yakin ingin menghapus <span class="font-semibold text-slate-900 dark:text-white">"{{ deleteTarget.name }}"</span>?
          </p>
        </div>

        <!-- Actions -->
        <div class="grid grid-cols-2 gap-3 pt-2">
          <button 
            type="button" 
            @click="deleteTarget = null" 
            class="w-full py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl transition duration-150 cursor-pointer active:scale-95"
          >
            Batal
          </button>
          <button 
            type="button" 
            @click="performDelete"
            class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl transition duration-150 shadow-md shadow-rose-600/10 cursor-pointer active:scale-95"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
.search-bar { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-input { @apply input pl-9; width: 100%; }

.table-wrapper { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 16px; text-align: left; font-size: 0.85rem; }
th { font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
td { color: #374151; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }

.product-cell { display: flex; align-items: center; gap: 12px; }
.product-img {
  width: 40px; height: 40px; min-width: 40px;
  border-radius: 8px; background: #f1f5f9;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; overflow: hidden;
}
.product-img img { width: 100%; height: 100%; object-fit: cover; }

.stock-cell { display: flex; align-items: center; gap: 6px; }

.pagination {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  padding: 16px; border-top: 1px solid #f1f5f9;
}
.page-btn {
  padding: 6px 12px; border-radius: 6px; font-size: 0.8rem;
  border: 1px solid #e2e8f0; background: white; color: #475569;
  cursor: pointer; transition: all 0.1s;
}
.page-btn:hover:not(:disabled) { background: #f1f5f9; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn--active { background: #2563eb; color: white; border-color: #2563eb; }

.empty-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 12px; padding: 60px; color: #cbd5e1;
}
</style>
