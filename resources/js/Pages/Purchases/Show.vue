<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, Printer, Calendar, User, Truck, FileText } from '@lucide/vue'

const props = defineProps({
  purchase: Object
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value ?? 0)
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  const options = { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }
  return new Date(dateString).toLocaleDateString('id-ID', options)
}

const printInvoice = () => {
  window.print()
}
</script>

<template>
  <Head :title="`Detail Stok Masuk #${purchase.id}`" />
  <AppLayout>
    <template #title>Detail Stok Masuk</template>
    
    <div class="page-header no-print">
      <div class="flex items-center gap-4">
        <Link :href="route('purchases.index')" class="btn-icon bg-white border border-gray-200">
          <ArrowLeft :size="18" />
        </Link>
        <div>
          <h2 class="page-title">Detail Stok Masuk</h2>
          <p class="page-subtitle">Lihat rincian transaksi pembelian stok</p>
        </div>
      </div>
      <button @click="printInvoice" class="btn btn-secondary">
        <Printer :size="18" /> Cetak
      </button>
    </div>

    <div class="invoice-container">
      <!-- Invoice Header -->
      <div class="invoice-header">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 mb-1">STOK MASUK</h1>
          <div class="text-gray-500">ID Sistem: #{{ purchase.id }}</div>
        </div>
        <div class="text-right">
          <div class="text-sm text-gray-500 mb-1">No. Referensi / Nota</div>
          <div class="text-lg font-bold text-gray-900">{{ purchase.reference_no || '-' }}</div>
        </div>
      </div>

      <!-- Invoice Info Grid -->
      <div class="info-grid">
        <div class="info-box">
          <div class="info-box-icon"><Truck :size="20" /></div>
          <div>
            <div class="info-label">Supplier</div>
            <div class="info-value">{{ purchase.supplier?.name || 'Terhapus' }}</div>
            <div class="info-sub text-sm">{{ purchase.supplier?.phone || '-' }}</div>
            <div class="info-sub text-sm mt-1">{{ purchase.supplier?.address || '-' }}</div>
          </div>
        </div>

        <div class="info-box">
          <div class="info-box-icon"><Calendar :size="20" /></div>
          <div>
            <div class="info-label">Tanggal Masuk</div>
            <div class="info-value">{{ formatDate(purchase.date) }}</div>
          </div>
        </div>

        <div class="info-box">
          <div class="info-box-icon"><User :size="20" /></div>
          <div>
            <div class="info-label">Dibuat Oleh (Admin)</div>
            <div class="info-value">{{ purchase.user?.name || 'Sistem' }}</div>
          </div>
        </div>
      </div>

      <!-- Notes if any -->
      <div v-if="purchase.notes" class="notes-box">
        <div class="flex items-center gap-2 font-semibold mb-1 text-gray-700">
          <FileText :size="16" /> Catatan
        </div>
        <p class="text-gray-600 m-0">{{ purchase.notes }}</p>
      </div>

      <!-- Items Table -->
      <div class="mt-8">
        <h3 class="text-lg font-bold mb-4">Rincian Barang</h3>
        <table class="table-invoice">
          <thead>
            <tr>
              <th width="50">No</th>
              <th>Nama Produk</th>
              <th class="text-center" width="100">Qty</th>
              <th class="text-right" width="200">Harga Beli</th>
              <th class="text-right" width="200">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in purchase.items" :key="item.id">
              <td class="text-center">{{ index + 1 }}</td>
              <td>
                <div class="font-medium text-gray-900">{{ item.product?.name || 'Produk Terhapus' }}</div>
                <div v-if="item.product?.sku" class="text-xs text-gray-500">SKU: {{ item.product.sku }}</div>
              </td>
              <td class="text-center">{{ item.qty }}</td>
              <td class="text-right">{{ formatCurrency(item.purchase_price) }}</td>
              <td class="text-right font-medium text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-right font-bold py-4">Total Keseluruhan</td>
              <td class="text-right font-bold text-lg text-blue-600 py-4">{{ formatCurrency(purchase.total_amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

    </div>

  </AppLayout>
</template>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
.page-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; }
.page-subtitle { font-size: 0.875rem; color: #64748b; margin: 4px 0 0; }

.flex { display: flex; }
.items-center { align-items: center; }
.gap-4 { gap: 16px; }
.gap-2 { gap: 8px; }
.mt-1 { margin-top: 4px; }
.mt-8 { margin-top: 32px; }
.mb-1 { margin-bottom: 4px; }
.mb-4 { margin-bottom: 16px; }
.m-0 { margin: 0; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.text-2xl { font-size: 1.5rem; }
.text-lg { font-size: 1.125rem; }
.text-sm { font-size: 0.875rem; }
.text-xs { font-size: 0.75rem; }
.text-gray-900 { color: #0f172a; }
.text-gray-700 { color: #334155; }
.text-gray-600 { color: #475569; }
.text-gray-500 { color: #64748b; }
.text-blue-600 { color: #2563eb; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-weight: 500; font-size: 0.875rem; cursor: pointer; border: none; transition: 0.2s; }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover { background: #e2e8f0; }

.btn-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: none; background: transparent; cursor: pointer; transition: 0.2s; text-decoration: none; color: #475569; }

/* Invoice Container */
.invoice-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 40px; margin-bottom: 40px; }
.invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #f1f5f9; padding-bottom: 24px; }

.info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px; }
.info-box { display: flex; gap: 16px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9; }
.info-box-icon { width: 40px; height: 40px; border-radius: 8px; background: white; color: #3b82f6; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); flex-shrink: 0; }
.info-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
.info-value { font-weight: 600; color: #0f172a; }

.notes-box { padding: 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; margin-bottom: 24px; }

.table-invoice { width: 100%; border-collapse: collapse; }
.table-invoice th, .table-invoice td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.table-invoice th { background: #f8fafc; color: #475569; font-weight: 600; font-size: 0.875rem; }
.table-invoice tfoot td { border-top: 2px solid #e2e8f0; border-bottom: none; }

/* Print styles */
@media print {
  .no-print { display: none !important; }
  body { background: white; }
  .invoice-container { box-shadow: none; border: none; padding: 0; margin: 0; }
  .info-box { border: 1px solid #e2e8f0; }
}
</style>
