<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Edit2, Trash2, X } from '@lucide/vue'

const props = defineProps({
  suppliers: Array
})

const isModalOpen = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

const isDeleteModalOpen = ref(false)
const supplierToDelete = ref(null)

const form = useForm({
  name: '',
  phone: '',
  address: ''
})

const openModal = (supplier = null) => {
  if (supplier) {
    isEditing.value = true
    currentId.value = supplier.id
    form.name = supplier.name
    form.phone = supplier.phone || ''
    form.address = supplier.address || ''
  } else {
    isEditing.value = false
    currentId.value = null
    form.reset()
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  form.reset()
  form.clearErrors()
}

const submit = () => {
  if (isEditing.value) {
    form.put(route('suppliers.update', currentId.value), {
      onSuccess: () => closeModal()
    })
  } else {
    form.post(route('suppliers.store'), {
      onSuccess: () => closeModal()
    })
  }
}

const confirmDeleteSupplier = (supplier) => {
  supplierToDelete.value = supplier
  isDeleteModalOpen.value = true
}

const executeDelete = () => {
  if (supplierToDelete.value) {
    router.delete(route('suppliers.destroy', supplierToDelete.value.id), {
      onFinish: () => {
        isDeleteModalOpen.value = false
        supplierToDelete.value = null
      }
    })
  }
}
</script>

<template>
  <Head title="Data Supplier" />
  <AppLayout>
    <template #title>Data Supplier</template>
    
    <div class="page-header">
      <div>
        <h2 class="page-title">Daftar Supplier</h2>
        <p class="page-subtitle">Kelola data pemasok barang untuk stok masuk</p>
      </div>
      <button @click="openModal()" class="btn btn-primary">
        <Plus :size="18" /> Tambah Supplier
      </button>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Nama Supplier</th>
              <th>No. HP</th>
              <th>Alamat</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="suppliers.length === 0">
              <td colspan="4" class="text-center py-8 text-gray-500">Belum ada data supplier.</td>
            </tr>
            <tr v-for="supplier in suppliers" :key="supplier.id">
              <td class="font-medium">{{ supplier.name }}</td>
              <td>{{ supplier.phone || '-' }}</td>
              <td>{{ supplier.address || '-' }}</td>
              <td class="text-right">
                <div class="action-buttons">
                  <button @click="openModal(supplier)" class="btn-icon text-blue-600 hover:bg-blue-50">
                    <Edit2 :size="16" />
                  </button>
                  <button @click="confirmDeleteSupplier(supplier)" class="btn-icon text-red-600 hover:bg-red-50">
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal-header">
            <h3>{{ isEditing ? 'Edit Supplier' : 'Tambah Supplier' }}</h3>
            <button @click="closeModal" class="btn-icon"><X :size="20" /></button>
          </div>
          <form @submit.prevent="submit" class="modal-body">
            <div class="form-group">
              <label>Nama Supplier *</label>
              <input v-model="form.name" type="text" class="form-control" required />
              <span v-if="form.errors.name" class="text-error">{{ form.errors.name }}</span>
            </div>
            <div class="form-group">
              <label>No. HP</label>
              <input v-model="form.phone" type="text" class="form-control" />
              <span v-if="form.errors.phone" class="text-error">{{ form.errors.phone }}</span>
            </div>
            <div class="form-group">
              <label>Alamat</label>
              <textarea v-model="form.address" class="form-control" rows="3"></textarea>
              <span v-if="form.errors.address" class="text-error">{{ form.errors.address }}</span>
            </div>
            <div class="modal-footer">
              <button type="button" @click="closeModal" class="btn btn-secondary">Batal</button>
              <button type="submit" class="btn btn-primary" :disabled="form.processing">
                {{ isEditing ? 'Simpan Perubahan' : 'Tambah Data' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div v-if="isDeleteModalOpen" class="modal-overlay" @click.self="isDeleteModalOpen = false">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl w-80 shadow-2xl p-6 text-center space-y-4 modal-bounce">
          <!-- Clean, simple Icon -->
          <div class="mx-auto w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-450 flex items-center justify-center">
            <Trash2 :size="20" />
          </div>
          
          <!-- Title & brief text -->
          <div class="space-y-1.5">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hapus Supplier?</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-1">
              Apakah Anda yakin ingin menghapus supplier <span class="font-semibold text-slate-900 dark:text-white">"{{ supplierToDelete?.name }}"</span>?
            </p>
          </div>

          <!-- Actions -->
          <div class="grid grid-cols-2 gap-3 pt-2">
            <button 
              type="button" 
              @click="isDeleteModalOpen = false" 
              class="w-full py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl transition duration-150 cursor-pointer active:scale-95"
            >
              Batal
            </button>
            <button 
              type="button" 
              @click="executeDelete"
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
.page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
.page-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; }
.page-subtitle { font-size: 0.875rem; color: #64748b; margin: 4px 0 0; }

.card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 16px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
.table th { font-weight: 600; color: #475569; background: #f8fafc; }
.font-medium { font-weight: 500; color: #0f172a; }
.text-right { text-align: right; }
.text-center { text-align: center; }

.action-buttons { display: flex; justify-content: flex-end; gap: 8px; }
.btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; background: transparent; cursor: pointer; transition: 0.2s; }
.text-blue-600 { color: #2563eb; }
.text-red-600 { color: #dc2626; }
.hover\:bg-blue-50:hover { background: #eff6ff; }
.hover\:bg-red-50:hover { background: #fef2f2; }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-weight: 500; font-size: 0.875rem; cursor: pointer; border: none; transition: 0.2s; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }

.modal { max-width: 500px; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 0.875rem; font-weight: 500; color: #475569; }
.form-control { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; transition: 0.2s; font-family: inherit; }
.form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
.text-error { font-size: 0.75rem; color: #ef4444; }
.modal-footer { margin-top: 8px; display: flex; justify-content: flex-end; gap: 12px; }
</style>
