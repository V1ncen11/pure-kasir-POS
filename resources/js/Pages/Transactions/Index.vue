<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
const debounce = (fn, delay) => {
  let timeoutId
  return (...args) => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn(...args), delay)
  }
}
import { 
  Search, Calendar, Filter, FileText, X, Eye, 
  AlertOctagon, RotateCcw, CreditCard, Banknote, QrCode
} from '@lucide/vue'

const props = defineProps({
  transactions: Object,
  filters: Object,
  isAdmin: Boolean,
})

const toast = useToast()

const search = ref(props.filters.search || '')
const date_from = ref(props.filters.date_from || '')
const date_to = ref(props.filters.date_to || '')
const payment = ref(props.filters.payment || '')
const status = ref(props.filters.status || '')
const showFilters = ref(false)

const formatRupiah = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value)
}

const updateFilters = debounce(() => {
  router.get(route('transactions.index'), {
    search: search.value,
    date_from: date_from.value,
    date_to: date_to.value,
    payment: payment.value,
    status: status.value,
  }, {
    preserveState: true,
    replace: true
  })
}, 300)

watch([search, date_from, date_to, payment, status], () => {
  updateFilters()
})

const resetFilters = () => {
  search.value = ''
  date_from.value = ''
  date_to.value = ''
  payment.value = ''
  status.value = ''
  toast.info('Filter dibersihkan')
}

const voidTarget = ref(null)
const voidPassword = ref('')
const confirmVoid = (t) => {
  voidTarget.value = t
  voidPassword.value = ''
}
const performVoid = () => {
  if (!voidTarget.value) return
  if (!voidPassword.value) {
    toast.error('Kata sandi konfirmasi wajib diisi!')
    return
  }
  router.post(route('transactions.void', voidTarget.value.id), {
    password: voidPassword.value
  }, {
    onSuccess: () => {
      voidTarget.value = null
      voidPassword.value = ''
    },
    onError: (errors) => {
      toast.error(errors.password || errors.error || 'Gagal membatalkan transaksi')
    }
  })
}
</script>

<template>
  <AppLayout title="Riwayat Transaksi">
    <template #title>Riwayat Transaksi</template>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-primary-800 dark:text-white">Riwayat Transaksi</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {{ isAdmin ? 'Kelola dan pantau seluruh transaksi penjualan toko.' : 'Daftar transaksi penjualan Anda hari ini.' }}
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="showFilters = !showFilters"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-xl transition duration-200"
            :class="showFilters || date_from || date_to || payment || status 
              ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300'
              : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700/50'"
          >
            <Filter class="w-4 h-4" />
            <span>Filter</span>
            <span v-if="date_from || date_to || payment || status" class="w-2 h-2 rounded-full bg-primary-600 dark:bg-primary-400"></span>
          </button>
          
          <button 
            v-if="date_from || date_to || payment || status || search"
            @click="resetFilters"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium border border-red-200 bg-red-50 text-red-700 rounded-xl hover:bg-red-100 transition duration-200 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400 dark:hover:bg-red-950/30"
          >
            <X class="w-4 h-4" />
            <span>Reset</span>
          </button>
        </div>
      </div>

      <!-- Search and Filter Bar -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4 space-y-4">
        <div class="relative">
          <Search class="absolute left-4 top-3 h-5 w-5 text-primary-600" />
          <input 
            v-model="search"
            type="text" 
            placeholder="Cari nomor invoice..."
            class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200"
          />
        </div>

        <!-- Expanded Filters -->
        <div v-show="showFilters || date_from || date_to || payment || status" class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-2 border-t border-slate-100 dark:border-slate-700">
          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tanggal Mulai</label>
            <div class="relative">
              <Calendar class="absolute left-3 top-2.5 h-4 w-4 text-primary-600" />
              <input 
                v-model="date_from"
                type="date"
                class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tanggal Selesai</label>
            <div class="relative">
              <Calendar class="absolute left-3 top-2.5 h-4 w-4 text-primary-600" />
              <input 
                v-model="date_to"
                type="date"
                class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Metode Pembayaran</label>
            <select 
              v-model="payment"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
            >
              <option value="">Semua Metode</option>
              <option value="cash">Tunai (Cash)</option>
              <option value="transfer">Transfer Bank</option>
              <option value="qris">QRIS</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Status Transaksi</label>
            <select 
              v-model="status"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
            >
              <option value="">Semua Status</option>
              <option value="completed">Selesai</option>
              <option value="voided">Batal (Void)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-left">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700">
                <th class="py-4 px-4 text-sm font-semibold">Nomor Invoice</th>
                <th class="py-4 px-4 text-sm font-semibold">Tanggal & Waktu</th>
                <th v-if="isAdmin" class="py-4 px-4 text-sm font-semibold">Kasir</th>
                <th class="py-4 px-4 text-sm font-semibold text-center">Item</th>
                <th class="py-4 px-4 text-sm font-semibold">Total Belanja</th>
                <th class="py-4 px-4 text-sm font-semibold">Pembayaran</th>
                <th class="py-4 px-4 text-sm font-semibold">Status</th>
                <th class="py-4 px-4 text-sm font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr 
                v-for="t in transactions.data" 
                :key="t.id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition duration-150"
              >
                <!-- Invoice -->
                <td class="py-4 px-4">
                  <div class="font-semibold text-primary-700 dark:text-primary-300 flex items-center gap-1.5">
                    <FileText class="w-4 h-4 text-slate-400" />
                    <span>{{ t.invoice_number }}</span>
                  </div>
                </td>

                <!-- Date -->
                <td class="py-4 px-4 text-sm">
                  <span class="text-slate-700 dark:text-slate-300">{{ t.created_at.split(' ')[0] }}</span>
                  <span class="ml-1.5 text-slate-400 dark:text-slate-500">{{ t.created_at.split(' ')[1] }}</span>
                </td>

                <!-- Cashier -->
                <td v-if="isAdmin" class="py-4 px-4">
                  <span class="text-sm text-slate-600 dark:text-slate-400 font-medium">{{ t.cashier_name }}</span>
                </td>

                <!-- Items Count -->
                <td class="py-4 px-4 text-center text-sm text-slate-600 dark:text-slate-300">
                  {{ t.items_count }} item
                </td>

                <!-- Total -->
                <td class="py-4 px-4">
                  <span class="font-bold text-slate-900 dark:text-white">{{ formatRupiah(t.total) }}</span>
                  <div v-if="t.discount > 0" class="text-xs text-red-500 dark:text-red-400 flex items-center gap-1 mt-0.5">
                    <span>Diskon: {{ formatRupiah(t.discount) }}</span>
                  </div>
                </td>

                <!-- Payment Method -->
                <td class="py-4 px-4">
                  <span class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <Banknote v-if="t.payment_method === 'cash'" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <CreditCard v-else-if="t.payment_method === 'transfer'" class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                    <QrCode v-else class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    <span>{{ t.payment_method === 'cash' ? 'Tunai' : t.payment_method === 'qris' ? 'QRIS' : 'Transfer' }}</span>
                  </span>
                </td>

                <!-- Status -->
                <td class="py-4 px-4">
                  <span class="inline-flex items-center gap-2 whitespace-nowrap text-sm font-semibold"
                    :title="t.status === 'completed' ? 'Transaksi selesai' : 'Transaksi dibatalkan (void)'"
                    :class="t.status === 'completed'
                      ? 'text-emerald-700 dark:text-emerald-400'
                      : 'text-rose-700 dark:text-rose-400'"
                  >
                    <span class="w-2 h-2 rounded-full" :class="t.status === 'completed' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    <span>{{ t.status === 'completed' ? 'Selesai' : 'Void' }}</span>
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-4 px-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Link 
                      :href="route('transactions.show', t.id)"
                      class="flex items-center justify-center p-2 text-slate-500 hover:text-primary-700 bg-slate-50 hover:bg-primary-50 border border-slate-200 dark:border-slate-700 hover:border-primary-200 rounded-xl transition dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-primary-900/30 dark:hover:text-primary-300"
                      title="Lihat Detail struk"
                    >
                      <Eye class="w-4 h-4" />
                    </Link>

                    <button 
                      v-if="isAdmin && t.status === 'completed'"
                      @click="confirmVoid(t)"
                      class="flex items-center justify-center p-2 text-slate-500 hover:text-red-600 bg-slate-50 hover:bg-red-50 border border-slate-200 dark:border-slate-700 hover:border-red-100 rounded-xl transition dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                      title="Batalkan Transaksi (Void)"
                    >
                      <RotateCcw class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="transactions.data.length === 0">
                <td :colspan="isAdmin ? 8 : 7" class="py-16 text-center">
                  <div class="max-w-xs mx-auto">
                    <div class="inline-flex p-4 rounded-full bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 text-slate-400 dark:text-slate-500 mb-4 animate-pulse">
                      <AlertOctagon class="w-10 h-10" />
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">Transaksi Tidak Ditemukan</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                      Silakan sesuaikan filter pencarian atau pastikan kata kunci Anda sudah benar.
                    </p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="transactions.links.length > 3" class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
          <div class="text-sm text-slate-500 dark:text-slate-400">
            Menampilkan <span class="font-medium text-slate-800 dark:text-white">{{ transactions.from || 0 }}</span> - <span class="font-medium text-slate-800 dark:text-white">{{ transactions.to || 0 }}</span> dari <span class="font-medium text-slate-800 dark:text-white">{{ transactions.total }}</span> transaksi
          </div>

          <div class="flex items-center gap-1.5">
            <template v-for="(link, i) in transactions.links" :key="i">
              <div 
                v-if="link.url === null" 
                class="px-3.5 py-2 text-xs font-semibold text-slate-400 dark:text-slate-600 bg-transparent cursor-default rounded-xl"
                v-html="link.label"
              />
              <Link 
                v-else 
                :href="link.url"
                class="px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-200"
                :class="link.active 
                  ? 'bg-primary-600 border-primary-600 text-white font-bold dark:bg-primary-600 dark:border-primary-600'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700'"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom Void Confirmation Modal -->
    <Teleport to="body">
      <div v-if="voidTarget" class="modal-overlay" @click.self="voidTarget = null">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl w-80 shadow-2xl p-6 text-center space-y-4 modal-bounce">
        <!-- Clean, simple Icon -->
        <div class="mx-auto w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-450 flex items-center justify-center">
          <RotateCcw :size="20" class="animate-spin-slow" />
        </div>
        
        <!-- Title & brief text -->
        <div class="space-y-1.5">
          <h3 class="text-lg font-bold text-slate-900 dark:text-white">Void Transaksi?</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-1">
            Apakah Anda yakin ingin membatalkan transaksi <span class="font-semibold text-slate-900 dark:text-white">"{{ voidTarget.invoice_number }}"</span>?
          </p>
        </div>

        <!-- Password input -->
        <div class="text-left space-y-1.5">
          <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider">Kata Sandi Konfirmasi</label>
          <input 
            v-model="voidPassword" 
            type="password" 
            placeholder="Masukkan kata sandi akun Anda" 
            class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-905 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 text-sm transition-all duration-200"
            @keyup.enter="performVoid"
          />
        </div>

        <!-- Actions -->
        <div class="grid grid-cols-2 gap-3 pt-2">
          <button 
            type="button" 
            @click="voidTarget = null" 
            class="w-full py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl transition duration-150 cursor-pointer active:scale-95"
          >
            Batal
          </button>
          <button 
            type="button" 
            @click="performVoid"
            class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl transition duration-150 shadow-md shadow-rose-600/10 cursor-pointer active:scale-95"
          >
            Void
          </button>
        </div>
      </div>
    </div>
  </Teleport>
  </AppLayout>
</template>
