<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { Plus, Pencil, Trash2, Search, Tag } from '@lucide/vue'
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
  categories: Object,
  filters: Object,
})

const toast = useToast()
const page  = usePage()

// Flash messages
watch(() => page.props.flash, (flash) => {
  if (flash.success) toast.success(flash.success)
  if (flash.error)   toast.error(flash.error)
}, { immediate: true })

// Search
const search = ref(props.filters?.search ?? '')
let searchTimer = null
function doSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(route('categories.index'), { search: search.value }, { preserveState: true })
  }, 400)
}

// Modal
const showModal = ref(false)
const editTarget = ref(null)

const form = useForm({
  name: '',
  description: '',
  is_active: true,
})

function openCreate() {
  editTarget.value = null
  form.reset()
  form.is_active = true
  showModal.value = true
}

function openEdit(cat) {
  editTarget.value = cat
  form.name        = cat.name
  form.description = cat.description ?? ''
  form.is_active   = cat.is_active
  showModal.value  = true
}

function closeModal() {
  showModal.value = false
  form.reset()
}

function submit() {
  if (editTarget.value) {
    form.put(route('categories.update', editTarget.value.id), {
      onSuccess: () => closeModal(),
    })
  } else {
    form.post(route('categories.store'), {
      onSuccess: () => closeModal(),
    })
  }
}

const deleteTarget = ref(null)
function confirmDelete(cat) {
  deleteTarget.value = cat
}
function performDelete() {
  if (!deleteTarget.value) return
  router.delete(route('categories.destroy', deleteTarget.value.id), {
    onSuccess: () => {
      deleteTarget.value = null
    }
  })
}
</script>

<template>
  <AppLayout>
    <template #title>Kategori Produk</template>

    <div class="page-header">
      <div>
        <h1 class="page-title">Kategori Produk</h1>
        <p class="page-subtitle">Kelola kategori untuk mengorganisir produk</p>
      </div>
      <button class="btn-primary" @click="openCreate">
        <Plus :size="16" />
        Tambah Kategori
      </button>
    </div>

    <!-- Search -->
    <div class="search-bar">
      <Search :size="16" class="search-icon" />
      <input
        v-model="search"
        @input="doSearch"
        type="text"
        placeholder="Cari kategori..."
        class="search-input"
      />
    </div>

    <!-- Table -->
    <div class="card mt-4">
      <div v-if="categories.data.length === 0" class="empty-state">
        <Tag :size="40" />
        <p>Belum ada kategori</p>
      </div>

      <div v-else class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Nama Kategori</th>
              <th>Deskripsi</th>
              <th>Jumlah Produk</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cat in categories.data" :key="cat.id">
              <td>
                <div class="flex items-center gap-3">
                  <div class="cat-icon">
                    <Tag :size="14" />
                  </div>
                  <span class="font-medium text-slate-900">{{ cat.name }}</span>
                </div>
              </td>
              <td class="text-slate-500 text-sm">{{ cat.description ?? '—' }}</td>
              <td>
                <span class="badge-neutral">{{ cat.products_count }} produk</span>
              </td>
              <td>
                <span :class="cat.is_active ? 'badge-success' : 'badge-danger'">
                  {{ cat.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td>
                <div class="flex items-center gap-2">
                  <button class="btn-ghost btn-sm" @click="openEdit(cat)">
                    <Pencil :size="14" />
                  </button>
                  <button class="btn-ghost btn-sm text-rose-500 hover:bg-rose-50" @click="confirmDelete(cat)">
                    <Trash2 :size="14" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="categories.last_page > 1" class="pagination">
        <button
          v-for="link in categories.links"
          :key="link.label"
          v-html="link.label"
          :disabled="!link.url"
          :class="['page-btn', link.active ? 'page-btn--active' : '']"
          @click="link.url && router.get(link.url)"
        />
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal">
          <div class="modal__header">
            <h3>{{ editTarget ? 'Edit Kategori' : 'Tambah Kategori' }}</h3>
            <button class="modal__close" @click="closeModal">✕</button>
          </div>
          <form @submit.prevent="submit" class="modal__body">
            <div class="form-group">
              <label class="label">Nama Kategori <span class="text-rose-500">*</span></label>
              <input v-model="form.name" type="text" class="input" :class="{ 'input-error': form.errors.name }" placeholder="cth: Plastik Kantong" />
              <p v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</p>
            </div>
            <div class="form-group">
              <label class="label">Deskripsi</label>
              <textarea v-model="form.description" class="input" rows="3" placeholder="Deskripsi kategori (opsional)" />
            </div>
            <div class="form-group">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-primary-600" />
                <span class="label mb-0">Aktif</span>
              </label>
            </div>
            <div class="modal__footer">
              <button type="button" class="btn-secondary" @click="closeModal">Batal</button>
              <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Menyimpan...' : (editTarget ? 'Simpan Perubahan' : 'Tambah Kategori') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

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
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hapus Kategori?</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-1">
              Apakah Anda yakin ingin menghapus kategori <span class="font-semibold text-slate-900 dark:text-white">"{{ deleteTarget.name }}"</span>?
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
.search-bar {
  position: relative;
  max-width: 380px;
}
.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}
.search-input {
  @apply input pl-9;
}

.table-wrapper { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 16px; text-align: left; font-size: 0.85rem; }
th { font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background: #f8fafc; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
td { color: #374151; border-bottom: 1px solid #f8fafc; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }

.cat-icon {
  width: 32px; height: 32px;
  background: #eff6ff;
  color: #2563eb;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 16px;
  border-top: 1px solid #f1f5f9;
}
.page-btn {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.8rem;
  border: 1px solid #e2e8f0;
  background: white;
  color: #475569;
  cursor: pointer;
  transition: all 0.1s;
}
.page-btn:hover:not(:disabled) { background: #f1f5f9; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn--active { background: #2563eb; color: white; border-color: #2563eb; }

/* Empty state */
.empty-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 12px; padding: 60px; color: #cbd5e1;
}
.empty-state p { font-size: 0.9rem; }

/* Modal */
.modal { max-width: 480px; }
.modal__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px; border-bottom: 1px solid #f1f5f9;
}
.modal__header h3 { font-size: 1rem; font-weight: 600; color: #0f172a; }
.modal__close {
  width: 28px; height: 28px; border-radius: 6px;
  background: #f1f5f9; border: none; cursor: pointer;
  color: #64748b; font-size: 0.85rem;
  display: flex; align-items: center; justify-content: center;
}
.modal__close:hover { background: #e2e8f0; }
.modal__body { padding: 24px; display: flex; flex-direction: column; gap: 16px; }
.modal__footer { display: flex; justify-content: flex-end; gap: 8px; padding-top: 8px; }

.form-group { display: flex; flex-direction: column; }
.error-msg { font-size: 0.75rem; color: #e11d48; margin-top: 4px; }
</style>
