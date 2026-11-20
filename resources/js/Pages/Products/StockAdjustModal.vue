<script setup>
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { ArrowUpCircle, X } from '@lucide/vue'

const props = defineProps({
  product: Object,
})
const emit = defineEmits(['close'])
const toast = useToast()

const form = useForm({
  type: 'in',
  qty: 1,
  notes: '',
})

function submit() {
  form.post(route('products.adjustStock', props.product.id), {
    onSuccess: () => {
      emit('close')
    },
  })
}
</script>

<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('close')">
      <div class="modal">
        <div class="modal__header">
          <div>
            <h3>Sesuaikan Stok</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ product.name }}</p>
          </div>
          <button class="modal__close" @click="$emit('close')">
            <X :size="16" />
          </button>
        </div>
        <form @submit.prevent="submit" class="modal__body">
          <!-- Current stock -->
          <div class="stock-info">
            <span class="text-sm text-slate-500">Stok saat ini</span>
            <span class="text-2xl font-bold text-slate-900">{{ product.stock }} <span class="text-sm font-normal text-slate-400">{{ product.unit }}</span></span>
          </div>

          <div class="form-group">
            <label class="label">Jenis</label>
            <div class="type-selector">
              <label class="type-option" :class="{ active: form.type === 'in' }">
                <input v-model="form.type" type="radio" value="in" class="hidden" />
                <span>+ Stok Masuk</span>
              </label>
              <label class="type-option" :class="{ active: form.type === 'adjustment' }">
                <input v-model="form.type" type="radio" value="adjustment" class="hidden" />
                <span>Koreksi Stok</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="label">
              {{ form.type === 'in' ? 'Jumlah Tambahan' : 'Stok Fisik Baru (jumlah aktual)' }}
              <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.qty"
              type="number"
              :min="form.type === 'in' ? 1 : 0"
              class="input"
              :class="{ 'input-error': form.errors.qty }"
              :placeholder="form.type === 'in' ? 'cth: 10' : 'cth: 50 (stok hasil hitung fisik)'"
            />
            <p v-if="form.type === 'adjustment'" class="text-xs text-slate-400 mt-1">
              Masukkan jumlah stok aktual hasil hitung fisik. Sistem akan menghitung selisihnya.
            </p>
            <p v-if="form.errors.qty" class="error-msg">{{ form.errors.qty }}</p>
          </div>

          <div class="form-group">
            <label class="label">Keterangan <span class="text-rose-500">*</span></label>
            <textarea v-model="form.notes" class="input" rows="3" placeholder="cth: Restock dari supplier, Koreksi hitung fisik..." :class="{ 'input-error': form.errors.notes }" />
            <p v-if="form.errors.notes" class="error-msg">{{ form.errors.notes }}</p>
          </div>

          <div class="modal__footer">
            <button type="button" class="btn-secondary" @click="$emit('close')">Batal</button>
            <button type="submit" class="btn-success" :disabled="form.processing">
              <ArrowUpCircle :size="15" />
              {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal { max-width: 420px; }
.modal__header {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid #f1f5f9;
}
.modal__header h3 { font-size: 1rem; font-weight: 600; color: #0f172a; }
.modal__close {
  width: 28px; height: 28px; border-radius: 6px;
  background: #f1f5f9; border: none; cursor: pointer; color: #64748b;
  display: flex; align-items: center; justify-content: center;
}
.modal__close:hover { background: #e2e8f0; }
.modal__body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.modal__footer { display: flex; justify-content: flex-end; gap: 8px; padding-top: 4px; }

.stock-info {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 16px; background: #f8fafc; border-radius: 10px; gap: 4px;
}

.type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.type-option {
  display: flex; align-items: center; justify-content: center;
  padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;
  cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #475569;
  transition: all 0.15s;
}
.type-option.active { border-color: #526d8a; background: #eff6ff; color: #526d8a; }

.form-group { display: flex; flex-direction: column; }
.error-msg { font-size: 0.75rem; color: #e11d48; margin-top: 4px; }
</style>
