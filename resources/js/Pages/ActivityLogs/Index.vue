<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import {
  History, Search, Filter, X, User,
  ShoppingCart, Package, Tag, Truck, ArrowDownToLine,
  Settings, LogIn, LogOut, AlertTriangle, ChevronLeft, ChevronRight
} from '@lucide/vue'

const props = defineProps({
  logs:    Object,
  users:   Array,
  filters: Object,
})

// Filters
const search    = ref(props.filters?.search ?? '')
const userId    = ref(props.filters?.user_id ?? '')
const dateFrom  = ref(props.filters?.date_from ?? '')
const dateTo    = ref(props.filters?.date_to ?? '')

let timer = null
function applyFilters() {
  clearTimeout(timer)
  timer = setTimeout(() => {
    router.get(route('activity-logs.index'), {
      search:    search.value,
      user_id:   userId.value,
      date_from: dateFrom.value,
      date_to:   dateTo.value,
    }, { preserveState: true, replace: true })
  }, 400)
}

function clearFilters() {
  search.value   = ''
  userId.value   = ''
  dateFrom.value = ''
  dateTo.value   = ''
  router.get(route('activity-logs.index'))
}

watch([search, userId, dateFrom, dateTo], applyFilters)

// Badge colors per activity type
function getActivityMeta(activity) {
  const lower = activity.toLowerCase()
  if (lower.includes('login') || lower.includes('logout')) {
    return { bg: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400', icon: lower.includes('login') ? LogIn : LogOut }
  }
  if (lower.includes('transaksi')) {
    return { bg: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400', icon: ShoppingCart }
  }
  if (lower.includes('produk')) {
    return { bg: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400', icon: Package }
  }
  if (lower.includes('kategori')) {
    return { bg: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400', icon: Tag }
  }
  if (lower.includes('supplier')) {
    return { bg: 'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400', icon: Truck }
  }
  if (lower.includes('stok')) {
    return { bg: 'bg-teal-100 text-teal-700 dark:bg-teal-950/40 dark:text-teal-400', icon: ArrowDownToLine }
  }
  if (lower.includes('void')) {
    return { bg: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400', icon: AlertTriangle }
  }
  if (lower.includes('pengguna')) {
    return { bg: 'bg-violet-100 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400', icon: User }
  }
  if (lower.includes('pengaturan') || lower.includes('logo')) {
    return { bg: 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-400', icon: Settings }
  }
  return { bg: 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-400', icon: History }
}

const hasFilters = () => search.value || userId.value || dateFrom.value || dateTo.value
</script>

<template>
  <AppLayout title="Log Aktivitas">
    <template #title>Log Aktivitas</template>

    <div class="space-y-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Log Aktivitas</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Riwayat aktivitas pengguna sistem. Data tersimpan selama 30 hari terakhir.
          </p>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/30 rounded-xl text-xs font-semibold text-amber-700 dark:text-amber-400">
          <History class="w-4 h-4" />
          <span>Auto-purge: Log ≥ 30 hari otomatis dihapus</span>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm p-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              v-model="search"
              type="text"
              placeholder="Cari aktivitas..."
              class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <!-- User filter -->
          <select
            v-model="userId"
            class="w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option value="">Semua Pengguna</option>
            <option v-for="u in users" :key="u.id" :value="u.id">
              {{ u.name }} ({{ u.role }})
            </option>
          </select>

          <!-- Date from -->
          <input
            v-model="dateFrom"
            type="date"
            class="w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />

          <!-- Date to -->
          <div class="flex gap-2">
            <input
              v-model="dateTo"
              type="date"
              class="flex-1 px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
            <button
              v-if="hasFilters()"
              @click="clearFilters"
              class="px-3 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30 dark:hover:text-rose-400 text-slate-500 rounded-xl transition"
              title="Hapus filter"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Log Table -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">

        <!-- Empty state -->
        <div v-if="logs.data.length === 0" class="py-20 flex flex-col items-center gap-3 text-slate-400">
          <History class="w-12 h-12 opacity-30" />
          <p class="text-sm font-semibold">Belum ada log aktivitas tercatat.</p>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/50">
              <tr>
                <th class="py-3.5 px-5 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Aktivitas</th>
                <th class="py-3.5 px-5 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Deskripsi</th>
                <th class="py-3.5 px-5 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Pengguna</th>
                <th class="py-3.5 px-5 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">IP</th>
                <th class="py-3.5 px-5 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Waktu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <tr
                v-for="log in logs.data"
                :key="log.id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition duration-150"
              >
                <!-- Activity badge -->
                <td class="py-4 px-5">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap"
                    :class="getActivityMeta(log.activity).bg"
                  >
                    <component :is="getActivityMeta(log.activity).icon" class="w-3.5 h-3.5 shrink-0" />
                    {{ log.activity }}
                  </span>
                </td>

                <!-- Description -->
                <td class="py-4 px-5 max-w-xs">
                  <p class="text-sm text-slate-700 dark:text-slate-300 line-clamp-2">
                    {{ log.description || '-' }}
                  </p>
                </td>

                <!-- User -->
                <td class="py-4 px-5">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-950/40 flex items-center justify-center shrink-0">
                      <User class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ log.user.name }}</p>
                      <p class="text-xs text-slate-400 dark:text-slate-500 capitalize">{{ log.user.role }}</p>
                    </div>
                  </div>
                </td>

                <!-- IP -->
                <td class="py-4 px-5">
                  <span class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ log.ip_address || '-' }}</span>
                </td>

                <!-- Timestamp -->
                <td class="py-4 px-5">
                  <span class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ log.created_at }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="flex items-center justify-between px-5 py-4 border-t border-slate-100 dark:border-slate-700">
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Menampilkan <span class="font-bold text-slate-700 dark:text-slate-300">{{ logs.from }}–{{ logs.to }}</span>
            dari <span class="font-bold text-slate-700 dark:text-slate-300">{{ logs.total }}</span> log
          </p>
          <div class="flex items-center gap-1">
            <component
              :is="logs.prev_page_url ? 'a' : 'span'"
              :href="logs.prev_page_url"
              class="p-2 rounded-lg transition"
              :class="logs.prev_page_url
                ? 'hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 cursor-pointer'
                : 'text-slate-300 dark:text-slate-600 cursor-not-allowed'"
            >
              <ChevronLeft class="w-4 h-4" />
            </component>

            <span class="px-3 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
              {{ logs.current_page }} / {{ logs.last_page }}
            </span>

            <component
              :is="logs.next_page_url ? 'a' : 'span'"
              :href="logs.next_page_url"
              class="p-2 rounded-lg transition"
              :class="logs.next_page_url
                ? 'hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 cursor-pointer'
                : 'text-slate-300 dark:text-slate-600 cursor-not-allowed'"
            >
              <ChevronRight class="w-4 h-4" />
            </component>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
