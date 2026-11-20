<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import DashboardStatIllustration from '@/Components/DashboardStatIllustration.vue'
import { computed } from 'vue'
import {
  ShoppingCart,
  Package,
  AlertTriangle,
  CreditCard,
  Wallet,
  QrCode,
  Clock,
} from '@lucide/vue'
import { Link, Head } from '@inertiajs/vue3'

const props = defineProps({
  stats: Object,
  chartData: Object,
  recentTransactions: Array,
  isAdmin: Boolean,
})

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value ?? 0)
}

function paymentIcon(method) {
  const icons = { cash: Wallet, transfer: CreditCard, qris: QrCode }
  return icons[method] ?? Wallet
}

function paymentLabel(method) {
  const labels = { cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }
  return labels[method] ?? method
}

// ApexCharts config
const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
    foreColor: '#435a74',
    sparkline: { enabled: false },
  },
  colors: ['#526d8a'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.24,
      opacityTo: 0.02,
      stops: [0, 95, 100],
    },
  },
  stroke: { curve: 'smooth', width: 3 },
  markers: { size: 0, hover: { size: 5 } },
  xaxis: {
    categories: props.chartData?.labels ?? [],
    labels: { style: { fontFamily: 'Inter, sans-serif', fontSize: '12px', colors: '#526d8a' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      formatter: (val) => 'Rp ' + new Intl.NumberFormat('id-ID').format(val),
      style: { fontFamily: 'Inter, sans-serif', fontSize: '11px', colors: '#526d8a' },
    },
  },
  grid: { borderColor: '#e4eaf2', strokeDashArray: 4 },
  tooltip: {
    y: { formatter: (val) => formatCurrency(val) },
  },
  dataLabels: { enabled: false },
}))

const chartSeries = computed(() => [
  { name: 'Penjualan', data: props.chartData?.sales ?? [] },
])
</script>

<template>
  <Head title="Dashboard" />
  <AppLayout>
    <template #title>Dashboard</template>

    <div class="dashboard">
      <!-- Stats Cards -->
      <div class="stats-grid">

        <!-- Today Sales -->
        <div class="stat-card stat-card--primary">
          <div class="stat-card__icon">
            <DashboardStatIllustration kind="sales" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Penjualan Hari Ini</p>
            <p class="stat-card__value">{{ formatCurrency(stats.today_sales) }}</p>
          </div>
        </div>

        <!-- Today Transactions -->
        <div class="stat-card stat-card--success">
          <div class="stat-card__icon">
            <DashboardStatIllustration kind="transactions" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Transaksi Hari Ini</p>
            <p class="stat-card__value">{{ stats.today_transactions }}</p>
          </div>
        </div>

        <!-- Monthly (Admin only) -->
        <div v-if="isAdmin" class="stat-card stat-card--info">
          <div class="stat-card__icon">
            <DashboardStatIllustration kind="revenue" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Omzet Bulan Ini</p>
            <p class="stat-card__value">{{ formatCurrency(stats.month_sales) }}</p>
          </div>
        </div>

        <!-- Total Products (Admin only) -->
        <div v-if="isAdmin" class="stat-card" :class="stats.low_stock_count > 0 ? 'stat-card--warning' : 'stat-card--neutral'">
          <div class="stat-card__icon">
            <DashboardStatIllustration kind="products" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Total Produk</p>
            <p class="stat-card__value">{{ stats.total_products }}</p>
            <p v-if="stats.low_stock_count > 0" class="stat-card__sub">
              <AlertTriangle :size="12" />
              {{ stats.low_stock_count }} stok hampir habis
            </p>
          </div>
        </div>

        <!-- Items today (Kasir) -->
        <div v-if="!isAdmin" class="stat-card stat-card--info">
          <div class="stat-card__icon">
            <DashboardStatIllustration kind="products" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Item Terjual Hari Ini</p>
            <p class="stat-card__value">{{ stats.today_items }}</p>
          </div>
        </div>
      </div>

      <!-- Main grid: chart + table -->
      <div class="dashboard__main">

        <!-- Sales Chart (Admin only) -->
        <div v-if="isAdmin && chartData" class="card chart-card">
          <div class="card__header">
            <h3 class="card__title">Grafik Penjualan 7 Hari</h3>
          </div>
          <div class="chart-wrapper">
            <apexchart
              type="area"
              height="250"
              :options="chartOptions"
              :series="chartSeries"
            />
          </div>
        </div>

        <!-- Top Products (Admin only) -->
        <div v-if="isAdmin && chartData?.topProducts?.length" class="card">
          <div class="card__header">
            <h3 class="card__title">Top Produk Bulan Ini</h3>
          </div>
          <div class="top-products">
            <div
              v-for="(product, i) in chartData.topProducts"
              :key="i"
              class="top-product"
            >
              <span class="top-product__rank">#{{ i + 1 }}</span>
              <div class="top-product__info">
                <p class="top-product__name">{{ product.name }}</p>
                <p class="top-product__qty">{{ product.qty }} terjual</p>
              </div>
              <span class="top-product__revenue">{{ formatCurrency(product.total) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="card">
        <div class="card__header">
          <h3 class="card__title">
            {{ isAdmin ? 'Transaksi Terbaru' : 'Transaksi Hari Ini' }}
          </h3>
          <Link :href="route('transactions.index')" class="card__link">
            Lihat semua →
          </Link>
        </div>

        <div v-if="recentTransactions.length === 0" class="empty-state">
          <ShoppingCart :size="40" />
          <p>Belum ada transaksi hari ini</p>
        </div>

        <div v-else class="transactions-table">
          <table>
            <thead>
              <tr>
                <th>Invoice</th>
                <th>Kasir</th>
                <th>Item</th>
                <th>Pembayaran</th>
                <th>Total</th>
                <th>Waktu</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="trx in recentTransactions" :key="trx.id">
                <td>
                  <Link :href="route('transactions.show', trx.id)" class="invoice-link">
                    {{ trx.invoice_number }}
                  </Link>
                </td>
                <td>{{ trx.cashier_name }}</td>
                <td>{{ trx.items_count }} item</td>
                <td>
                  <span class="payment-badge" :class="`payment-badge--${trx.payment_method}`">
                    <component :is="paymentIcon(trx.payment_method)" :size="12" />
                    {{ paymentLabel(trx.payment_method) }}
                  </span>
                </td>
                <td class="font-semibold">{{ formatCurrency(trx.total) }}</td>
                <td class="text-muted">
                  <Clock :size="12" />
                  {{ trx.created_at }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts'
export default { components: { apexchart: VueApexCharts } }
</script>

<style scoped>
.dashboard { display: flex; flex-direction: column; gap: 18px; }

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.stat-card {
  position: relative;
  background: white;
  border-radius: 8px;
  padding: 16px 18px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  border: 1px solid #dbe3ee;
  box-shadow: 0 1px 2px rgba(24, 48, 84, 0.05);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
@media (min-width: 640px) {
  .stat-card {
    padding: 18px 20px;
    gap: 16px;
  }
}
.stat-card:hover {
  border-color: #b9c9df;
  box-shadow: 0 3px 10px rgba(24, 48, 84, 0.08);
}

/* Soft tile colors inspired by the reference dashboard. */
.stat-card--primary { background: #fff0e6; }
.stat-card--success { background: #fce8ec; }
.stat-card--info { background: #e7eff8; }
.stat-card--warning { background: #fff3d6; }
.stat-card--neutral { background: #e7f4df; }

.stat-card__icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-card--primary .stat-card__icon,
.stat-card--success .stat-card__icon,
.stat-card--warning .stat-card__icon,
.stat-card--info .stat-card__icon,
.stat-card--neutral .stat-card__icon {
  background: transparent;
}

.stat-card__body {
  flex: 1;
  min-width: 0;
  padding-right: 8px;
}

.stat-card__label  { font-size: 0.74rem; color: #65758b; font-weight: 600; margin-bottom: 5px; letter-spacing: 0.025em; }
.stat-card__value  { font-size: 1.25rem; font-weight: 700; color: #36495e; line-height: 1.2; }
@media (min-width: 640px) {
  .stat-card__value  { font-size: 1.4rem; }
}
.stat-card__sub    {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  color: #d97706;
  margin-top: 4px;
}

/* Main grid */
.dashboard__main {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 16px;
}
@media (max-width: 1024px) {
  .dashboard__main { grid-template-columns: 1fr; }
}

/* Card */
.card {
  background: white;
  border-radius: 8px;
  border: 1px solid #dbe3ee;
  box-shadow: 0 1px 2px rgba(24, 48, 84, 0.05);
  overflow: hidden;
}
.card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #e4eaf2;
  background: #fbfcfe;
}
.card__title { font-size: 0.88rem; font-weight: 650; color: #263f5e; }
.card__link  { font-size: 0.78rem; color: #526d8a; text-decoration: none; font-weight: 600; }
.card__link:hover { text-decoration: underline; }

.chart-wrapper { padding: 16px 8px 8px; }

/* Top Products */
.top-products { padding: 8px 0; }
.top-product {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 20px;
  transition: background 0.1s;
}
.top-product:hover { background: #f8fafc; }
.top-product__rank {
  width: 24px;
  font-size: 0.75rem;
  font-weight: 700;
  color: #356b9d;
}
.top-product__info { flex: 1; min-width: 0; }
.top-product__name { font-size: 0.85rem; font-weight: 500; color: #172b4d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-product__qty  { font-size: 0.75rem; color: #7187a1; }
.top-product__revenue { font-size: 0.82rem; font-weight: 600; color: #356b9d; white-space: nowrap; }

/* Transactions Table */
.transactions-table { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 11px 16px; text-align: left; font-size: 0.83rem; }
th { font-weight: 600; color: #52647d; border-bottom: 1px solid #e1e8f1; background: #f3f6fa; }
td { color: #35445a; border-bottom: 1px solid #edf1f6; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f7f9fc; }
td:last-child, th:last-child { display: flex; align-items: center; gap: 4px; color: #94a3b8; }

.invoice-link { color: #526d8a; font-weight: 600; text-decoration: none; }
.invoice-link:hover { text-decoration: underline; }

.payment-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 500;
}
.payment-badge--cash     { background: #f0fdf4; color: #16a34a; }
.payment-badge--transfer { background: #edf2f6; color: #526d8a; }
.payment-badge--qris     { background: #faf5ff; color: #7c3aed; }

.font-semibold { font-weight: 600; }
.text-muted    { color: #94a3b8; }

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px;
  color: #cbd5e1;
}
.empty-state p { font-size: 0.9rem; }
</style>
