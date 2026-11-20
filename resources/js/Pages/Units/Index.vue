<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Edit2, Trash2, X } from '@lucide/vue'

const props = defineProps({
  units: Array
})

const isModalOpen = ref(false)
const isEditing = ref(false)
const currentId = ref(null)

const isDeleteModalOpen = ref(false)
const unitToDelete = ref(null)

const form = useForm({
  name: ''
})

const openModal = (unit = null) => {
  if (unit) {
    isEditing.value = true
    currentId.value = unit.id
    form.name = unit.name
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
    form.put(route('satuan.update', currentId.value), {
      onSuccess: () => closeModal()
    })
  } else {
    form.post(route('satuan.store'), {
      onSuccess: () => closeModal()
    })
  }
}

const confirmDeleteUnit = (unit) => {
  unitToDelete.value = unit
  isDeleteModalOpen.value = true
}

const executeDelete = () => {
  if (unitToDelete.value) {
    router.delete(route('satuan.destroy', unitToDelete.value.id), {
      onFinish: () => {
        isDeleteModalOpen.value = false
        unitToDelete.value = null
      }
    })
  }
}
</script>

<template>
  <Head title="Data Satuan" />
  <AppLayout>
    <template #title>Data Satuan</template>

    <!-- Header -->
    <div class="units-header">
      <div>
        <h2 class="units-title">Kelola Satuan Produk</h2>
        <p class="units-subtitle">Tambah, ubah, atau hapus satuan yang tersedia untuk produk toko Anda</p>
      </div>
      <button @click="openModal()" class="btn-add">
        <Plus :size="16" />
        <span>Tambah Satuan</span>
      </button>
    </div>

    <!-- Stats bar -->
    <div class="stats-bar">
      <span class="stats-count">
        <span class="stats-num">{{ units.length }}</span>
        satuan terdaftar
      </span>
    </div>

    <!-- Units Grid -->
    <div class="units-grid">
      <div
        v-for="unit in units"
        :key="unit.id"
        class="unit-pill"
      >
        <span class="unit-name">{{ unit.name }}</span>
        <div class="unit-actions">
          <button @click="openModal(unit)" class="unit-btn unit-btn-edit" title="Edit">
            <Edit2 :size="13" />
          </button>
          <button @click="confirmDeleteUnit(unit)" class="unit-btn unit-btn-delete" title="Hapus">
            <Trash2 :size="13" />
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="units.length === 0" class="empty-state">
        <p>Belum ada satuan. Klik <strong>Tambah Satuan</strong> untuk mulai.</p>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
        <div class="modal-box">
          <div class="modal-hd">
            <h3>{{ isEditing ? 'Edit Satuan' : 'Tambah Satuan Baru' }}</h3>
            <button @click="closeModal" class="modal-close"><X :size="18" /></button>
          </div>
          <form @submit.prevent="submit" class="modal-bd">
            <div class="field">
              <label>Nama Satuan <span class="req">*</span></label>
              <input
                v-model="form.name"
                type="text"
                class="field-input"
                :class="{ 'field-input--error': form.errors.name }"
                placeholder="cth: karung, ikat, bungkus"
                autofocus
                required
              />
              <span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span>
            </div>
            <div class="modal-ft">
              <button type="button" @click="closeModal" class="btn-cancel">Batal</button>
              <button type="submit" class="btn-save" :disabled="form.processing">
                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirm Modal -->
    <Teleport to="body">
      <div v-if="isDeleteModalOpen" class="modal-overlay" @click.self="isDeleteModalOpen = false">
        <div class="confirm-box">
          <div class="confirm-icon">
            <Trash2 :size="22" />
          </div>
          <h3 class="confirm-title">Hapus Satuan?</h3>
          <p class="confirm-desc">
            Satuan <strong>"{{ unitToDelete?.name }}"</strong> akan dihapus permanen dan tidak bisa dikembalikan.
          </p>
          <div class="confirm-actions">
            <button @click="isDeleteModalOpen = false" class="btn-cancel">Batal</button>
            <button @click="executeDelete" class="btn-delete">Hapus</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
/* ── Layout ── */
.units-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 16px;
}
.units-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px;
}
.units-subtitle {
  font-size: 0.82rem;
  color: #64748b;
  margin: 0;
  max-width: 420px;
}
.stats-bar {
  margin-bottom: 16px;
}
.stats-count {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
}
.stats-num {
  font-size: 1rem;
  font-weight: 700;
  color: #526d8a;
  margin-right: 4px;
}

/* ── Add Button ── */
.btn-add {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  background: linear-gradient(135deg, #526d8a, #526d8a);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}
.btn-add:hover {
  background: linear-gradient(135deg, #526d8a, #435a74);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
}
.btn-add:active { transform: translateY(0); }

/* ── Units Grid ── */
.units-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  max-width: 860px;
}

/* ── Unit Pill ── */
.unit-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px 7px 14px;
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 50px;
  transition: all 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.unit-pill:hover {
  border-color: #cbd5e1;
  box-shadow: 0 3px 10px rgba(0,0,0,0.08);
  transform: translateY(-1px);
}
.unit-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: #334155;
  letter-spacing: 0.01em;
}
.unit-actions {
  display: flex;
  align-items: center;
  gap: 2px;
}
.unit-btn {
  width: 26px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.15s;
  background: transparent;
}
.unit-btn-edit { color: #64748b; }
.unit-btn-edit:hover { background: #eff6ff; color: #526d8a; }
.unit-btn-delete { color: #94a3b8; }
.unit-btn-delete:hover { background: #fef2f2; color: #ef4444; }

/* ── Empty ── */
.empty-state {
  width: 100%;
  padding: 32px;
  text-align: center;
  color: #94a3b8;
  font-size: 0.875rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1.5px dashed #e2e8f0;
}

/* ── Modal Overlay ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

/* ── Add/Edit Modal ── */
.modal-box {
  background: white;
  border-radius: 20px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.15);
  overflow: hidden;
  animation: slideUp 0.2s ease;
}
.modal-hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f1f5f9;
}
.modal-hd h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}
.modal-close {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 8px;
  background: #f1f5f9;
  color: #64748b;
  cursor: pointer;
  transition: 0.15s;
}
.modal-close:hover { background: #e2e8f0; color: #0f172a; }
.modal-bd { padding: 20px 24px 24px; }
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 4px; }
.field label { font-size: 0.82rem; font-weight: 600; color: #475569; }
.req { color: #ef4444; }
.field-input {
  padding: 10px 13px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.875rem;
  font-family: inherit;
  color: #0f172a;
  transition: all 0.15s;
  outline: none;
}
.field-input:focus { border-color: #526d8a; box-shadow: 0 0 0 3px rgba(82, 109, 138, 0.12); }
.field-input--error { border-color: #ef4444; }
.field-error { font-size: 0.75rem; color: #ef4444; }
.modal-ft {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

/* ── Confirm Modal ── */
.confirm-box {
  background: white;
  border-radius: 24px;
  width: 100%;
  max-width: 340px;
  padding: 28px 24px 24px;
  text-align: center;
  box-shadow: 0 25px 60px rgba(0,0,0,0.15);
  animation: slideUp 0.2s ease;
}
.confirm-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: #fef2f2;
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}
.confirm-title { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.confirm-desc { font-size: 0.82rem; color: #64748b; margin: 0 0 20px; line-height: 1.5; }
.confirm-desc strong { color: #0f172a; }
.confirm-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

/* ── Shared Buttons ── */
.btn-cancel {
  padding: 9px 16px;
  background: #f1f5f9;
  color: #475569;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: 0.15s;
}
.btn-cancel:hover { background: #e2e8f0; }
.btn-save {
  padding: 9px 16px;
  background: linear-gradient(135deg, #526d8a, #526d8a);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: 0.15s;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}
.btn-save:hover { background: linear-gradient(135deg, #526d8a, #435a74); }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-delete {
  padding: 9px 16px;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: 0.15s;
}
.btn-delete:hover { background: #dc2626; }

/* ── Anim ── */
@keyframes slideUp {
  from { opacity: 0; transform: translateY(16px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
