<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
const debounce = (fn, delay) => {
  let timeoutId
  return (...args) => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn(...args), delay)
  }
}
import {
  Search, Plus, Pencil, Trash2, ShieldAlert,
  Shield, Check, X, User, Mail, ShieldCheck,
  ToggleLeft, ToggleRight, Eye, EyeOff
} from '@lucide/vue'

const props = defineProps({
  users: Object,
  filters: Object,
})

const toast = useToast()

const search = ref(props.filters.search || '')
const role = ref(props.filters.role || '')

const updateFilters = debounce(() => {
  router.get(route('users.index'), {
    search: search.value,
    role: role.value,
  }, {
    preserveState: true,
    replace: true
  })
}, 300)

watch([search, role], () => {
  updateFilters()
})

// Modal states
const showModal = ref(false)
const isEdit = ref(false)
const selectedUser = ref(null)
const showPassword = ref(false)

const form = useForm({
  name: '',
  email: '',
  role: 'kasir',
  password: '',
  is_active: true,
})

const openCreateModal = () => {
  isEdit.value = false
  form.reset()
  form.clearErrors()
  showPassword.value = false
  showModal.value = true
}

const openEditModal = (user) => {
  isEdit.value = true
  selectedUser.value = user
  form.clearErrors()
  form.name = user.name
  form.email = user.email
  form.role = user.role
  form.is_active = user.is_active === 1 || user.is_active === true
  form.password = '' // password nullable
  showPassword.value = false
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  form.reset()
}

const submit = () => {
  if (isEdit.value) {
    form.put(route('users.update', selectedUser.value.id), {
      onSuccess: () => {
        closeModal()
      },
      onError: () => {
        toast.error('Gagal memperbarui pengguna. Silakan periksa kembali inputan Anda.')
      }
    })
  } else {
    form.post(route('users.store'), {
      onSuccess: () => {
        closeModal()
      },
      onError: () => {
        toast.error('Gagal menambahkan pengguna. Silakan periksa kembali inputan Anda.')
      }
    })
  }
}

const deleteForm = useForm({
  password: ''
})

const deleteTarget = ref(null)
const confirmDelete = (user) => {
  deleteTarget.value = user
  deleteForm.reset()
  deleteForm.clearErrors()
}
const performDelete = () => {
  if (!deleteTarget.value) return
  deleteForm.delete(route('users.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      deleteTarget.value = null
      deleteForm.reset()
    },
    onError: (errors) => {
      if (errors.error) toast.error(errors.error)
    }
  })
}
</script>

<template>
  <AppLayout title="Kelola Pengguna">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Kelola Pengguna</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Daftar administrator dan kasir yang memiliki akses masuk ke sistem POS.
          </p>
        </div>

        <button
          @click="openCreateModal"
          class="flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 active:bg-primary-800 rounded-xl shadow-lg hover:shadow-primary-500/20 active:shadow-none transition-all duration-200"
        >
          <Plus class="w-4.5 h-4.5" />
          <span>Tambah Pengguna</span>
        </button>
      </div>

      <!-- Filters & Actions -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm p-4 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="relative w-full md:max-w-md">
          <Search class="absolute left-4 top-3 h-4.5 w-4.5 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama atau email..."
            class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
          />
        </div>

        <div class="w-full md:w-auto">
          <select
            v-model="role"
            class="w-full md:w-48 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
          >
            <option value="">Semua Peran (Role)</option>
            <option value="admin">Administrator</option>
            <option value="kasir">Kasir</option>
          </select>
        </div>
      </div>

      <!-- Users Grid/Table -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-left">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700">
                <th class="py-4 px-6 text-sm font-semibold">Nama Pengguna</th>
                <th class="py-4 px-6 text-sm font-semibold">Email</th>
                <th class="py-4 px-6 text-sm font-semibold">Hak Akses</th>
                <th class="py-4 px-6 text-sm font-semibold text-center">Jumlah Transaksi</th>
                <th class="py-4 px-6 text-sm font-semibold">Status Akun</th>
                <th class="py-4 px-6 text-sm font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr
                v-for="u in users.data"
                :key="u.id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition duration-150"
              >
                <!-- Avatar & Name -->
                <td class="py-4 px-6">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center font-bold text-white uppercase text-sm shrink-0"
                      :class="u.role === 'admin'
                        ? 'bg-primary-600 dark:bg-primary-500 shadow-md shadow-primary-600/10'
                        : 'bg-emerald-600 dark:bg-emerald-500 shadow-md shadow-emerald-600/10'"
                    >
                      <img v-if="u.avatar_url" :src="u.avatar_url" :alt="`Foto profil ${u.name}`" class="w-full h-full object-cover" />
                      <span v-else>{{ u.name.charAt(0) }}</span>
                    </div>
                    <div>
                      <h4 class="font-bold text-slate-900 dark:text-white">{{ u.name }}</h4>
                      <p v-if="u.id === $page.props.auth.user.id" class="text-xxs font-bold text-primary-600 dark:text-primary-400">Anda saat ini</p>
                    </div>
                  </div>
                </td>

                <!-- Email -->
                <td class="py-4 px-6 text-slate-600 dark:text-slate-400 text-sm">
                  {{ u.email }}
                </td>

                <!-- Role Badge -->
                <td class="py-4 px-6">
                  <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold"
                    :class="u.role === 'admin'
                      ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400'
                      : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'"
                  >
                    <ShieldCheck v-if="u.role === 'admin'" class="w-3.5 h-3.5" />
                    <User v-else class="w-3.5 h-3.5" />
                    <span class="capitalize">{{ u.role }}</span>
                  </span>
                </td>

                <!-- Transactions Count -->
                <td class="py-4 px-6 text-center">
                  <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                    {{ u.transactions_count || 0 }} kali
                  </span>
                </td>

                <!-- Active Status -->
                <td class="py-4 px-6">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold"
                    :class="u.is_active
                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400'
                      : 'bg-rose-100 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="u.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    <span>{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-4 px-6 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="openEditModal(u)"
                      class="flex items-center justify-center p-2 text-slate-500 hover:text-primary-600 bg-slate-50 hover:bg-primary-50 border border-slate-200 dark:border-slate-700 hover:border-primary-100 rounded-xl transition dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-primary-900/30 dark:hover:text-primary-400"
                      title="Edit Pengguna"
                    >
                      <Pencil class="w-4 h-4" />
                    </button>

                    <button
                      v-if="u.id !== $page.props.auth.user.id"
                      @click="confirmDelete(u)"
                      class="flex items-center justify-center p-2 text-slate-500 hover:text-red-600 bg-slate-50 hover:bg-red-50 border border-slate-200 dark:border-slate-700 hover:border-red-100 rounded-xl transition dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                      title="Hapus Pengguna"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.links.length > 3" class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
          <div class="text-sm text-slate-500">
            Menampilkan <span class="font-medium text-slate-800 dark:text-white">{{ users.from || 0 }}</span> - <span class="font-medium text-slate-800 dark:text-white">{{ users.to || 0 }}</span> dari <span class="font-medium text-slate-800 dark:text-white">{{ users.total }}</span> pengguna
          </div>

          <div class="flex items-center gap-1.5">
            <template v-for="(link, i) in users.links" :key="i">
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
              {{ isEdit ? 'Perbarui Profil Pengguna' : 'Tambah Akses Pengguna Baru' }}
            </h3>
            <button @click="closeModal" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-900 rounded-xl transition">
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Modal Body Form -->
          <form @submit.prevent="submit" class="p-6 space-y-4">

            <div v-if="isEdit && selectedUser?.id === $page.props.auth.user.id" class="bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 p-3 rounded-xl text-xs font-semibold flex items-center gap-2">
              <ShieldAlert class="w-4 h-4 shrink-0" />
              Anda tidak dapat mengubah hak akses peran atau menonaktifkan akun Anda sendiri.
            </div>

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

            <!-- Email -->
            <div>
              <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Email Aktif</label>
              <div class="relative">
                <Mail class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                <input
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="andiwijaya@toko.com"
                  class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                />
              </div>
              <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
            </div>

            <!-- Role (Radio Cards) -->
            <div v-if="!isEdit || selectedUser?.id !== $page.props.auth.user.id">
              <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2">Hak Akses Peran</label>
              <div class="grid grid-cols-2 gap-3">
                <label
                  class="flex flex-col items-center justify-center p-4 border rounded-2xl cursor-pointer transition select-none"
                  :class="form.role === 'kasir'
                    ? 'border-primary-600 bg-primary-50/20 dark:border-primary-400 dark:bg-primary-900/20'
                    : 'border-slate-200 dark:border-slate-700 bg-transparent hover:bg-slate-50 dark:hover:bg-slate-900'"
                >
                  <input type="radio" value="kasir" v-model="form.role" class="hidden" />
                  <User class="w-6 h-6 mb-1 text-slate-400" :class="{ 'text-primary-600 dark:text-primary-400': form.role === 'kasir' }" />
                  <span class="text-sm font-bold" :class="form.role === 'kasir' ? 'text-primary-600 dark:text-primary-400' : 'text-slate-700 dark:text-slate-300'">KASIR</span>
                  <span class="text-xxs text-slate-400 mt-0.5">Operasi kasir POS</span>
                </label>

                <label
                  class="flex flex-col items-center justify-center p-4 border rounded-2xl cursor-pointer transition select-none"
                  :class="form.role === 'admin'
                    ? 'border-primary-600 bg-primary-50/20 dark:border-primary-400 dark:bg-primary-900/20'
                    : 'border-slate-200 dark:border-slate-700 bg-transparent hover:bg-slate-50 dark:hover:bg-slate-900'"
                >
                  <input type="radio" value="admin" v-model="form.role" class="hidden" />
                  <ShieldCheck class="w-6 h-6 mb-1 text-slate-400" :class="{ 'text-primary-600 dark:text-primary-400': form.role === 'admin' }" />
                  <span class="text-sm font-bold" :class="form.role === 'admin' ? 'text-primary-600 dark:text-primary-400' : 'text-slate-700 dark:text-slate-300'">ADMIN</span>
                  <span class="text-xxs text-slate-400 mt-0.5">Manajemen penuh</span>
                </label>
              </div>
              <p v-if="form.errors.role" class="text-xs text-red-500 mt-1">{{ form.errors.role }}</p>
            </div>

            <!-- Password -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Kata Sandi (Password)</label>
                <span v-if="isEdit" class="text-xxs text-slate-400 italic">Kosongkan jika tidak diubah</span>
              </div>
              <div class="relative">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  :required="!isEdit"
                  placeholder="Minimal 8 karakter"
                  class="w-full px-4 pr-10 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                />
                <button
                  v-if="form.password"
                  type="button"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none"
                  @click="showPassword = !showPassword"
                >
                  <component :is="showPassword ? EyeOff : Eye" class="w-4.5 h-4.5" />
                </button>
              </div>
              <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
            </div>

            <!-- Active Status Toggle (Edit mode only) -->
            <div v-if="isEdit && selectedUser?.id !== $page.props.auth.user.id" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-100 dark:border-slate-700">
              <div>
                <h5 class="text-sm font-bold text-slate-800 dark:text-slate-200">Status Akun Aktif</h5>
                <p class="text-xxs text-slate-400">Nonaktifkan pengguna untuk memblokir akses login.</p>
              </div>

              <button
                type="button"
                @click="form.is_active = !form.is_active"
                class="text-primary-600 dark:text-primary-400 focus:outline-none transition duration-150"
              >
                <ToggleRight v-if="form.is_active" class="w-10 h-10 text-primary-600" />
                <ToggleLeft v-else class="w-10 h-10 text-slate-450" />
              </button>
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
          <!-- Clean, simple Icon -->
          <div class="mx-auto w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-450 flex items-center justify-center">
            <Trash2 :size="20" />
          </div>

          <!-- Title & brief text -->
          <div class="space-y-1.5">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Hapus Pengguna?</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-1">
              Apakah Anda yakin ingin menghapus akun pengguna <span class="font-semibold text-slate-900 dark:text-white">"{{ deleteTarget.name }}"</span>?
            </p>
          </div>

          <!-- Password Confirmation Input -->
          <div class="text-left mt-4 mb-2">
            <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Konfirmasi Password Anda</label>
            <input
              v-model="deleteForm.password"
              type="password"
              required
              placeholder="Masukkan password admin"
              class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 text-sm"
              @keydown.enter="performDelete"
            />
            <p v-if="deleteForm.errors.password" class="text-xs text-red-500 mt-1">{{ deleteForm.errors.password }}</p>
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
              :disabled="deleteForm.processing"
              class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition duration-150 shadow-md shadow-rose-600/10 cursor-pointer active:scale-95"
            >
              {{ deleteForm.processing ? 'Menghapus...' : 'Hapus' }}
            </button>
          </div>
        </div>
      </div>
      </Teleport>
    </div>
  </AppLayout>
</template>
