<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { 
  Store, MapPin, Phone, Mail, AtSign, Globe, Percent, AlertCircle,
  FileText, Upload, Image, Trash2, ShieldCheck, ToggleLeft, ToggleRight,
  Database, Download, FileSpreadsheet
} from '@lucide/vue'

const props = defineProps({
  settings: Object,
})

const toast = useToast()
const activeTab = ref('general')

const form = useForm({
  store_name: props.settings.store_name || '',
  store_address: props.settings.store_address || '',
  store_phone: props.settings.store_phone || '',
  store_email: props.settings.store_email || '',
  instagram: props.settings.instagram || '',
  website: props.settings.website || '',
  tax_percentage: props.settings.tax_percentage || 0,
  receipt_header: props.settings.receipt_header || '',
  receipt_footer: props.settings.receipt_footer || '',
  low_stock_alert: props.settings.low_stock_alert === '1' || props.settings.low_stock_alert === 1 || props.settings.low_stock_alert === true,
  receipt_paper_width: props.settings.receipt_paper_width || '58mm',
})

const logoForm = useForm({
  logo: null
})

const logoPreview = ref(props.settings.store_logo ? `/storage/${props.settings.store_logo}` : null)
const fileInput = ref(null)

const triggerFileSelect = () => {
  fileInput.value.click()
}

const onFileChange = (e) => {
  const file = e.target.files[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    toast.error('Ukuran logo tidak boleh melebihi 2MB!')
    return
  }

  logoForm.logo = file
  const reader = new FileReader()
  reader.onload = (event) => {
    logoPreview.value = event.target.result
  }
  reader.readAsDataURL(file)

  uploadLogo()
}

const uploadLogo = () => {
  logoForm.post(route('settings.logo'), {
    forceFormData: true,
    onError: () => {
      toast.error('Gagal mengupload logo toko.')
    }
  })
}

const saveSettings = () => {
  form.post(route('settings.update'), {
    onError: () => {
      toast.error('Gagal menyimpan pengaturan. Mohon cek kembali inputan Anda.')
    }
  })
}
</script>

<template>
  <AppLayout title="Pengaturan Sistem">
    <template #title>Pengaturan Toko</template>
    <div class="max-w-5xl mx-auto space-y-6">
      
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Pengaturan Toko</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Sesuaikan konfigurasi toko, format struk thermal, pajak pertambahan nilai, dan logo resmi.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Sidebar Navigation Settings (Tab buttons) -->
        <div class="space-y-1.5 md:col-span-1">
          <button 
            @click="activeTab = 'general'"
            class="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-2xl transition duration-150 border"
            :class="activeTab === 'general'
              ? 'bg-primary-600 border-primary-600 text-white shadow-lg shadow-primary-600/10'
              : 'bg-white border-slate-100 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-slate-750 dark:text-slate-300 dark:hover:bg-slate-700/50'"
          >
            <Store class="w-4.5 h-4.5" />
            <span>Informasi Toko</span>
          </button>

          <button 
            @click="activeTab = 'receipt'"
            class="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-2xl transition duration-150 border"
            :class="activeTab === 'receipt'
              ? 'bg-primary-600 border-primary-600 text-white shadow-lg shadow-primary-600/10'
              : 'bg-white border-slate-100 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-slate-750 dark:text-slate-300 dark:hover:bg-slate-700/50'"
          >
            <FileText class="w-4.5 h-4.5" />
            <span>Format Struk</span>
          </button>

          <button 
            @click="activeTab = 'finance'"
            class="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-2xl transition duration-150 border"
            :class="activeTab === 'finance'
              ? 'bg-primary-600 border-primary-600 text-white shadow-lg shadow-primary-600/10'
              : 'bg-white border-slate-100 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-slate-750 dark:text-slate-300 dark:hover:bg-slate-700/50'"
          >
            <Percent class="w-4.5 h-4.5" />
            <span>Pajak & Alarm</span>
          </button>

          <button 
            @click="activeTab = 'backup'"
            class="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-2xl transition duration-150 border"
            :class="activeTab === 'backup'
              ? 'bg-primary-600 border-primary-600 text-white shadow-lg shadow-primary-600/10'
              : 'bg-white border-slate-100 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-slate-750 dark:text-slate-300 dark:hover:bg-slate-700/50'"
          >
            <Database class="w-4.5 h-4.5" />
            <span>Backup Data</span>
          </button>
        </div>

        <!-- Form Panels -->
        <div class="md:col-span-3 space-y-6">
          
          <!-- Logo Uploader Box (General for all, but fits next to General info) -->
          <div v-show="activeTab === 'general'" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-extrabold text-slate-850 dark:text-white uppercase tracking-wider">Logo Toko / Struk</h3>
            
            <div class="flex flex-col sm:flex-row items-center gap-6">
              <!-- Preview Frame -->
              <div class="w-28 h-28 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-center overflow-hidden shrink-0">
                <img v-if="logoPreview" :src="logoPreview" class="max-w-full max-h-full object-contain" />
                <div v-else class="text-center text-slate-400 p-2">
                  <Image class="w-8 h-8 mx-auto mb-1 text-slate-300" />
                  <span class="text-xxs font-semibold">Belum Ada Logo</span>
                </div>
              </div>

              <!-- Action buttons for Logo -->
              <div class="space-y-2 text-center sm:text-left">
                <input 
                  type="file" 
                  ref="fileInput" 
                  @change="onFileChange" 
                  accept="image/*"
                  class="hidden" 
                />
                
                <button 
                  type="button" 
                  @click="triggerFileSelect"
                  class="flex items-center justify-center gap-2 px-4 py-2 bg-primary-50 hover:bg-primary-100 border border-primary-200 text-indigo-750 text-xs font-bold rounded-xl transition dark:bg-primary-900/20 dark:border-primary-900/30 dark:text-primary-400"
                >
                  <Upload class="w-3.5 h-3.5" />
                  <span>Pilih Logo Toko</span>
                </button>
                <p class="text-xxs text-slate-405 dark:text-slate-500">Format gambar JPG, PNG, atau WEBP. Maksimum 2MB.</p>
              </div>
            </div>
          </div>

          <!-- BACKUP DATA PANEL -->
          <div v-show="activeTab === 'backup'" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-extrabold text-slate-850 dark:text-white uppercase tracking-wider">Pencadangan Data (Backup)</h3>
            
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
              Backup SQL digunakan untuk memulihkan database melalui alat pengelolaan database. Ekspor Excel menyertakan ringkasan operasional hari ini (transaksi, omzet, pembelian stok, dan stok menipis/habis), daftar transaksi hari ini, daftar stok yang perlu dicek, serta data lengkap produk dan riwayat toko.
            </p>

            <div class="pt-2 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
              <a 
                :href="route('settings.backup')" 
                class="flex items-center justify-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition duration-150 shadow-lg shadow-emerald-650/15 cursor-pointer active:scale-95"
              >
                <Download class="w-4 h-4" />
                <span>Download Backup Database (SQL)</span>
              </a>
              <a
                :href="route('settings.export-data')"
                class="flex items-center justify-center gap-2 px-5 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold rounded-xl transition duration-150 shadow-md shadow-primary-600/10 cursor-pointer active:scale-95"
              >
                <FileSpreadsheet class="w-4 h-4" />
                <span>Ekspor Data Toko (Excel)</span>
              </a>
            </div>
          </div>

          <!-- Settings Main Form fields -->
          <form @submit.prevent="saveSettings" class="space-y-6">
            
            <!-- GENERAL SETTINGS PANEL -->
            <div v-show="activeTab === 'general'" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-6 shadow-sm space-y-4">
              <h3 class="text-sm font-extrabold text-slate-850 dark:text-white uppercase tracking-wider">Identitas & Kontak</h3>

              <div class="space-y-4">
                <!-- Store Name -->
                <div>
                  <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Nama Toko / Bisnis</label>
                  <div class="relative">
                    <Store class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                    <input 
                      v-model="form.store_name"
                      type="text"
                      required
                      placeholder="Masukkan nama toko Anda"
                      class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                    />
                  </div>
                  <p v-if="form.errors.store_name" class="text-xs text-red-500 mt-1">{{ form.errors.store_name }}</p>
                </div>

                <!-- Store Phone & Email in row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">No. Telepon / HP</label>
                    <div class="relative">
                      <Phone class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                      <input 
                        v-model="form.store_phone"
                        type="text"
                        placeholder="Contoh: 08123456789"
                        class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                      />
                    </div>
                    <p v-if="form.errors.store_phone" class="text-xs text-red-500 mt-1">{{ form.errors.store_phone }}</p>
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Alamat Email Toko</label>
                    <div class="relative">
                      <Mail class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                      <input 
                        v-model="form.store_email"
                        type="email"
                        placeholder="toko@bisnis.com"
                        class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                      />
                    </div>
                    <p v-if="form.errors.store_email" class="text-xs text-red-500 mt-1">{{ form.errors.store_email }}</p>
                  </div>
                </div>

                <!-- Optional Social Media & Website -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Instagram <span class="normal-case tracking-normal font-medium">(opsional)</span></label>
                    <div class="relative">
                      <AtSign class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                      <input
                        v-model="form.instagram"
                        type="text"
                        maxlength="120"
                        placeholder="@namatoko atau tautan Instagram"
                        class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                      />
                    </div>
                    <p v-if="form.errors.instagram" class="text-xs text-red-500 mt-1">{{ form.errors.instagram }}</p>
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Website <span class="normal-case tracking-normal font-medium">(opsional)</span></label>
                    <div class="relative">
                      <Globe class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                      <input
                        v-model="form.website"
                        type="url"
                        maxlength="255"
                        placeholder="https://tokokamu.com"
                        class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                      />
                    </div>
                    <p v-if="form.errors.website" class="text-xs text-red-500 mt-1">{{ form.errors.website }}</p>
                  </div>
                </div>

                <!-- Store Address -->
                <div>
                  <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Alamat Toko</label>
                  <div class="relative">
                    <MapPin class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                    <textarea 
                      v-model="form.store_address"
                      rows="3"
                      placeholder="Masukkan alamat fisik toko secara lengkap..."
                      class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                    ></textarea>
                  </div>
                  <p v-if="form.errors.store_address" class="text-xs text-red-500 mt-1">{{ form.errors.store_address }}</p>
                </div>
              </div>
            </div>

            <!-- RECEIPT SETTINGS PANEL -->
            <div v-show="activeTab === 'receipt'" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-6 shadow-sm space-y-4">
              <h3 class="text-sm font-extrabold text-slate-850 dark:text-white uppercase tracking-wider">Format Cetak Struk Thermal</h3>

              <div class="space-y-4">
                <!-- Receipt Header -->
                <div>
                  <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Header Struk (Baris Atas)</label>
                  <input 
                    v-model="form.receipt_header"
                    type="text"
                    placeholder="Contoh: Terimakasih telah belanja di toko kami!"
                    class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                  />
                  <p class="text-xxs text-slate-405 dark:text-slate-550 mt-1">Dicetak pada bagian paling atas kertas struk.</p>
                  <p v-if="form.errors.receipt_header" class="text-xs text-red-500 mt-1">{{ form.errors.receipt_header }}</p>
                </div>

                <!-- Receipt Footer -->
                <div>
                  <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Footer Struk (Baris Bawah)</label>
                  <input 
                    v-model="form.receipt_footer"
                    type="text"
                    placeholder="Contoh: Barang yang dibeli tidak dapat ditukar"
                    class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                  />
                  <p class="text-xxs text-slate-405 dark:text-slate-550 mt-1">Dicetak pada bagian paling bawah kertas struk.</p>
                  <p v-if="form.errors.receipt_footer" class="text-xs text-red-500 mt-1">{{ form.errors.receipt_footer }}</p>
                </div>

                <!-- Receipt Paper Width -->
                <div>
                  <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Lebar Kertas Struk (Thermal)</label>
                  <select 
                    v-model="form.receipt_paper_width"
                    class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm appearance-none pr-8 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%3E%3Cpath%20d%3D%22M7%209l3%203%203-3%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem] bg-[right_0.5rem_center] bg-no-repeat"
                  >
                    <option value="58mm">58mm (Printer Kecil)</option>
                    <option value="80mm">80mm (Printer Standar/Sedang)</option>
                  </select>
                  <p class="text-xxs text-slate-405 dark:text-slate-550 mt-1">Menentukan batas lebar maksimum struk agar rapi di printer thermal Anda.</p>
                  <p v-if="form.errors.receipt_paper_width" class="text-xs text-red-500 mt-1">{{ form.errors.receipt_paper_width }}</p>
                </div>
              </div>
            </div>

            <!-- FINANCE / ALERT SETTINGS PANEL -->
            <div v-show="activeTab === 'finance'" class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-6 shadow-sm space-y-4">
              <h3 class="text-sm font-extrabold text-slate-850 dark:text-white uppercase tracking-wider">Perpajakan & Keamanan Stok</h3>

              <div class="space-y-4">
                <!-- Tax Percentage -->
                <div>
                  <label class="block text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1.5">Persentase Pajak PPN (%)</label>
                  <div class="relative">
                    <Percent class="absolute left-3.5 top-2.5 h-4.5 w-4.5 text-slate-400" />
                    <input 
                      v-model="form.tax_percentage"
                      type="number"
                      step="0.01"
                      required
                      placeholder="Masukkan nilai persen (contoh: 11)"
                      class="w-full pl-11 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 text-sm"
                    />
                  </div>
                  <p class="text-xxs text-slate-405 dark:text-slate-550 mt-1">Persentase pajak pertambahan nilai otomatis pada hitungan kasir POS.</p>
                  <p v-if="form.errors.tax_percentage" class="text-xs text-red-500 mt-1">{{ form.errors.tax_percentage }}</p>
                </div>

                <!-- Low Stock Alert Toggle -->
                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700 rounded-2xl">
                  <div class="flex items-start gap-3">
                    <AlertCircle class="w-5 h-5 text-primary-600 shrink-0 mt-0.5" />
                    <div>
                      <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Notifikasi Stok Menipis</h4>
                      <p class="text-xs text-slate-400 dark:text-slate-500">Tampilkan alarm peringatan stok di dashboard/sidebar bila stok menipis.</p>
                    </div>
                  </div>
                  
                  <button 
                    type="button"
                    @click="form.low_stock_alert = !form.low_stock_alert"
                    class="text-primary-600 dark:text-primary-400 focus:outline-none transition duration-150"
                  >
                    <ToggleRight v-if="form.low_stock_alert" class="w-10 h-10 text-primary-600" />
                    <ToggleLeft v-else class="w-10 h-10 text-slate-450" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Submit Button (Sticky or placed beautifully at bottom) -->
            <div class="flex items-center justify-end gap-3 pt-2">
              <button 
                type="submit"
                :disabled="form.processing"
                class="w-full sm:w-auto px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold rounded-xl transition duration-150 shadow-lg shadow-primary-600/10"
              >
                {{ form.processing ? 'Menyimpan...' : 'Simpan Semua Pengaturan' }}
              </button>
            </div>

          </form>

        </div>
      </div>

    </div>
  </AppLayout>
</template>
