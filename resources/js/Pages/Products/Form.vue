<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { Save, ArrowLeft, Upload, X } from '@lucide/vue'
import { ref } from 'vue'

const props = defineProps({
  product:    Object,
  categories: Array,
})

const toast = useToast()
const isEdit = !!props.product

const form = useForm({
  name:        props.product?.name ?? '',
  sku:         props.product?.sku ?? '',
  barcode:     props.product?.barcode ?? '',
  category_id: props.product?.category_id ?? '',
  unit:        props.product?.unit ?? 'pcs',
  buy_price:   props.product?.buy_price ?? 0,
  sell_price:  props.product?.sell_price ?? 0,
  stock:       props.product?.stock ?? 0,
  min_stock:   props.product?.min_stock ?? 5,
  description: props.product?.description ?? '',
  is_active:   props.product?.is_active ?? true,
  image:       null,
})

const imagePreview = ref(props.product?.image ? `/storage/${props.product.image}` : null)

function handleImage(e) {
  const file = e.target.files[0]
  if (!file) return
  form.image = file
  imagePreview.value = URL.createObjectURL(file)
}

function clearImage() {
  form.image = null
  imagePreview.value = null
}

const units = ['pcs', 'kg', 'gram', 'liter', 'ml', 'lusin', 'pack', 'roll', 'lembar', 'meter', 'box']

function submit() {
  if (isEdit) {
    form.transform((data) => ({
      ...data,
      _method: 'put',
    })).post(route('products.update', props.product.id))
  } else {
    form.post(route('products.store'))
  }
}
</script>

<template>
  <AppLayout>
    <template #title>{{ isEdit ? 'Edit Produk' : 'Tambah Produk' }}</template>

    <div class="page-header">
      <div class="flex items-center gap-3">
        <Link :href="route('products.index')" class="btn-ghost btn-sm">
          <ArrowLeft :size="16" />
        </Link>
        <div>
          <h1 class="page-title">{{ isEdit ? 'Edit Produk' : 'Tambah Produk Baru' }}</h1>
          <p class="page-subtitle">{{ isEdit ? `Mengedit: ${product.name}` : 'Isi form berikut untuk menambah produk baru' }}</p>
        </div>
      </div>
      <button @click="submit" class="btn-primary" :disabled="form.processing">
        <Save :size="16" />
        {{ form.processing ? 'Menyimpan...' : 'Simpan Produk' }}
      </button>
    </div>

    <div class="form-grid">
      <!-- Left Column: Main Info -->
      <div class="form-column">

        <!-- Basic Info Card -->
        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Informasi Produk</h3>
          </div>
          <div class="card-body space-y-4">
            <div>
              <label class="label">Nama Produk <span class="text-rose-500">*</span></label>
              <input v-model="form.name" type="text" class="input" :class="{ 'input-error': form.errors.name }" placeholder="cth: Kantong Plastik HD 30x40 1kg" />
              <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>
            <div class="grid-2">
              <div>
                <label class="label">SKU <span class="text-rose-500">*</span></label>
                <input v-model="form.sku" type="text" class="input" :class="{ 'input-error': form.errors.sku }" placeholder="cth: KP-HD-3040" />
                <p v-if="form.errors.sku" class="error-msg">{{ form.errors.sku }}</p>
              </div>
              <div>
                <label class="label">Barcode</label>
                <input v-model="form.barcode" type="text" class="input" placeholder="Scan atau ketik barcode" />
              </div>
            </div>
            <div class="grid-2">
              <div>
                <label class="label">Kategori</label>
                <select v-model="form.category_id" class="input">
                  <option value="">— Pilih Kategori —</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
              <div>
                <label class="label">Satuan <span class="text-rose-500">*</span></label>
                <select v-model="form.unit" class="input">
                  <option v-for="u in units" :key="u" :value="u">{{ u }}</option>
                </select>
              </div>
            </div>
            <div>
              <label class="label">Deskripsi</label>
              <textarea v-model="form.description" class="input" rows="3" placeholder="Deskripsi produk (opsional)" />
            </div>
          </div>
        </div>

        <!-- Pricing Card -->
        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Harga</h3>
          </div>
          <div class="card-body space-y-4">
            <div class="grid-2">
              <div>
                <label class="label">Harga Beli (HPP)</label>
                <div class="input-prefix">
                  <span>Rp</span>
                  <input v-model="form.buy_price" type="number" min="0" class="input" :class="{ 'input-error': form.errors.buy_price }" />
                </div>
                <p v-if="form.errors.buy_price" class="error-msg">{{ form.errors.buy_price }}</p>
              </div>
              <div>
                <label class="label">Harga Jual <span class="text-rose-500">*</span></label>
                <div class="input-prefix">
                  <span>Rp</span>
                  <input v-model="form.sell_price" type="number" min="0" class="input" :class="{ 'input-error': form.errors.sell_price }" />
                </div>
                <p v-if="form.errors.sell_price" class="error-msg">{{ form.errors.sell_price }}</p>
              </div>
            </div>
            <!-- Margin indicator -->
            <div v-if="form.sell_price > 0 && form.buy_price > 0" class="margin-info">
              <span class="text-xs text-slate-500">Margin</span>
              <span class="text-sm font-semibold text-emerald-600">
                {{ Math.round(((form.sell_price - form.buy_price) / form.sell_price) * 100) }}%
              </span>
              <span class="text-xs text-slate-500">
                (Rp {{ new Intl.NumberFormat('id-ID').format(form.sell_price - form.buy_price) }})
              </span>
            </div>
          </div>
        </div>

      </div>

      <!-- Right Column: Stock & Image -->
      <div class="form-column">

        <!-- Stock Card -->
        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Stok</h3>
          </div>
          <div class="card-body space-y-4">
            <div class="grid-2">
              <div>
                <label class="label">{{ isEdit ? 'Stok Saat Ini' : 'Stok Awal' }} <span class="text-rose-500">*</span></label>
                <input v-model="form.stock" type="number" min="0" class="input" :disabled="isEdit" :class="isEdit ? 'bg-slate-50 cursor-not-allowed' : ''" />
                <p v-if="isEdit" class="text-xs text-slate-400 mt-1">Ubah stok via tombol "Sesuaikan Stok"</p>
              </div>
              <div>
                <label class="label">Stok Minimum</label>
                <input v-model="form.min_stock" type="number" min="0" class="input" />
                <p class="text-xs text-slate-400 mt-1">Alert jika stok ≤ nilai ini</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Image Card -->
        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Foto Produk</h3>
          </div>
          <div class="card-body">
            <div v-if="imagePreview" class="image-preview">
              <img :src="imagePreview" alt="Preview" />
              <button class="image-remove" @click="clearImage">
                <X :size="14" />
              </button>
            </div>
            <label v-else class="image-upload">
              <input type="file" accept="image/*" class="hidden" @change="handleImage" />
              <Upload :size="24" class="text-slate-400" />
              <span class="text-sm text-slate-500">Klik untuk upload foto</span>
              <span class="text-xs text-slate-400">JPG, PNG, max 2MB</span>
            </label>
            <p v-if="form.errors.image" class="error-msg mt-2">{{ form.errors.image }}</p>
          </div>
        </div>

        <!-- Status Card -->
        <div class="card">
          <div class="card-body">
            <label class="flex items-center gap-3 cursor-pointer">
              <div class="relative">
                <input v-model="form.is_active" type="checkbox" class="sr-only" />
                <div :class="['toggle', form.is_active ? 'toggle--on' : '']" @click="form.is_active = !form.is_active">
                  <div class="toggle-thumb" />
                </div>
              </div>
              <div>
                <p class="text-sm font-medium text-slate-700">Produk Aktif</p>
                <p class="text-xs text-slate-400">Produk nonaktif tidak muncul di kasir</p>
              </div>
            </label>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 1024px) {
  .form-grid { grid-template-columns: 1fr; }
}

.form-column { display: flex; flex-direction: column; gap: 16px; }

.space-y-4 > * + * { margin-top: 16px; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } }

.input-prefix {
  display: flex; align-items: center;
  border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;
  background: white;
  transition: box-shadow 0.15s;
}
.input-prefix:focus-within { box-shadow: 0 0 0 2px #3b82f6; border-color: transparent; }
.input-prefix > span {
  padding: 0 12px; background: #f8fafc; color: #64748b;
  font-size: 0.85rem; height: 100%;
  display: flex; align-items: center;
  border-right: 1px solid #e2e8f0;
}
.input-prefix .input { border: none; border-radius: 0; padding-left: 10px; }
.input-prefix .input:focus { box-shadow: none; }

.margin-info {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; background: #f0fdf4; border-radius: 8px;
}

.image-preview {
  position: relative; width: 100%; aspect-ratio: 4/3;
  border-radius: 10px; overflow: hidden; background: #f1f5f9;
}
.image-preview img { width: 100%; height: 100%; object-fit: cover; }
.image-remove {
  position: absolute; top: 8px; right: 8px;
  width: 28px; height: 28px; border-radius: 6px;
  background: rgba(0,0,0,0.6); color: white; border: none;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.image-remove:hover { background: rgba(0,0,0,0.8); }

.image-upload {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; padding: 32px; border: 2px dashed #e2e8f0; border-radius: 10px;
  cursor: pointer; transition: all 0.15s;
}
.image-upload:hover { border-color: #3b82f6; background: #f8fafc; }

/* Toggle */
.toggle {
  width: 44px; height: 24px; border-radius: 99px;
  background: #e2e8f0; cursor: pointer; position: relative;
  transition: background 0.2s;
}
.toggle--on { background: #2563eb; }
.toggle-thumb {
  position: absolute; top: 2px; left: 2px;
  width: 20px; height: 20px; border-radius: 99px;
  background: white; transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle--on .toggle-thumb { transform: translateX(20px); }

.error-msg { font-size: 0.75rem; color: #e11d48; margin-top: 4px; }
</style>
