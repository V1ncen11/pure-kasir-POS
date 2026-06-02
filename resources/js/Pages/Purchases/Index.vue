<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { Plus, Eye, Clock, User, Package } from '@lucide/vue'

const props = defineProps({
  purchases: Object
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
  const options = { day: '2-digit', month: 'short', year: 'numeric' }
  return new Date(dateString).toLocaleDateString('id-ID', options)
}
</script>

<template>
  <Head title="Stok Masuk" />
  <AppLayout>
    <template #title>Stok Masuk</template>
    
    <div class="page-header">
      <div>
        <h2 class="page-title">Riwayat Stok Masuk</h2>
        <p class="page-subtitle">Daftar transaksi pembelian barang dari supplier</p>
      </div>
      <Link :href="route('purchases.create')" class="btn btn-primary">
        <Plus :size="18" /> Tambah Stok Masuk
      </Link>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>No. Referensi</th>
              <th>Tanggal</th>
              <th>Supplier</th>
              <th>Item</th>
              <th>Total Biaya</th>
              <th>Admin</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="purchases.data.length === 0">
              <td colspan="7" class="text-center py-8 text-gray-500">
                Belum ada data stok masuk.
              </td>
            </tr>
            <tr v-for="purchase in purchases.data" :key="purchase.id">
              <td class="font-medium text-blue-600">{{ purchase.reference_no || '-' }}</td>
              <td>
                <div class="flex items-center gap-2 text-gray-600">
                  <Clock :size="14" />
                  {{ formatDate(purchase.date) }}
                </div>
              </td>
              <td class="font-medium">{{ purchase.supplier?.name || 'Supplier Terhapus' }}</td>
              <td>
                <span class="badge badge-gray">
                  <Package :size="12" /> {{ purchase.items_count }} Item
                </span>
              </td>
              <td class="font-semibold">{{ formatCurrency(purchase.total_amount) }}</td>
              <td>
                <div class="flex items-center gap-2 text-gray-600">
                  <User :size="14" />
                  {{ purchase.user?.name || 'Sistem' }}
                </div>
              </td>
              <td class="text-right">
                <Link :href="route('purchases.show', purchase.id)" class="btn-icon text-blue-600 hover:bg-blue-50">
                  <Eye :size="16" />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="purchases.links && purchases.links.length > 3" class="pagination">
        <template v-for="(link, k) in purchases.links" :key="k">
          <Link
            v-if="link.url"
            :href="link.url"
            class="page-link"
            :class="{ 'page-link-active': link.active }"
            v-html="link.label"
          />
          <span v-else class="page-link disabled" v-html="link.label"></span>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
.page-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; }
.page-subtitle { font-size: 0.875rem; color: #64748b; margin: 4px 0 0; }

.card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 16px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
.table th { font-weight: 600; color: #475569; background: #f8fafc; }
.font-medium { font-weight: 500; color: #0f172a; }
.font-semibold { font-weight: 600; color: #0f172a; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-blue-600 { color: #2563eb; }
.text-gray-500 { color: #64748b; }
.text-gray-600 { color: #475569; }

.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 8px; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }

.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 500; }
.badge-gray { background: #f1f5f9; color: #475569; }

.btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; background: transparent; cursor: pointer; transition: 0.2s; text-decoration: none; }
.hover\:bg-blue-50:hover { background: #eff6ff; }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-weight: 500; font-size: 0.875rem; cursor: pointer; border: none; transition: 0.2s; text-decoration: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }

.pagination { padding: 16px; border-top: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; justify-content: center; }
.page-link { padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0; color: #475569; text-decoration: none; font-size: 0.875rem; transition: 0.2s; }
.page-link:hover:not(.disabled) { background: #f8fafc; border-color: #cbd5e1; }
.page-link-active { background: #eff6ff; border-color: #2563eb; color: #2563eb; font-weight: 500; }
.page-link.disabled { opacity: 0.5; cursor: not-allowed; background: #f8fafc; }
</style>
