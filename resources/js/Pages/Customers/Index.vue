<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import {
  Search, Plus, Pencil, Trash2, X, User, Phone, Mail, MapPin, Award
} from '@lucide/vue'

const debounce = (fn, delay) => {
  let timeoutId
  return (...args) => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn(...args), delay)
  }
}

const props = defineProps({
  customers: Object,
  filters: Object,
})

const toast = useToast()

const search = ref(props.filters.search || '')

const updateFilters = debounce(() => {
  router.get(route('customers.index'), {
    search: search.value,
  }, {
    preserveState: true,
    replace: true
  })
}, 300)

watch(search, () => {
  updateFilters()
})

// Modal states
const showModal = ref(false)
const isEdit = ref(false)
const selectedCustomer = ref(null)

const form = useForm({
  name: '',
  phone: '',
  email: '',
  address: '',
  points: 0,
})

const openCreateModal = () => {
  isEdit.value = false
  form.reset()
  form.clearErrors()
  showModal.value = true
}

const openEditModal = (customer) => {
  isEdit.value = true
  selectedCustomer.value = customer
  form.clearErrors()
  form.name = customer.name
  form.phone = customer.phone ?? ''
  form.email = customer.email ?? ''
  form.address = customer.address ?? ''
  form.points = customer.points
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  form.reset()
}

const submit = () => {
  if (isEdit.value) {
    form.put(route('customers.update', selectedCustomer.value.id), {
      onSuccess: () => {
        closeModal()
        toast.success('Pelanggan berhasil diperbarui!')
      },
      onError: () => {
        toast.error('Gagal memperbarui pelanggan. Silakan periksa kembali inputan Anda.')
      }
    })
  } else {
    form.post(route('customers.store'), {
      onSuccess: () => {
        closeModal()
        toast.success('Pelanggan berhasil ditambahkan!')
      },
      onError: () => {
        toast.error('Gagal menambahkan pelanggan. Silakan periksa kembali inputan Anda.')
      }
    })
  }
}

const deleteTarget = ref(null)
const confirmDelete = (customer) => {
  deleteTarget.value = customer
}
const performDelete = () => {
  if (!deleteTarget.value) return
  router.delete(route('customers.destroy', deleteTarget.value.id), {
    onSuccess: () => {
      deleteTarget.value = null
      toast.success('Pelanggan berhasil dihapus!')
    },
    onError: (errors) => {
      toast.error(errors.error || 'Gagal menghapus pelanggan.')
    }
  })
}
</script>

<template>
  <AppLayout title="Kelola Pelanggan">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Kelola Pelanggan</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Daftar member/pelanggan yang terdaftar di program loyalitas POS Toko.
          </p>
        </div>

        <button
          @click="openCreateModal"
          class="flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 active:bg-primary-800 rounded-xl shadow-lg hover:shadow-primary-500/20 active:shadow-none transition-all duration-200"
        >
          <Plus class="w-4.5 h-4.5" />
          <span>Tambah Pelanggan</span>
        </button>
      </div>

      <!-- Filters & Actions -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm p-4 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full md:max-w-md">
          <Search class="absolute left-4 top-3 h-4.5 w-4.5 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama, telepon, atau email..."
            class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
          />
        </div>
      </div>

      <!-- Customers Table -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-left">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700">
                <th class="py-4 px-6 text-sm font-semibold">Nama Pelanggan</th>
                <th class="py-4 px-6 text-sm font-semibold">Telepon</th>
                <th class="py-4 px-6 text-sm font-semibold">Email</th>
                <th class="py-4 px-6 text-sm font-semibold">Alamat</th>
                <th class="py-4 px-6 text-sm font-semibold text-center">Poin Belanja</th>
                <th class="py-4 px-6 text-sm font-semibold text-center">Jumlah Transaksi</th>
                <th class="py-4 px-6 text-sm font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr
                v-for="c in customers.data"
                :key="c.id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition duration-150"
              >
                <!-- Avatar & Name -->
                <td class="py-4 px-6">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white uppercase text-sm shrink-0 bg-primary-600 dark:bg-primary-500 shadow-md shadow-primary-600/10">
                      {{ c.name.charAt(0) }}
                    </div>
                    <div>
                      <h4 class="font-bold text-slate-900 dark:text-white">{{ c.name }}</h4>
                      <p class="text-xxs text-slate-450">ID Member: MB-{{ String(c.id).padStart(5, '0') }}</p>
                    </div>
                  </div>
                </td>

                <!-- Phone -->
                <td class="py-4 px-6 text-slate-600 dark:text-slate-400 text-sm">
                  {{ c.phone || '-' }}
                </td>

                <!-- Email -->
                <td class="py-4 px-6 text-slate-600 dark:text-slate-400 text-sm">
                  {{ c.email || '-' }}
                </td>

                <!-- Address -->
                <td class="py-4 px-6 text-slate-600 dark:text-slate-400 text-sm max-w-xs truncate">
                  {{ c.address || '-' }}
                </td>

                <!-- Points -->
                <td class="py-4 px-6 text-center">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 rounded-xl text-xs font-bold">
                    <Award class="w-3.5 h-3.5" />
                    <span>{{ c.points }} Poin</span>
                  </span>
                </td>

                <!-- Transactions Count -->
                <td class="py-4 px-6 text-center">
                  <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                    {{ c.transactions_count || 0 }} kali
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-4 px-6 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="openEditModal(c)"
                      class="flex items-center justify-center p-2 text-slate-500 hover:text-primary-600 bg-slate-50 hover:bg-primary-50 border border-slate-200 dark:border-slate-700 hover:border-primary-100 rounded-xl transition dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-primary-900/30 dark:hover:text-primary-400"
                      title="Edit Pelanggan"
                    >
                      <Pencil class="w-4 h-4" />
                    </button>

                    <button
                      @click="confirmDelete(c)"
                      class="flex items-center justify-center p-2 text-slate-500 hover:text-red-600 bg-slate-50 hover:bg-red-50 border border-slate-200 dark:border-slate-700 hover:border-red-100 rounded-xl transition dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                      title="Hapus Pelanggan"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="customers.data.length === 0">
                <td colspan="7" class="py-8 text-center text-slate-400">
                  Tidak ada data pelanggan/member ditemukan.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="customers.links.length > 3" class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
          <div class="text-sm text-slate-500">
            Menampilkan <span class="font-medium text-slate-800 dark:text-white">{{ customers.from || 0 }}</span> - <span class="font-medium text-slate-800 dark:text-white">{{ customers.to || 0 }}</span> dari <span class="font-medium text-slate-800 dark:text-white">{{ customers.total }}</span> pelanggan
          </div>

          <div class="flex items-center gap-1.5">
            <template v-for="(link, i) in customers.links" :key="i">
              <div
                v-if="link.url === null"
                class="px-3.5 py-2 text-xs text-slate-400 cursor-default rounded-xl"
                v-html="link.label"
              />
              <Link
                v-else
                :href="link.url"
                class="px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-200"
                :class="link.active
                  ? 'bg-primary-600 border-primary-600 text-white font-bold'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300'"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>

      <!-- Create & Edit Modal -->
      <Teleport to="body">
        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
          <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden modal">

            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
              <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
                {{ isEdit ? 'Perbarui Data Member' : 'Tambah Member Baru' }}
              </h3>
              <button @click="closeModal" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-900 rounded-xl transition">
                <X class="w-5 h-5" />
              </button>
            </div>

            <!-- Modal Body Form -->
            <form @submit.prevent="submit" class="p-6 space-y-4">

              <!-- Name -->
              <div>
                <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Nama Lengkap</label>
                <div class="relative">
                  <User class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                  <input
                    v-model="form.name"
                    type="text"
                    required
                    placeholder="Contoh: Andi Wijaya"
                    class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                  />
                </div>
                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
              </div>

              <!-- Phone -->
              <div>
                <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">No. Telepon / HP</label>
                <div class="relative">
                  <Phone class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                  <input
                    v-model="form.phone"
                    type="text"
                    placeholder="Contoh: 08123456789"
                    class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                  />
                </div>
                <p v-if="form.errors.phone" class="text-xs text-red-500 mt-1">{{ form.errors.phone }}</p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Email (Opsional)</label>
                <div class="relative">
                  <Mail class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                  <input
                    v-model="form.email"
                    type="email"
                    placeholder="andi@gmail.com"
                    class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                  />
                </div>
                <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
              </div>

              <!-- Address -->
              <div>
                <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Alamat Lengkap</label>
                <div class="relative">
                  <MapPin class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                  <textarea
                    v-model="form.address"
                    placeholder="Contoh: Jl. Merdeka No. 12"
                    rows="2.5"
                    class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm resize-none font-sans"
                  />
                </div>
                <p v-if="form.errors.address" class="text-xs text-red-500 mt-1">{{ form.errors.address }}</p>
              </div>

              <!-- Points (Editable in Edit Mode only) -->
              <div v-if="isEdit">
                <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Poin Loyalitas</label>
                <div class="relative">
                  <Award class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                  <input
                    v-model="form.points"
                    type="number"
                    min="0"
                    required
                    class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                  />
                </div>
                <p v-if="form.errors.points" class="text-xs text-red-500 mt-1">{{ form.errors.points }}</p>
              </div>

              <!-- Modal Footer Buttons -->
              <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-3">
                <button
                  type="button"
                  @click="closeModal"
                  class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl hover:bg-slate-50 dark:hover:bg-slate-900/50 transition duration-150"
                >
                  Batalkan
                </button>

                <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold rounded-xl transition duration-150 shadow-md shadow-primary-600/10"
                >
                  {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
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
            <!-- Icon -->
            <div class="mx-auto w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-450 flex items-center justify-center">
              <Trash2 :size="20" />
            </div>

            <!-- Title & brief text -->
            <div class="space-y-1.5">
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hapus Pelanggan?</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-1">
                Apakah Anda yakin ingin menghapus data member <span class="font-semibold text-slate-900 dark:text-white">"{{ deleteTarget.name }}"</span>?
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
    </div>
  </AppLayout>
</template>
