<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import {
  ShoppingCart,
  Package,
  TrendingUp,
  AlertTriangle,
  CreditCard,
  Wallet,
  QrCode,
  Clock,
  BarChart2,
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
    sparkline: { enabled: false },
  },
  colors: ['#3b82f6'],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.3,
      opacityTo: 0.02,
      stops: [0, 95, 100],
    },
  },
  stroke: { curve: 'smooth', width: 2.5 },
  xaxis: {
    categories: props.chartData?.labels ?? [],
    labels: { style: { fontFamily: 'Inter, sans-serif', fontSize: '12px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      formatter: (val) => 'Rp ' + new Intl.NumberFormat('id-ID').format(val),
      style: { fontFamily: 'Inter, sans-serif', fontSize: '11px' },
    },
  },
  grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
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
            <TrendingUp :size="22" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Penjualan Hari Ini</p>
            <p class="stat-card__value">{{ formatCurrency(stats.today_sales) }}</p>
          </div>
        </div>

        <!-- Today Transactions -->
        <div class="stat-card stat-card--success">
          <div class="stat-card__icon">
            <ShoppingCart :size="22" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Transaksi Hari Ini</p>
            <p class="stat-card__value">{{ stats.today_transactions }}</p>
          </div>
        </div>

        <!-- Monthly (Admin only) -->
        <div v-if="isAdmin" class="stat-card stat-card--info">
          <div class="stat-card__icon">
            <BarChart2 :size="22" />
          </div>
          <div class="stat-card__body">
            <p class="stat-card__label">Omzet Bulan Ini</p>
            <p class="stat-card__value">{{ formatCurrency(stats.month_sales) }}</p>
          </div>
        </div>

        <!-- Total Products (Admin only) -->
        <div v-if="isAdmin" class="stat-card" :class="stats.low_stock_count > 0 ? 'stat-card--warning' : 'stat-card--neutral'">
          <div class="stat-card__icon">
            <Package :size="22" />
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
            <Package :size="22" />
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
.dashboard { display: flex; flex-direction: column; gap: 24px; }

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-card__icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-card--primary .stat-card__icon  { background: #eff6ff; color: #2563eb; }
.stat-card--success .stat-card__icon  { background: #f0fdf4; color: #16a34a; }
.stat-card--warning .stat-card__icon  { background: #fffbeb; color: #d97706; }
.stat-card--info    .stat-card__icon  { background: #f5f3ff; color: #7c3aed; }
.stat-card--neutral .stat-card__icon  { background: #f8fafc; color: #475569; }

.stat-card__label  { font-size: 0.8rem; color: #94a3b8; font-weight: 500; margin-bottom: 4px; }
.stat-card__value  { font-size: 1.4rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
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
  border-radius: 12px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  overflow: hidden;
}
.card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
}
.card__title { font-size: 0.9rem; font-weight: 600; color: #0f172a; }
.card__link  { font-size: 0.8rem; color: #3b82f6; text-decoration: none; }
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
  color: #94a3b8;
}
.top-product__info { flex: 1; min-width: 0; }
.top-product__name { font-size: 0.85rem; font-weight: 500; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-product__qty  { font-size: 0.75rem; color: #94a3b8; }
.top-product__revenue { font-size: 0.82rem; font-weight: 600; color: #0f172a; white-space: nowrap; }

/* Transactions Table */
.transactions-table { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 11px 16px; text-align: left; font-size: 0.83rem; }
th { font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
td { color: #374151; border-bottom: 1px solid #f8fafc; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }
td:last-child, th:last-child { display: flex; align-items: center; gap: 4px; color: #94a3b8; }

.invoice-link { color: #2563eb; font-weight: 500; text-decoration: none; }
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
.payment-badge--transfer { background: #eff6ff; color: #2563eb; }
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
