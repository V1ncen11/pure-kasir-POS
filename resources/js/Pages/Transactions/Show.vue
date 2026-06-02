<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Printer, AlertTriangle, CheckCircle2, RotateCcw, Wallet, Banknote, CreditCard } from '@lucide/vue'
import { useToast } from 'vue-toastification'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  transaction: Object,
})

const toast = useToast()
const { app_name = 'KASIR POS' } = usePage().props.settings || {}

const formatRupiah = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value)
}

const printReceipt = () => {
  window.print()
  toast.success('Membuka dialog pencetakan...')
}
</script>

<template>
  <AppLayout title="Detail Transaksi">
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Action Bar -->
      <div class="flex items-center justify-between no-print">
        <Link 
          :href="route('transactions.index')"
          class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Kembali ke Riwayat</span>
        </Link>

        <button 
          @click="printReceipt"
          class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-lg hover:shadow-indigo-500/20 active:shadow-none transition-all duration-200"
        >
          <Printer class="w-4 h-4" />
          <span>Cetak Struk</span>
        </button>
      </div>

      <!-- Main Receipt Card -->
      <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl shadow-sm overflow-hidden p-6 md:p-8 relative">
        <!-- Print Header (Hidden on screen) -->
        <div class="print-header hidden text-center mb-8 border-b border-dashed pb-6">
          <h2 class="text-2xl font-extrabold tracking-wide uppercase text-black">{{ app_name }}</h2>
          <p class="text-xs text-slate-600 mt-1">Struk Bukti Pembayaran Resmi</p>
        </div>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pb-8 border-b border-slate-100 dark:border-slate-700">
          <!-- Left Info Column -->
          <div class="space-y-4">
            <div>
              <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-1">Nomor Invoice</span>
              <div class="flex items-center gap-3">
                <h2 class="text-xl md:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ transaction.invoice_number }}</h2>
                <span 
                  class="px-3 py-1.5 rounded-2xl text-[10px] md:text-xs font-bold uppercase tracking-wider shadow-sm flex items-center gap-1.5 no-print"
                  :class="transaction.status === 'completed'
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400' 
                    : 'bg-rose-100 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="transaction.status === 'completed' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                  {{ transaction.status === 'completed' ? 'Lunas' : 'Void / Batal' }}
                </span>
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-1">Tanggal Transaksi</span>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ transaction.created_at }}</p>
              </div>
              <div>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-1">Kasir Pelaksana</span>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ transaction.cashier_name }}</p>
              </div>
            </div>
          </div>

          <!-- Right Info Column -->
          <div class="space-y-4 md:text-right flex flex-col md:items-end justify-end">
            <div class="w-full md:max-w-xs">
              <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-1 md:text-right">Metode Pembayaran</span>
              <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-300">
                <Banknote v-if="transaction.payment_method === 'cash'" class="w-4 h-4 text-emerald-500" />
                <CreditCard v-else-if="transaction.payment_method === 'transfer'" class="w-4 h-4 text-sky-500" />
                <Wallet v-else class="w-4 h-4 text-indigo-500" />
                <span class="capitalize">{{ transaction.payment_method === 'cash' ? 'Tunai' : transaction.payment_method }}</span>
              </div>
            </div>

            <div v-if="transaction.notes" class="w-full md:max-w-xs text-left md:text-right">
              <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-1">Catatan Tambahan</span>
              <p class="text-xs italic text-slate-600 dark:text-slate-400 font-medium">{{ transaction.notes }}</p>
            </div>
          </div>
        </div>

        <!-- Void Alert Banner -->
        <div v-if="transaction.status === 'voided'" class="mt-6 p-4 bg-rose-50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/30 rounded-2xl flex items-start gap-3">
          <AlertTriangle class="w-5 h-5 text-rose-500 dark:text-rose-400 shrink-0 mt-0.5" />
          <div>
            <h4 class="text-sm font-bold text-rose-800 dark:text-rose-400">Transaksi Telah Dibatalkan (VOID)</h4>
            <p class="text-xs text-rose-700 dark:text-rose-400/80 mt-0.5">
              Seluruh item dalam transaksi ini telah dibatalkan. Riwayat stok untuk produk-produk ini telah dikembalikan secara otomatis.
            </p>
          </div>
        </div>

        <!-- Items Table -->
        <div class="mt-8">
          <h3 class="text-sm font-extrabold text-slate-800 dark:text-white uppercase tracking-wider mb-4">Daftar Belanja</h3>
          
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-b border-slate-100 dark:border-slate-700 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                  <th class="pb-3 text-left">Detail Produk</th>
                  <th class="pb-3 text-right">Harga Satuan</th>
                  <th class="pb-3 text-center">Jumlah</th>
                  <th class="pb-3 text-right">Diskon Item</th>
                  <th class="pb-3 text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <tr v-for="item in transaction.items" :key="item.id" class="text-sm text-slate-700 dark:text-slate-300">
                  <td class="py-4">
                    <div class="font-bold text-slate-900 dark:text-white">{{ item.product_name }}</div>
                    <div class="text-xs text-slate-400 dark:text-slate-500 font-mono mt-0.5">SKU: {{ item.product_sku || '-' }}</div>
                  </td>
                  <td class="py-4 text-right font-medium">{{ formatRupiah(item.price) }}</td>
                  <td class="py-4 text-center font-bold">{{ item.qty }}</td>
                  <td class="py-4 text-right font-semibold" :class="item.discount > 0 ? 'text-red-500 dark:text-red-400' : 'text-slate-400'">
                    {{ item.discount > 0 ? formatRupiah(item.discount) : '-' }}
                  </td>
                  <td class="py-4 text-right font-bold text-slate-900 dark:text-white">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Totals & Payment Details Block -->
        <div class="mt-8 pt-8 border-t border-slate-100 dark:border-slate-700 flex flex-col md:flex-row md:justify-between gap-6">
          <div class="w-full md:max-w-sm order-2 md:order-1 flex items-end">
            <!-- Signature/Thankyou for printing -->
            <div class="print-footer hidden text-center w-full mt-8 border-t border-dashed pt-6 text-xs text-slate-500">
              <p class="font-bold mb-1">Terima Kasih Atas Kunjungan Anda!</p>
              <p>Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan.</p>
            </div>
          </div>

          <div class="w-full md:max-w-md ml-auto order-1 md:order-2 space-y-3">
            <div class="flex justify-between items-center text-sm font-semibold text-slate-600 dark:text-slate-400">
              <span>Subtotal</span>
              <span>{{ formatRupiah(transaction.subtotal) }}</span>
            </div>

            <div v-if="transaction.discount > 0" class="flex justify-between items-center text-sm font-bold text-red-500">
              <span>Total Diskon</span>
              <span>-{{ formatRupiah(transaction.discount) }}</span>
            </div>

            <div v-if="transaction.tax > 0" class="flex justify-between items-center text-sm font-semibold text-slate-600 dark:text-slate-400">
              <span>Pajak PPN ({{ transaction.tax_percentage || 11 }}%)</span>
              <span>{{ formatRupiah(transaction.tax) }}</span>
            </div>

            <div class="flex justify-between items-center pt-3 border-t border-slate-100 dark:border-slate-700 text-lg font-extrabold text-slate-900 dark:text-white">
              <span>Total Pembayaran</span>
              <span class="text-2xl text-indigo-600 dark:text-indigo-400">{{ formatRupiah(transaction.total) }}</span>
            </div>

            <div class="flex justify-between items-center text-sm font-semibold text-slate-600 dark:text-slate-400 pt-3">
              <span>Jumlah Bayar</span>
              <span>{{ formatRupiah(transaction.paid_amount) }}</span>
            </div>

            <div class="flex justify-between items-center text-sm font-bold" :class="transaction.change_amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
              <span>Kembalian</span>
              <span>{{ formatRupiah(transaction.change_amount) }}</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<style>
@media print {
  body {
    background-color: white !important;
    color: black !important;
  }
  .no-print {
    display: none !important;
  }
  .print-header {
    display: block !important;
  }
  .print-footer {
    display: block !important;
  }
  aside, nav, header {
    display: none !important;
  }
  main {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
  }
  .max-w-4xl {
    max-width: 100% !important;
    width: 100% !important;
  }
  .bg-white, .dark\:bg-slate-800 {
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
  }
  table th {
    color: #000 !important;
    border-bottom: 2px solid #000 !important;
  }
  table td {
    border-bottom: 1px solid #ddd !important;
  }
}
</style>
