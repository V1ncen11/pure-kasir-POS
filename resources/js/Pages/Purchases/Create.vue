<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { Plus, Trash2, Save, ArrowLeft, Search, AlertTriangle } from '@lucide/vue'

const props = defineProps({
  suppliers: Array,
  products: Array
})

const form = useForm({
  supplier_id: '',
  date: new Date().toISOString().split('T')[0],
  reference_no: '',
  notes: '',
  items: []
})

const searchQuery = ref('')
const highlightedIndex = ref(-1)

watch(searchQuery, () => {
  highlightedIndex.value = -1
})

const highlightNext = () => {
  if (filteredProducts.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value + 1) % filteredProducts.value.length
}

const highlightPrev = () => {
  if (filteredProducts.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value - 1 + filteredProducts.value.length) % filteredProducts.value.length
}

const selectHighlighted = () => {
  if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredProducts.value.length) {
    addProduct(filteredProducts.value[highlightedIndex.value])
  } else if (filteredProducts.value.length === 1) {
    // If only one product, pressing enter selects it even without highlighting
    addProduct(filteredProducts.value[0])
  }
}

const filteredProducts = computed(() => {
  if (!searchQuery.value) return []
  const query = searchQuery.value.toLowerCase()
  return props.products.filter(p => 
    p.name.toLowerCase().includes(query) || 
    (p.sku && p.sku.toLowerCase().includes(query))
  ).slice(0, 5) // Limit results
})

const addProduct = (product) => {
  // Check if already in list
  const existing = form.items.find(i => i.product_id === product.id)
  if (existing) {
    existing.qty += 1
  } else {
    form.items.push({
      product_id: product.id,
      product: product,
      qty: 1,
      purchase_price: product.price // Default to current selling price or 0, adjust as needed
    })
  }
  searchQuery.value = ''
}

const removeItem = (index) => {
  form.items.splice(index, 1)
}

const totalAmount = computed(() => {
  return form.items.reduce((total, item) => {
    const qty = Number(item.qty) || 0;
    const price = Number(item.purchase_price) || 0;
    return total + (qty * price)
  }, 0)
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value ?? 0)
}

const lowStockProducts = computed(() => {
  return (props.products || [])
    .filter(p => p.stock <= p.min_stock)
    .sort((a, b) => a.stock - b.stock)
})

const getQtyInCart = (productId) => {
  const item = form.items.find(i => i.product_id === productId)
  return item ? item.qty : 0
}

const submit = () => {
  if (form.items.length === 0) {
    alert('Tambahkan minimal 1 produk!')
    return
  }
  form.post(route('purchases.store'))
}
</script>

<template>
  <Head title="Tambah Stok Masuk" />
  <AppLayout>
    <template #title>Tambah Stok Masuk</template>
    
    <div class="page-header">
      <div class="flex items-center gap-4">
        <Link :href="route('purchases.index')" class="btn-icon bg-white border border-gray-200">
          <ArrowLeft :size="18" />
        </Link>
        <div>
          <h2 class="page-title">Tambah Stok Masuk Baru</h2>
          <p class="page-subtitle">Catat pembelian barang dari supplier</p>
        </div>
      </div>
    </div>

    <form @submit.prevent="submit" class="main-grid">
      <!-- Left Column: Items List -->
      <div class="main-column">
        <div class="card">
          <div class="card-header">
            <h3>Daftar Barang Masuk</h3>
          </div>
          <div class="card-body">
            
            <!-- Search Product -->
            <div class="search-box">
              <Search class="search-icon" :size="18" />
              <input 
                v-model="searchQuery" 
                type="text" 
                class="form-control pl-10" 
                placeholder="Cari produk (nama atau SKU)..."
                @keydown.down.prevent="highlightNext"
                @keydown.up.prevent="highlightPrev"
                @keydown.enter.prevent="selectHighlighted"
              />
              
              <!-- Search Results Dropdown -->
              <div v-if="searchQuery && filteredProducts.length > 0" class="search-results">
                <button 
                  v-for="(product, idx) in filteredProducts" 
                  :key="product.id"
                  type="button"
                  @click="addProduct(product)"
                  @mouseenter="highlightedIndex = idx"
                  class="search-result-item"
                  :class="{ 'search-item-highlighted': idx === highlightedIndex }"
                >
                  <div class="font-medium">{{ product.name }}</div>
                  <div class="text-sm text-gray-500">Stok saat ini: {{ product.stock }}</div>
                </button>
              </div>
              <div v-else-if="searchQuery && filteredProducts.length === 0" class="search-results p-4 text-center text-gray-500">
                Produk tidak ditemukan
              </div>
            </div>

            <!-- Items Table -->
            <div class="items-table-wrapper mt-4">
              <table class="table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th width="120">Qty Masuk</th>
                    <th width="180">Harga Beli / Pcs</th>
                    <th width="180">Subtotal</th>
                    <th width="50"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="form.items.length === 0">
                    <td colspan="5" class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
                      Belum ada barang yang ditambahkan. Silakan cari produk di atas.
                    </td>
                  </tr>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>
                      <div class="font-medium">{{ item.product.name }}</div>
                      <div class="text-xs text-gray-500">Stok sblmnya: {{ item.product.stock }}</div>
                    </td>
                    <td>
                      <input 
                        v-model="item.qty" 
                        type="number" 
                        min="1" 
                        class="form-control text-center" 
                        required
                      />
                    </td>
                    <td>
                      <input 
                        v-model="item.purchase_price" 
                        type="number" 
                        min="0" 
                        class="form-control" 
                        required
                      />
                    </td>
                    <td class="font-semibold">
                      {{ formatCurrency((Number(item.qty) || 0) * (Number(item.purchase_price) || 0)) }}
                    </td>
                    <td class="text-right">
                      <button type="button" @click="removeItem(index)" class="btn-icon text-red-600 hover:bg-red-50">
                        <Trash2 :size="16" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>

      <!-- Right Column: Form Details -->
      <div class="side-column">
        <div class="card">
          <div class="card-header">
            <h3>Informasi Pembelian</h3>
          </div>
          <div class="card-body form-body">
            
            <div class="form-group">
              <label>Tanggal Masuk *</label>
              <input v-model="form.date" type="date" class="form-control" required />
              <span v-if="form.errors.date" class="text-error">{{ form.errors.date }}</span>
            </div>

            <div class="form-group">
              <label>Supplier *</label>
              <select v-model="form.supplier_id" class="form-control" required>
                <option value="" disabled>Pilih Supplier...</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
              <span v-if="form.errors.supplier_id" class="text-error">{{ form.errors.supplier_id }}</span>
            </div>

            <div class="form-group">
              <label>No. Referensi / Nota (Opsional)</label>
              <input v-model="form.reference_no" type="text" class="form-control" placeholder="Contoh: INV-2023001" />
              <span v-if="form.errors.reference_no" class="text-error">{{ form.errors.reference_no }}</span>
            </div>

            <div class="form-group">
              <label>Catatan</label>
              <textarea v-model="form.notes" class="form-control" rows="3" placeholder="Catatan tambahan..."></textarea>
              <span v-if="form.errors.notes" class="text-error">{{ form.errors.notes }}</span>
            </div>

            <div class="summary-box mt-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-gray-600">Total Item</span>
                <span class="font-semibold">{{ form.items.length }} Item</span>
              </div>
              <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                <span class="font-semibold">Total Biaya</span>
                <span class="text-xl font-bold text-primary-600">{{ formatCurrency(totalAmount) }}</span>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-full mt-4 justify-center" :disabled="form.processing">
              <Save :size="18" /> Simpan Stok Masuk
            </button>
            <span v-if="form.errors.items" class="text-error text-center mt-2 block">{{ form.errors.items }}</span>
          </div>
        </div>

        <!-- Rekomendasi Stok Menipis -->
        <div v-if="lowStockProducts.length > 0" class="card mt-4 low-stock-card">
          <div class="card-header flex items-center justify-between bg-amber-50/50 dark:bg-amber-955/10 border-b border-amber-100/50 dark:border-amber-900/30" style="padding: 12px 16px;">
            <h3 class="flex items-center gap-2" style="color: #b45309; font-weight: 700; font-size: 0.9rem; margin: 0;">
              <AlertTriangle :size="16" class="text-amber-600 animate-bounce" />
              Rekomendasi Restock
            </h3>
            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
              {{ lowStockProducts.length }} Produk
            </span>
          </div>
          <div class="card-body p-0 low-stock-list">
            <div v-for="product in lowStockProducts" :key="product.id" class="low-stock-item hover:bg-amber-50/20 transition">
              <div style="min-width: 0; flex: 1; padding-right: 8px;">
                <div class="font-semibold text-slate-800 truncate" style="font-size: 0.82rem; color: #1e293b;">
                  {{ product.name }}
                </div>
                <div class="text-gray-500 mt-0.5" style="font-size: 0.72rem;">
                  Stok: <span class="font-bold text-red-600">{{ product.stock }}</span> <span style="opacity: 0.6;">/ {{ product.min_stock }} {{ product.unit }}</span>
                </div>
              </div>
              
              <button
                type="button"
                @click="addProduct(product)"
                class="btn-add-low-stock"
                :class="{ 'btn-add-low-stock--added': getQtyInCart(product.id) > 0 }"
              >
                <template v-if="getQtyInCart(product.id) > 0">
                  <span>+{{ getQtyInCart(product.id) }}</span>
                </template>
                <template v-else>
                  <Plus :size="12" />
                  <span>Tambah</span>
                </template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>

  </AppLayout>
</template>

<style scoped>
.page-header { margin-bottom: 24px; }
.page-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; }
.page-subtitle { font-size: 0.875rem; color: #64748b; margin: 4px 0 0; }

.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.gap-4 { gap: 16px; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
.mb-2 { margin-bottom: 8px; }
.pt-2 { padding-top: 8px; }
.p-4 { padding: 16px; }
.w-full { width: 100%; }
.block { display: block; }
.border-t { border-top-width: 1px; border-top-style: solid; }
.border-gray-200 { border-color: #e5e7eb; }
.bg-white { background-color: white; }
.text-xl { font-size: 1.25rem; }

.main-grid {
  display: grid;
  grid-template-columns: 1fr 350px;
  gap: 20px;
}

@media (max-width: 1024px) {
  .main-grid { grid-template-columns: 1fr; }
}

.card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: 100%; }
.card-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
.card-header h3 { margin: 0; font-size: 1rem; font-weight: 600; color: #0f172a; }
.card-body { padding: 20px; }

/* Search Box */
.search-box { position: relative; }
.search-icon { position: absolute; left: 12px; top: 11px; color: #94a3b8; }
.search-box .form-control { padding-left: 40px; }
.search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
  z-index: 10;
  max-height: 300px;
  overflow-y: auto;
}
.search-result-item {
  width: 100%;
  text-align: left;
  padding: 12px 16px;
  border: none;
  background: transparent;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: 0.1s;
}
.search-result-item:hover { background: #f8fafc; }
.search-result-item:last-child { border-bottom: none; }
.search-item-highlighted {
  background: #f0f7ff !important;
  border-left: 4px solid #526d8a !important;
  padding-left: 12px;
}

/* Table */
.items-table-wrapper { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; vertical-align: middle; }
.table th { font-weight: 600; color: #475569; background: #f8fafc; border-radius: 6px; }

/* Forms */
.form-body { display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 0.875rem; font-weight: 500; color: #475569; }
.form-control { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; transition: 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #526d8a; box-shadow: 0 0 0 3px rgba(82, 109, 138, 0.12); }
.text-error { font-size: 0.75rem; color: #ef4444; }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-weight: 500; font-size: 0.875rem; cursor: pointer; border: none; transition: 0.2s; }
.btn-primary { background: #526d8a; color: white; }
.btn-primary:hover { background: #435a74; }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

.btn-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: none; background: transparent; cursor: pointer; transition: 0.2s; }
.text-red-600 { color: #dc2626; }
.hover\:bg-red-50:hover { background: #fef2f2; }

.summary-box { background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0; }

/* Rekomendasi Stok Menipis */
.low-stock-card {
  height: auto !important;
}
.low-stock-list {
  max-height: 250px;
  overflow-y: auto;
}
.low-stock-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.low-stock-item:last-child {
  border-bottom: none;
}
.btn-add-low-stock {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background-color: #fffbeb;
  color: #d97706;
  border: 1px solid #fde68a;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.btn-add-low-stock:hover {
  background-color: #fef3c7;
  color: #b45309;
  border-color: #fcd34d;
}
.btn-add-low-stock--added {
  background-color: #d97706;
  color: white;
  border-color: #d97706;
}
.btn-add-low-stock--added:hover {
  background-color: #b45309;
  color: white;
  border-color: #b45309;
}
</style>
