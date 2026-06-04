<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { 
  TrendingUp, Calendar, FileDown, ShoppingBag, 
  DollarSign, Percent, ArrowUpRight, Award, 
  CreditCard, Wallet, Banknote, Search
} from '@lucide/vue'
import dayjs from 'dayjs'

const props = defineProps({
  summary: Object,
  chartDays: Array,
  paymentBreakdown: Array,
  topProducts: Array,
  transactions: Object,
  filters: Object,
})

const toast = useToast()

const date_from = ref(props.filters.date_from || '')
const date_to = ref(props.filters.date_to || '')

const formatRupiah = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value)
}

const applyFilters = () => {
  router.get(route('reports.index'), {
    date_from: date_from.value,
    date_to: date_to.value,
  }, {
    preserveState: true,
  })
}

const setPresetRange = (rangeType) => {
  const today = dayjs()
  let from = today
  let to = today

  if (rangeType === 'today') {
    from = today.format('YYYY-MM-DD')
    to = today.format('YYYY-MM-DD')
  } else if (rangeType === 'yesterday') {
    from = today.subtract(1, 'day').format('YYYY-MM-DD')
    to = today.subtract(1, 'day').format('YYYY-MM-DD')
  } else if (rangeType === 'this_month') {
    from = today.startOf('month').format('YYYY-MM-DD')
    to = today.endOf('month').format('YYYY-MM-DD')
  } else if (rangeType === 'last_30_days') {
    from = today.subtract(30, 'days').format('YYYY-MM-DD')
    to = today.format('YYYY-MM-DD')
  }

  date_from.value = from
  date_to.value = to
  applyFilters()
  toast.success('Rentang waktu diperbarui')
}

// Chart Options
const chartOptions = computed(() => {
  return {
    chart: {
      id: 'sales-chart',
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'Inter, sans-serif',
      type: 'area'
    },
    colors: ['#4f46e5'],
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [0, 100]
      }
    },
    stroke: {
      curve: 'smooth',
      width: 3
    },
    markers: {
      size: 4,
      colors: ['#4f46e5'],
      strokeColors: '#ffffff',
      strokeWidth: 2,
      hover: { size: 6 }
    },
    xaxis: {
      categories: props.chartDays.map(d => d.date),
      labels: {
        style: { colors: '#94a3b8', fontSize: '12px' }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        formatter: (val) => {
          if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M'
          if (val >= 1000) return (val / 1000).toFixed(0) + 'k'
          return val
        },
        style: { colors: '#94a3b8', fontSize: '12px' }
      }
    },
    grid: {
      borderColor: '#f1f5f9',
      strokeDashArray: 4,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: true } }
    },
    dataLabels: { enabled: false },
    tooltip: {
      y: {
        formatter: (val) => formatRupiah(val)
      }
    }
  }
})

const chartSeries = computed(() => {
  return [
    {
      name: 'Penjualan',
      data: props.chartDays.map(d => d.total)
    }
  ]
})

const exportCsv = () => {
  window.open(route('reports.export', {
    date_from: date_from.value,
    date_to: date_to.value
  }), '_blank')
  toast.success('Pengeksporan laporan CSV dimulai!')
}
</script>

<template>
  <AppLayout title="Laporan Penjualan">
    <div class="space-y-6">
      
      <!-- Header Section -->
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Laporan Penjualan</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Analisis kinerja penjualan, omset, dan metode pembayaran toko Anda.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button 
            @click="exportCsv"
            class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition duration-200 dark:bg-indigo-950/20 dark:border-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-950/30"
          >
            <FileDown class="w-4 h-4" />
            <span>Ekspor CSV</span>
          </button>
        </div>
      </div>

      <!-- Filters Panel -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl shadow-sm p-4 space-y-4">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
          <div class="flex flex-wrap gap-2 items-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mr-2">Preset:</span>
            <button 
              @click="setPresetRange('today')"
              class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30 transition"
            >
              Hari Ini
            </button>
            <button 
              @click="setPresetRange('yesterday')"
              class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30 transition"
            >
              Kemarin
            </button>
            <button 
              @click="setPresetRange('this_month')"
              class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30 transition"
            >
              Bulan Ini
            </button>
            <button 
              @click="setPresetRange('last_30_days')"
              class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30 transition"
            >
              30 Hari Terakhir
            </button>
          </div>

          <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="grid grid-cols-2 gap-2 grow md:grow-0">
              <div>
                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Dari</label>
                <input 
                  v-model="date_from"
                  type="date"
                  class="w-full px-2.5 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Sampai</label>
                <input 
                  v-model="date_to"
                  type="date"
                  class="w-full px-2.5 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </div>
            </div>
            <button 
              @click="applyFilters"
              class="self-end px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-850 text-white text-xs font-bold rounded-lg transition duration-200 shadow-md shadow-indigo-600/10"
            >
              Filter
            </button>
          </div>
        </div>
      </div>

      <!-- Financial & Operational Widget Cards (Unified Responsive Grid) -->
      <div class="grid grid-cols-2 sm:grid-cols-3 2xl:grid-cols-6 gap-4">
        <!-- Revenue Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3.5 shadow-sm flex items-center justify-between">
          <div class="space-y-1 min-w-0 pr-7">
            <span class="block text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">Total Omset</span>
            <h3 class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{{ formatRupiah(summary.total_revenue) }}</h3>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1 font-semibold truncate">
              <TrendingUp class="w-3 h-3 shrink-0 text-indigo-500 dark:text-indigo-400" />
              <span>Omset Kotor</span>
            </p>
          </div>
          <div class="p-1.5 bg-indigo-50 dark:bg-indigo-950/30 rounded-lg text-indigo-600 dark:text-indigo-400 shrink-0 ml-1">
            <Banknote class="w-4 h-4" />
          </div>
        </div>

        <!-- Expense Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3.5 shadow-sm flex items-center justify-between">
          <div class="space-y-1 min-w-0 pr-7">
            <span class="block text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">Pengeluaran</span>
            <h3 class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{{ formatRupiah(summary.total_expense) }}</h3>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1 font-semibold truncate">
              <FileDown class="w-3 h-3 shrink-0 text-slate-500 dark:text-slate-400" />
              <span>Belanja Stok</span>
            </p>
          </div>
          <div class="p-1.5 bg-slate-100 dark:bg-slate-900/50 rounded-lg text-slate-600 dark:text-slate-400 shrink-0 ml-1">
            <ShoppingBag class="w-4 h-4" />
          </div>
        </div>

        <!-- Profit Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3.5 shadow-sm flex items-center justify-between">
          <div class="space-y-1 min-w-0 pr-7">
            <span class="block text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">Keuntungan</span>
            <h3 class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{{ formatRupiah(summary.net_profit) }}</h3>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1 font-semibold truncate">
              <ArrowUpRight class="w-3 h-3 shrink-0 text-emerald-500 dark:text-emerald-400" />
              <span>Net Profit</span>
            </p>
          </div>
          <div class="p-1.5 bg-emerald-50 dark:bg-emerald-950/30 rounded-lg text-emerald-600 dark:text-emerald-400 shrink-0 ml-1">
            <TrendingUp class="w-4 h-4" />
          </div>
        </div>

        <!-- Transactions Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3.5 shadow-sm flex items-center justify-between">
          <div class="space-y-1 min-w-0 pr-7">
            <span class="block text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">Transaksi</span>
            <h3 class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{{ summary.total_transactions }}</h3>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1 font-semibold truncate">
              <span>Sukses</span>
            </p>
          </div>
          <div class="p-1.5 bg-emerald-50 dark:bg-emerald-950/30 rounded-lg text-emerald-600 dark:text-emerald-400 shrink-0 ml-1">
            <ShoppingBag class="w-4 h-4" />
          </div>
        </div>

        <!-- Avg Value Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3.5 shadow-sm flex items-center justify-between">
          <div class="space-y-1 min-w-0 pr-7">
            <span class="block text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">Rata-rata</span>
            <h3 class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{{ formatRupiah(summary.avg_transaction) }}</h3>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1 font-semibold truncate">
              <span>Nilai struk</span>
            </p>
          </div>
          <div class="p-1.5 bg-blue-50 dark:bg-blue-950/30 rounded-lg text-blue-600 dark:text-blue-400 shrink-0 ml-1">
            <ArrowUpRight class="w-4 h-4" />
          </div>
        </div>

        <!-- Discounts Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3.5 shadow-sm flex items-center justify-between">
          <div class="space-y-1 min-w-0 pr-7">
            <span class="block text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase truncate">Diskon</span>
            <h3 class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{{ formatRupiah(summary.total_discount) }}</h3>
            <p class="text-[10px] text-rose-500 flex items-center gap-1 font-semibold truncate">
              <span>Potongan</span>
            </p>
          </div>
          <div class="p-1.5 bg-rose-50 dark:bg-rose-950/30 rounded-lg text-rose-600 dark:text-rose-400 shrink-0 ml-1">
            <Percent class="w-4 h-4" />
          </div>
        </div>
      </div>

      <!-- Charts & Breakdown Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales Performance Chart (Left 2 columns) -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl shadow-sm p-6 lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tren Kinerja Penjualan</h3>
              <p class="text-xs text-slate-400 dark:text-slate-500">Grafik omset harian pada rentang waktu terpilih.</p>
            </div>
          </div>

          <div class="h-80 w-full">
            <apexchart 
              type="area" 
              height="300" 
              :options="chartOptions" 
              :series="chartSeries" 
            />
          </div>
        </div>

        <!-- Payment Method Breakdown (Right 1 column) -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl shadow-sm p-6 space-y-6">
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Metode Pembayaran</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500">Distribusi omset berdasarkan cara bayar.</p>
          </div>

          <div class="space-y-4">
            <div 
              v-for="p in paymentBreakdown" 
              :key="p.payment_method"
              class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border border-slate-100 dark:border-slate-700"
            >
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl shrink-0"
                  :class="{
                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400': p.payment_method === 'cash',
                    'bg-sky-50 text-sky-600 dark:bg-sky-950/20 dark:text-sky-400': p.payment_method === 'transfer',
                    'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400': p.payment_method === 'qris',
                  }"
                >
                  <Banknote v-if="p.payment_method === 'cash'" class="w-4 h-4" />
                  <CreditCard v-else-if="p.payment_method === 'transfer'" class="w-4 h-4" />
                  <Wallet v-else class="w-4 h-4" />
                </div>

                <div>
                  <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 capitalize">
                    {{ p.payment_method === 'cash' ? 'Tunai' : p.payment_method }}
                  </h4>
                  <p class="text-xs text-slate-400 dark:text-slate-500">{{ p.count }} Transaksi</p>
                </div>
              </div>

              <div class="text-right">
                <div class="text-sm font-extrabold text-slate-900 dark:text-white">{{ formatRupiah(p.total) }}</div>
                <div class="text-xxs font-bold text-indigo-600 dark:text-indigo-400">
                  {{ ((p.total / summary.total_revenue) * 100).toFixed(1) }}%
                </div>
              </div>
            </div>

            <!-- Empty Payment Method Breakdown -->
            <div v-if="paymentBreakdown.length === 0" class="py-12 text-center text-slate-400">
              Belum ada data pembayaran
            </div>
          </div>
        </div>
      </div>

      <!-- Top Selling Products & Transactions list -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Top Products List (Left Column) -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl shadow-sm p-6 space-y-4">
          <div class="flex items-center gap-2">
            <Award class="w-5 h-5 text-amber-500" />
            <div>
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">10 Produk Terlaris</h3>
              <p class="text-xs text-slate-400 dark:text-slate-500">Paling banyak terjual pada rentang waktu terpilih.</p>
            </div>
          </div>

          <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
            <div 
              v-for="(p, index) in topProducts" 
              :key="index"
              class="py-3.5 flex items-center justify-between gap-4"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <span class="flex items-center justify-center w-6 h-6 text-xs font-extrabold rounded-full shrink-0"
                  :class="{
                    'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400': index === 0,
                    'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-300': index === 1,
                    'bg-orange-100 text-orange-800 dark:bg-orange-950/40 dark:text-orange-400': index === 2,
                    'bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400': index > 2,
                  }"
                >
                  {{ index + 1 }}
                </span>
                
                <div class="overflow-hidden">
                  <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 truncate">{{ p.product_name }}</h4>
                  <p class="text-xs text-slate-400 dark:text-slate-500">{{ p.total_qty }} Unit Terjual</p>
                </div>
              </div>

              <div class="text-right shrink-0">
                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ formatRupiah(p.total_revenue) }}</span>
              </div>
            </div>

            <!-- Empty Products State -->
            <div v-if="topProducts.length === 0" class="py-12 text-center text-slate-400">
              Belum ada penjualan produk
            </div>
          </div>
        </div>

        <!-- Recent Transactions list inside date range (Right 2 columns) -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl shadow-sm p-6 lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-bold text-slate-900 dark:text-white">Daftar Penjualan</h3>
              <p class="text-xs text-slate-400 dark:text-slate-500">Daftar transaksi dalam rentang waktu laporan.</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-b border-slate-100 dark:border-slate-700 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                  <th class="pb-3 text-left">Invoice</th>
                  <th class="pb-3 text-left">Tanggal</th>
                  <th class="pb-3 text-left">Kasir</th>
                  <th class="pb-3 text-left">Metode</th>
                  <th class="pb-3 text-right">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <tr 
                  v-for="t in transactions.data" 
                  :key="t.id"
                  class="text-sm hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition duration-100"
                >
                  <td class="py-3.5">
                    <Link :href="route('transactions.show', t.id)" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                      {{ t.invoice_number }}
                    </Link>
                  </td>
                  <td class="py-3.5 text-slate-600 dark:text-slate-400 text-xs">{{ t.created_at }}</td>
                  <td class="py-3.5 font-medium text-slate-700 dark:text-slate-300">{{ t.cashier_name }}</td>
                  <td class="py-3.5">
                    <span class="capitalize text-xxs font-bold text-slate-500 dark:text-slate-400">{{ t.payment_method === 'cash' ? 'Tunai' : t.payment_method }}</span>
                  </td>
                  <td class="py-3.5 text-right font-extrabold text-slate-900 dark:text-white">{{ formatRupiah(t.total) }}</td>
                </tr>

                <!-- Empty State -->
                <tr v-if="transactions.data.length === 0">
                  <td colspan="5" class="py-12 text-center text-slate-400 dark:text-slate-500">
                    Tidak ada transaksi pada rentang waktu terpilih
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="transactions.links.length > 3" class="pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="text-xs text-slate-500">
              Menampilkan <span class="font-medium text-slate-700 dark:text-slate-300">{{ transactions.from || 0 }} - {{ transactions.to || 0 }}</span> dari <span class="font-medium text-slate-700 dark:text-slate-300">{{ transactions.total }}</span> transaksi
            </div>

            <div class="flex items-center gap-1">
              <template v-for="(link, i) in transactions.links" :key="i">
                <div 
                  v-if="link.url === null" 
                  class="px-2 py-1 text-xxs text-slate-400 cursor-default"
                  v-html="link.label"
                />
                <Link 
                  v-else 
                  :href="link.url"
                  class="px-2.5 py-1 text-xxs font-bold rounded-lg border transition"
                  :class="link.active 
                    ? 'bg-indigo-600 border-indigo-600 text-white' 
                    : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300'"
                  v-html="link.label"
                />
              </template>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
