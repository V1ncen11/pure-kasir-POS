<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import JsBarcode from 'jsbarcode'
import { BrowserMultiFormatReader } from '@zxing/browser'
import {
  Search, ShoppingCart, Trash2, Plus, Minus, CreditCard, Camera,
  Wallet, QrCode, Barcode, X, CheckCircle, Package,
  Calculator, Percent, FolderDown, FolderOpen, AlertTriangle,
  Database, User, Globe
} from '@lucide/vue'

const props = defineProps({
  products: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
})

const toast   = useToast()
const page    = usePage()
const settings = computed(() => page.props.settings)

// ── Search & Products ─────────────────────────────
const searchQuery      = ref('')
const searchInput      = ref(null)
const selectedCategory = ref(null)
const categoryTabs = ref(null)
let categoryDragStart = null
let suppressCategoryClick = false

function startCategoryDrag(event) {
  if (event.button !== 0 || !categoryTabs.value) return

  categoryDragStart = {
    x: event.clientX,
    scrollLeft: categoryTabs.value.scrollLeft,
    moved: false,
  }
}

function dragCategoryTabs(event) {
  if (!categoryDragStart || !categoryTabs.value) return

  const distance = event.clientX - categoryDragStart.x
  if (Math.abs(distance) > 4) {
    categoryDragStart.moved = true
    categoryTabs.value.classList.add('is-dragging')
    categoryTabs.value.scrollLeft = categoryDragStart.scrollLeft - distance
  }
}

function stopCategoryDrag() {
  if (!categoryDragStart) return

  if (categoryDragStart.moved) {
    suppressCategoryClick = true
    setTimeout(() => { suppressCategoryClick = false }, 120)
  }

  categoryDragStart = null
  categoryTabs.value?.classList.remove('is-dragging')
}

function selectCategory(categoryId) {
  if (suppressCategoryClick) {
    suppressCategoryClick = false
    return
  }

  selectedCategory.value = categoryId
}

const filteredProducts = computed(() => {
  let list = props.products || []
  if (selectedCategory.value !== null) {
    list = list.filter(p => p.category_id === selectedCategory.value)
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(p => 
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.sku && p.sku.toLowerCase().includes(q)) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    )
  }
  return list
})

const searchResults = ref([])
const searching = ref(false)

function selectHighlighted() {
  if (barcodeBuffer.length > 2) return
  if (filteredProducts.value.length > 0) {
    addToCart(filteredProducts.value[0])
    searchQuery.value = ''
  }
}

// ── Barcode USB HID scanner support ──────────────
let barcodeBuffer = ''
let barcodeTimer  = null

// ── Optional camera barcode scanner ─────────────
const showCameraScanner = ref(false)
const cameraVideo = ref(null)
const cameraError = ref('')
const cameraReady = ref(false)
let cameraStream = null
let cameraControls = null
let cameraSession = 0

function stopCameraStream() {
  cameraSession++
  cameraControls?.stop()
  cameraControls = null
  cameraStream = cameraVideo.value?.srcObject || null
  cameraStream?.getTracks().forEach(track => track.stop())
  cameraStream = null
  if (cameraVideo.value) {
    cameraVideo.value.pause()
    cameraVideo.value.srcObject = null
  }
  cameraReady.value = false
}

function closeCameraScanner() {
  showCameraScanner.value = false
  stopCameraStream()
}

async function openCameraScanner() {
  showCameraScanner.value = true
  cameraError.value = ''
  cameraReady.value = false
  const session = ++cameraSession
  await nextTick()

  if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
    cameraError.value = 'Akses kamera membutuhkan HTTPS atau alamat localhost. Buka aplikasi melalui koneksi aman lalu coba lagi.'
    return
  }
  try {
    const reader = new BrowserMultiFormatReader()
    const controls = await reader.decodeFromVideoDevice(undefined, cameraVideo.value, (result) => {
      const barcode = result?.getText()?.trim()
      if (barcode && showCameraScanner.value && session === cameraSession) {
        closeCameraScanner()
        searchByBarcode(barcode)
      }
    })
    if (!showCameraScanner.value || session !== cameraSession) {
      controls.stop()
      return
    }
    cameraControls = controls
    cameraStream = cameraVideo.value?.srcObject || null
    cameraReady.value = true
  } catch (error) {
    stopCameraStream()
    if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
      cameraError.value = 'Izin kamera ditolak. Izinkan akses kamera dari ikon pengaturan situs di browser, lalu coba lagi.'
    } else if (error.name === 'NotFoundError' || error.name === 'NotFoundException') {
      cameraError.value = 'Kamera tidak ditemukan di perangkat ini.'
    } else {
      cameraError.value = 'Kamera tidak bisa dibuka. Pastikan tidak sedang dipakai aplikasi lain, lalu coba lagi.'
    }
  }
}

function handleKeydown(e) {
  // If success modal is open, intercept key events
  if (showSuccessModal.value) {
    if (e.key === 'Enter') {
      e.preventDefault()
      closeSuccessModal(true)
      return
    }
    if (e.key === 'Escape') {
      e.preventDefault()
      closeSuccessModal(false)
      return
    }
  }

  // If user presses Escape, close modals
  if (e.key === 'Escape') {
    if (showCameraScanner.value) {
      closeCameraScanner()
      return
    }
    if (showPaymentModal.value) {
      showPaymentModal.value = false
      return
    }
    if (showReceiptModal.value) {
      showReceiptModal.value = false
      return
    }
    if (showDraftSaveModal.value) {
      showDraftSaveModal.value = false
      return
    }
    if (showDraftListModal.value) {
      showDraftListModal.value = false
      return
    }
    if (showConfirmModal.value) {
      showConfirmModal.value = false
      return
    }
  }

  // If user presses F2, open the payment modal
  if (e.key === 'F2') {
    e.preventDefault()
    if (cart.value.length > 0 && !showPaymentModal.value && !showReceiptModal.value) {
      openPayment()
    }
    return
  }

  // USB HID barcode scanner types very fast (< 50ms between chars)
  // and ends with Enter key
  if (e.key === 'Enter' && barcodeBuffer.length > 2) {
    e.preventDefault()
    const barcode = barcodeBuffer
    barcodeBuffer = ''
    searchByBarcode(barcode)
    return
  }
  if (e.key.length === 1) {
    barcodeBuffer += e.key
    clearTimeout(barcodeTimer)
    barcodeTimer = setTimeout(() => { barcodeBuffer = '' }, 200)
  }
}

function searchByBarcode(barcode) {
  const match = props.products.find(p => p.barcode === barcode || p.sku === barcode)
  if (match) {
    addToCart(match)
    toast.success(`${match.name} ditambahkan`)
  } else {
    // Fallback to server search
    axios.get(route('kasir.product.search'), { params: { q: barcode } })
      .then(res => {
        if (res.data.length === 1) {
          addToCart(res.data[0])
          toast.success(`${res.data[0].name} ditambahkan`)
        } else {
          toast.warning('Barcode tidak ditemukan')
        }
      })
      .catch(() => {
        toast.warning('Barcode tidak ditemukan')
      })
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  loadDraftsFromStorage()
  setTimeout(() => {
    searchInput.value?.focus()
  }, 100)
})
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  stopCameraStream()
})

// ── Cart ──────────────────────────────────────────
const cart = ref([])

function addToCart(product) {
  const existing = cart.value.find(i => i.product_id === product.id)
  if (existing) {
    if (existing.qty >= product.stock) {
      toast.warning('Stok tidak mencukupi')
      return
    }
    existing.qty++
    existing.subtotal = (existing.price * existing.qty) - existing.discount
  } else {
    if (product.stock <= 0) {
      toast.warning('Stok habis')
      return
    }
    cart.value.push({
      product_id:   product.id,
      name:         product.name,
      sku:          product.sku,
      price:        parseFloat(product.sell_price),
      qty:          1,
      discount:     0,
      subtotal:     parseFloat(product.sell_price),
      maxStock:     product.stock,
      unit:         product.unit,
    })
  }
  searchQuery.value   = ''
  searchResults.value = []
  searchInput.value?.focus()
}

function removeFromCart(index) {
  cart.value.splice(index, 1)
}

function updateQty(index, delta) {
  const item = cart.value[index]
  const newQty = item.qty + delta
  if (newQty < 1) { removeFromCart(index); return }
  if (newQty > item.maxStock) { toast.warning('Stok tidak mencukupi'); return }
  item.qty     = newQty
  item.subtotal = (item.price * newQty) - item.discount
}

function setQty(index, val) {
  const item = cart.value[index]
  const qty  = Math.max(1, Math.min(parseInt(val) || 1, item.maxStock))
  item.qty     = qty
  item.subtotal = (item.price * qty) - item.discount
}

function clearCart() {
  cart.value = []
  selectedCustomer.value = null
}

// ── Customer / Member Search ─────────────────────
const customerSearchQuery   = ref('')
const customerSearchResults = ref([])
const customerSearching     = ref(false)
const showCustomerDropdown   = ref(false)
const selectedCustomer      = ref(null)
const showAddCustomerModal   = ref(false)
const customerNameInput     = ref(null)
let customerSearchTimer     = null

const customerForm = useForm({
  name: '',
  phone: '',
  address: '',
})

async function searchCustomers(q) {
  if (!q || q.length < 1) { customerSearchResults.value = []; return }
  customerSearching.value = true
  try {
    const res = await axios.get(route('kasir.customer.search'), { params: { q } })
    customerSearchResults.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    customerSearching.value = false
  }
}

watch(customerSearchQuery, (val) => {
  clearTimeout(customerSearchTimer)
  customerSearchTimer = setTimeout(() => searchCustomers(val), 200)
})

function selectCustomer(customer) {
  selectedCustomer.value = customer
  customerSearchQuery.value = ''
  customerSearchResults.value = []
  showCustomerDropdown.value = false
}

function handleCustomerBlur() {
  setTimeout(() => {
    showCustomerDropdown.value = false
  }, 200)
}

function openAddCustomerModal() {
  customerForm.reset()
  customerForm.clearErrors()
  showAddCustomerModal.value = true
}

async function submitAddCustomer() {
  customerForm.processing = true
  try {
    const res = await axios.post(route('kasir.customer.store'), {
      name: customerForm.name,
      phone: customerForm.phone,
      address: customerForm.address,
    })
    
    if (res.data.success) {
      selectedCustomer.value = res.data.customer
      showAddCustomerModal.value = false
      toast.success(res.data.message)
    } else {
      toast.error('Gagal menyimpan member')
    }
  } catch (e) {
    if (e.response && e.response.data && e.response.data.errors) {
      const errs = e.response.data.errors
      if (errs.phone) {
        toast.error('Nomor telepon sudah terdaftar untuk member lain!')
      } else {
        toast.error('Periksa kembali inputan member baru')
      }
    } else {
      toast.error('Terjadi kesalahan saat menambah member')
    }
  } finally {
    customerForm.processing = false
  }
}

watch(showAddCustomerModal, (isOpen) => {
  if (isOpen) {
    setTimeout(() => {
      customerNameInput.value?.focus()
    }, 50)
  }
})

// ── Draft Transactions ────────────────────────────
const draftTransactions  = ref([])
const showDraftSaveModal = ref(false)
const showDraftListModal = ref(false)
const draftLabel         = ref('')
const draftLabelInput    = ref(null)

const showConfirmModal   = ref(false)
const confirmTitle      = ref('')
const confirmMessage    = ref('')
const confirmAction     = ref(null)

function triggerConfirm(title, message, onConfirm) {
  confirmTitle.value = title
  confirmMessage.value = message
  confirmAction.value = onConfirm
  showConfirmModal.value = true
}

function executeConfirm() {
  if (confirmAction.value) {
    confirmAction.value()
  }
  showConfirmModal.value = false
}

function saveDraftsToStorage() {
  localStorage.setItem('pos_draft_transactions', JSON.stringify(draftTransactions.value))
}

function loadDraftsFromStorage() {
  const saved = localStorage.getItem('pos_draft_transactions')
  if (saved) {
    try {
      draftTransactions.value = JSON.parse(saved)
    } catch (e) {
      console.error('Gagal memuat draf', e)
    }
  }
}

function openSaveDraftModal() {
  if (cart.value.length === 0) {
    toast.warning('Keranjang belanja kosong')
    return
  }
  draftLabel.value = ''
  showDraftSaveModal.value = true
}

function confirmSaveDraft() {
  const label = draftLabel.value.trim() || `Draf #${draftTransactions.value.length + 1} (${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })})`
  
  draftTransactions.value.push({
    id: Date.now(),
    label: label,
    cart: [...cart.value],
    discountType: discountType.value,
    discountValue: discountValue.value,
    notes: notes.value,
    customer: selectedCustomer.value ? { ...selectedCustomer.value } : null,
    time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
  })
  
  saveDraftsToStorage()
  clearCart()
  discountValue.value = 0
  notes.value = ''
  selectedCustomer.value = null
  
  showDraftSaveModal.value = false
  toast.success('Transaksi berhasil disimpan sebagai draf')
}

function recallDraft(index) {
  const draft = draftTransactions.value[index]
  
  const proceedRecall = () => {
    cart.value = [...draft.cart]
    discountType.value = draft.discountType
    discountValue.value = draft.discountValue
    notes.value = draft.notes
    selectedCustomer.value = draft.customer ? { ...draft.customer } : null
    
    // Hapus dari daftar draf setelah di-recall
    draftTransactions.value.splice(index, 1)
    saveDraftsToStorage()
    
    showDraftListModal.value = false
    toast.success(`Draf "${draft.label}" berhasil dipanggil`)
    
    setTimeout(() => {
      searchInput.value?.focus()
    }, 100)
  }

  if (cart.value.length > 0) {
    triggerConfirm(
      'Keranjang Tidak Kosong',
      'Keranjang belanja saat ini terisi barang. Apakah Anda yakin ingin mengosongkannya dan memuat draf ini?',
      proceedRecall
    )
  } else {
    proceedRecall()
  }
}

function deleteDraft(index) {
  const label = draftTransactions.value[index].label
  triggerConfirm(
    'Hapus Draf Transaksi',
    `Apakah Anda yakin ingin menghapus draf "${label}" secara permanen?`,
    () => {
      draftTransactions.value.splice(index, 1)
      saveDraftsToStorage()
      toast.success(`Draf "${label}" dihapus`)
    }
  )
}

function getDraftTotal(draft) {
  const sub = draft.cart.reduce((sum, item) => sum + item.subtotal, 0)
  const disc = draft.discountType === 'percent' 
    ? sub * (draft.discountValue / 100) 
    : Math.min(draft.discountValue, sub)
  const taxPercent = parseFloat(settings.value?.tax_percentage ?? 0)
  const tax = (sub - disc) * (taxPercent / 100)
  return sub - disc + tax
}

watch(showDraftSaveModal, (isOpen) => {
  if (isOpen) {
    setTimeout(() => {
      draftLabelInput.value?.focus()
    }, 50)
  }
})

// ── Pricing ───────────────────────────────────────
const discountType  = ref('nominal') // 'nominal' | 'percent'
const discountValue = ref(0)
const taxPercent    = computed(() => parseFloat(settings.value?.tax_percentage ?? 0))
const paymentMethod = ref('cash')
const paidAmount    = ref(0)
const formattedPaidAmount = computed({
  get() {
    if (paidAmount.value === 0) return ''
    return new Intl.NumberFormat('id-ID').format(paidAmount.value)
  },
  set(val) {
    const cleaned = val.replace(/\D/g, '')
    paidAmount.value = cleaned ? parseInt(cleaned, 10) : 0
  }
})
const notes         = ref('')

const subtotal = computed(() => cart.value.reduce((s, i) => s + i.subtotal, 0))

const discountAmount = computed(() => {
  let val = parseFloat(discountValue.value) || 0
  if (val < 0) val = 0
  if (discountType.value === 'percent') {
    val = Math.min(val, 100)
    return subtotal.value * (val / 100)
  }
  return Math.min(val, subtotal.value)
})

const taxAmount = computed(() => (subtotal.value - discountAmount.value) * (taxPercent.value / 100))
const total     = computed(() => subtotal.value - discountAmount.value + taxAmount.value)
const change    = computed(() => paidAmount.value - total.value)

// Quick cash buttons
const quickCash = computed(() => {
  const t = total.value
  const candidates = [
    Math.ceil(t / 1000) * 1000,
    Math.ceil(t / 5000) * 5000,
    Math.ceil(t / 10000) * 10000,
    Math.ceil(t / 50000) * 50000,
  ]
  return [...new Set(candidates)].slice(0, 4)
})

// ── Payment Modal ─────────────────────────────────
const showPaymentModal = ref(false)
const showSuccessModal = ref(false)

function playSuccessSound() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    
    // First oscillator for a sweet high coin tone
    const osc1 = ctx.createOscillator()
    const gain1 = ctx.createGain()
    osc1.type = 'sine'
    osc1.frequency.setValueAtTime(880, ctx.currentTime) // A5
    osc1.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.1)
    
    gain1.gain.setValueAtTime(0, ctx.currentTime)
    gain1.gain.linearRampToValueAtTime(0.25, ctx.currentTime + 0.02)
    gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3)
    
    osc1.connect(gain1)
    gain1.connect(ctx.destination)
    osc1.start(ctx.currentTime)
    osc1.stop(ctx.currentTime + 0.3)
    
    // Second oscillator for a classic coin chiming ringing tone
    const osc2 = ctx.createOscillator()
    const gain2 = ctx.createGain()
    osc2.type = 'triangle'
    osc2.frequency.setValueAtTime(1320, ctx.currentTime + 0.08) // E6
    osc2.frequency.setValueAtTime(2640, ctx.currentTime + 0.15)
    
    gain2.gain.setValueAtTime(0, ctx.currentTime + 0.08)
    gain2.gain.linearRampToValueAtTime(0.2, ctx.currentTime + 0.1)
    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.45)
    
    osc2.connect(gain2)
    gain2.connect(ctx.destination)
    osc2.start(ctx.currentTime + 0.08)
    osc2.stop(ctx.currentTime + 0.45)
  } catch (e) {
    console.error("Gagal memainkan suara", e)
  }
}

function closeSuccessModal(openReceipt = false) {
  showSuccessModal.value = false
  if (openReceipt) {
    showReceiptModal.value = true
  } else {
    setTimeout(() => {
      searchInput.value?.focus()
    }, 50)
  }
}

function openPayment() {
  if (cart.value.length === 0) { toast.warning('Keranjang masih kosong'); return }
  paidAmount.value = total.value
  showPaymentModal.value = true
}

// ── Submit Transaction ────────────────────────────
const processing = ref(false)

async function submitTransaction() {
  if (paidAmount.value < total.value) {
    toast.error('Uang bayar kurang dari total')
    return
  }
  processing.value = true
  try {
    const response = await axios.post(route('kasir.store'), {
      items: cart.value.map(i => ({
        product_id: i.product_id,
        qty:        i.qty,
        price:      i.price,
        discount:   i.discount,
      })),
      discount:       discountAmount.value,
      tax:            taxAmount.value,
      paid_amount:    paidAmount.value,
      payment_method: paymentMethod.value,
      notes:          notes.value,
      customer_id:    selectedCustomer.value?.id ?? null,
    })

    // Tampilkan struk
    const saved = response.data.transaction
    lastTransaction.value = {
      invoice: saved.invoice,
      items:   saved.items,
      subtotal: Number(saved.subtotal),
      discount: Number(saved.discount),
      tax:      Number(saved.tax),
      total:    Number(saved.total),
      paid:     Number(saved.paid),
      change:   Number(saved.change),
      method:   saved.method,
      time:     new Date(saved.created_at).toLocaleString('id-ID'),
      customer: selectedCustomer.value ? { ...selectedCustomer.value } : null,
    }
    showPaymentModal.value = false
    showSuccessModal.value = true
    playSuccessSound()

    cart.value      = []
    discountValue.value = 0
    paidAmount.value    = 0
    notes.value         = ''
    selectedCustomer.value = null

    toast.success('Transaksi berhasil!')
    router.reload({ only: ['products'], preserveScroll: true })
  } catch (e) {
    toast.error('Gagal menyimpan transaksi')
    console.error(e)
  } finally {
    processing.value = false
  }
}

// ── Receipt ───────────────────────────────────────
const showReceiptModal = ref(false)
const lastTransaction  = ref(null)

function printReceipt() {
  window.print()
}

// ── Focus & Watchers ──────────────────────────────
const paidAmountInput = ref(null)

watch(showPaymentModal, (isOpen) => {
  if (isOpen) {
    setTimeout(() => {
      paidAmountInput.value?.focus()
      paidAmountInput.value?.select()
    }, 50)
  } else {
    setTimeout(() => {
      searchInput.value?.focus()
    }, 50)
  }
})

watch(showReceiptModal, (isOpen) => {
  if (!isOpen) {
    setTimeout(() => {
      searchInput.value?.focus()
    }, 50)
  }
})

// ── Format ────────────────────────────────────────
function formatCurrency(val) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val ?? 0)
}

// ── Barcode Renderer ──────────────────────────────
function renderBarcode(el, value) {
  if (!el || !value) return
  try {
    JsBarcode(el, value, {
      format: 'CODE128',
      width: 1.5,
      height: 22,
      displayValue: false,
      margin: 0,
      background: 'transparent',
      lineColor: '#334155',
    })
  } catch (e) {
    // Fallback: hide if barcode format invalid
    el.style.display = 'none'
  }
}
</script>

<template>
  <AppLayout>
    <template #title>Kasir</template>

    <div class="pos-layout no-print">

      <!-- LEFT: Visual Catalog & Search -->
      <div class="pos-left">

        <!-- Search Bar -->
        <div class="search-area">
          <div class="flex items-center gap-2">
            <div class="search-wrap flex-1">
              <Search :size="18" class="search-icon" />
              <input
                ref="searchInput"
                v-model="searchQuery"
                type="text"
                placeholder="Cari nama produk, SKU, barcode, atau scan..."
                class="pos-search"
                autocomplete="off"
                @keydown.enter.prevent="selectHighlighted"
              />
              <button v-if="searchQuery" @click="searchQuery = ''" class="search-clear">
                <X :size="16" />
              </button>
            </div>

            <button
              type="button"
              class="camera-scan-trigger"
              @click="openCameraScanner"
              title="Scan barcode dengan kamera"
            >
              <Camera :size="16" />
              <span>Kamera</span>
            </button>

            <!-- Draft Queue Button -->
            <button
              v-if="draftTransactions.length > 0"
              @click="showDraftListModal = true"
              class="draft-list-trigger"
              type="button"
            >
              <FolderOpen :size="16" />
              <span>Draf ({{ draftTransactions.length }})</span>
            </button>
          </div>
        </div>

        <!-- Catalog Container -->
        <div class="catalog-container">
          <!-- Category Tabs -->
          <div
            ref="categoryTabs"
            class="category-tabs"
            @mousedown="startCategoryDrag"
            @mousemove="dragCategoryTabs"
            @mouseup="stopCategoryDrag"
            @mouseleave="stopCategoryDrag"
            @wheel.prevent="categoryTabs.scrollLeft += $event.deltaY || $event.deltaX"
          >
            <button
              type="button"
              class="category-tab"
              :class="{ 'category-tab--active': selectedCategory === null }"
              @click="selectCategory(null)"
            >
              Semua Produk
            </button>
            <button
              v-for="cat in categories"
              :key="cat.id"
              type="button"
              class="category-tab"
              :class="{ 'category-tab--active': selectedCategory === cat.id }"
              @click="selectCategory(cat.id)"
            >
              {{ cat.name }}
            </button>
          </div>

          <!-- Product Grid -->
          <div v-if="filteredProducts.length === 0" class="flex flex-col items-center justify-center flex-1 py-16 text-slate-350 dark:text-slate-550 text-center gap-3 bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700">
            <Package :size="48" class="opacity-50" />
            <div>
              <p class="text-sm font-bold">Produk tidak ditemukan</p>
              <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau kategori Anda.</p>
            </div>
          </div>

          <div v-else class="catalog-grid">
            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="product-card"
              @click="addToCart(product)"
            >
              <!-- Card Image / Placeholder -->
              <img
                v-if="product.image"
                :src="'/storage/' + product.image"
                :alt="product.name"
                class="product-card__image"
              />
              <div v-else class="product-card__image-placeholder">
                <Package :size="24" />
              </div>

              <!-- Card Content -->
              <div class="flex-1 flex flex-col gap-0.5">
                <p class="product-card__name" :title="product.name">{{ product.name }}</p>
                <p class="product-card__sku text-[10px] text-slate-400">{{ product.sku || '-' }}</p>
                <svg v-if="product.barcode" :ref="el => renderBarcode(el, product.barcode)" class="product-card__barcode"></svg>
              </div>

              <!-- Price & Stock -->
              <div class="product-card__meta">
                <span class="product-card__price">{{ formatCurrency(product.sell_price) }}</span>
                <span
                  class="product-card__stock text-[10px] font-bold"
                  :class="product.stock <= 5 ? 'product-card__stock--warning' : 'product-card__stock--ok'"
                >
                  {{ product.stock }} {{ product.unit }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Summary, Cart List + Checkout -->
      <div class="pos-right">
        
        <!-- Cart Items Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-3.5 shadow-sm flex flex-col gap-3 flex-1 min-h-[80px] overflow-hidden">
          <h4 class="text-xs font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between">
            <span class="flex items-center gap-2">
              <ShoppingCart :size="16" class="text-primary-500" />
              Keranjang Belanja
            </span>
            <div class="flex items-center gap-1.5">
              <button
                v-if="cart.length > 0"
                @click="openSaveDraftModal"
                type="button"
                class="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-primary-500 rounded-lg transition"
                title="Simpan Draf"
              >
                <FolderDown :size="14" />
              </button>
              <button
                v-if="cart.length > 0"
                @click="clearCart"
                type="button"
                class="p-1 hover:bg-red-50 dark:hover:bg-red-950/30 text-slate-400 hover:text-red-500 rounded-lg transition"
                title="Kosongkan Keranjang"
              >
                <Trash2 :size="14" />
              </button>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-900 text-slate-650 dark:text-slate-350">
                {{ cart.reduce((sum, item) => sum + item.qty, 0) }} item
              </span>
            </div>
          </h4>

          <!-- Cart Area List -->
          <div class="overflow-y-auto pr-1 flex-1 min-h-[60px]">
            <div v-if="cart.length === 0" class="flex flex-col items-center justify-center py-8 text-slate-350 dark:text-slate-550 text-center gap-2">
              <ShoppingCart :size="32" class="opacity-50" />
              <p class="text-xs font-semibold">Keranjang masih kosong</p>
              <p class="text-[10px] text-slate-400">Pilih produk di katalog atau scan barcode</p>
            </div>

            <div v-else class="space-y-3">
              <div v-for="(item, i) in cart" :key="item.product_id" class="flex items-start justify-between gap-3 pb-3 border-b border-slate-50 dark:border-slate-750/30 last:border-0 last:pb-0">
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.name }}</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">{{ item.sku || '-' }}</p>
                  <div class="flex items-center gap-1.5 mt-1.5">
                    <span class="text-xxs font-extrabold text-slate-700 dark:text-slate-350">{{ formatCurrency(item.price) }}</span>
                  </div>
                </div>
                
                <div class="flex flex-col items-end gap-1.5 shrink-0">
                  <!-- Qty Controls -->
                  <div class="flex items-center border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden h-7 bg-white dark:bg-slate-900">
                    <button type="button" @click="updateQty(i, -1)" class="w-7 h-full flex items-center justify-center hover:bg-slate-50 dark:hover:bg-slate-800 transition text-slate-500">
                      <Minus :size="10" />
                    </button>
                    <input
                      :value="item.qty"
                      @change="setQty(i, $event.target.value)"
                      type="number" min="1"
                      class="w-10 h-full text-center text-xs font-extrabold border-x border-slate-200 dark:border-slate-700 bg-transparent text-slate-800 dark:text-slate-200 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                    />
                    <button type="button" @click="updateQty(i, 1)" class="w-7 h-full flex items-center justify-center hover:bg-slate-50 dark:hover:bg-slate-800 transition text-slate-500">
                      <Plus :size="10" />
                    </button>
                  </div>
                  <span class="text-xs font-extrabold text-slate-900 dark:text-white">{{ formatCurrency(item.subtotal) }}</span>
                </div>

                <button type="button" @click="removeFromCart(i)" class="p-1 hover:bg-red-50 dark:hover:bg-red-950/30 text-red-500 rounded-lg transition shrink-0 mt-0.5">
                  <Trash2 :size="13" />
                </button>
              </div>
            </div>
          </div>
          
          <!-- Discount Row inside Cart Card -->
          <div v-if="cart.length > 0" class="pt-3 border-t border-slate-100 dark:border-slate-755/50 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider">Diskon Transaksi:</span>
            <div class="flex gap-1.5 items-center">
              <select v-model="discountType" class="text-xs font-semibold px-1.5 py-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 outline-none">
                <option value="nominal">Rp</option>
                <option value="percent">%</option>
              </select>
              <input
                v-model="discountValue"
                type="number" min="0"
                :max="discountType === 'percent' ? 100 : subtotal"
                class="w-20 px-2 py-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-right text-slate-800 dark:text-slate-200 outline-none"
                placeholder="0"
              />
            </div>
          </div>
        </div>

        <!-- Customer Section Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-3 shadow-sm flex flex-col gap-2 shrink-0">
          <h4 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-2">
            <User :size="16" class="text-primary-500" />
            Pelanggan / Member
          </h4>
          
          <!-- Selected Customer -->
          <div v-if="selectedCustomer" class="flex items-center justify-between p-2.5 bg-primary-50/50 dark:bg-primary-900/20 rounded-xl border border-primary-100/50 dark:border-primary-900/30">
            <div class="min-w-0">
              <p class="font-bold text-slate-850 dark:text-slate-250 text-sm truncate">{{ selectedCustomer.name }}</p>
              <p class="text-[10px] text-slate-500 dark:text-slate-450 font-bold mt-0.5">
                {{ selectedCustomer.phone || '-' }} · Poin: <span class="text-primary-600 dark:text-primary-400 font-extrabold">{{ selectedCustomer.points }}</span>
              </p>
            </div>
            <button @click="selectedCustomer = null" class="p-1 hover:bg-red-50 dark:hover:bg-red-950/30 text-red-500 rounded-lg transition" type="button" title="Lepas Pelanggan">
              <X :size="16" />
            </button>
          </div>

          <!-- Select/Search Customer -->
          <div v-else class="flex gap-2 relative">
            <div class="relative flex-1">
              <input
                v-model="customerSearchQuery"
                type="text"
                class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                placeholder="Cari member (nama / HP)..."
                @focus="showCustomerDropdown = true"
                @blur="handleCustomerBlur"
              />
              <Search :size="12" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              
              <!-- Customer Dropdown List -->
              <div v-if="showCustomerDropdown && (customerSearchResults.length > 0 || customerSearching)" class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg max-h-48 overflow-y-auto z-50">
                <div v-if="customerSearching" class="p-3 text-xs text-center text-slate-400">Mencari...</div>
                <div
                  v-for="c in customerSearchResults"
                  :key="c.id"
                  @mousedown="selectCustomer(c)"
                  class="p-2.5 hover:bg-slate-50 dark:hover:bg-slate-900 text-xs cursor-pointer border-b border-slate-100 dark:border-slate-700/50 last:border-0"
                >
                  <p class="font-bold text-slate-800 dark:text-slate-200">{{ c.name }}</p>
                  <p class="text-slate-450 dark:text-slate-500 text-[10px]">{{ c.phone || '-' }}</p>
                </div>
              </div>
            </div>
            
            <button
              @click="openAddCustomerModal"
              class="px-3 py-2 bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 text-xs font-bold rounded-xl border border-primary-100 dark:border-primary-900/30 transition flex items-center justify-center whitespace-nowrap"
              type="button"
            >
              + Baru
            </button>
          </div>
        </div>

        <div class="summary-card shrink-0">
          <h3 class="summary-title">Ringkasan</h3>

          <div class="summary-rows">
            <div class="summary-row">
              <span>Subtotal</span>
              <span>{{ formatCurrency(subtotal) }}</span>
            </div>
            <div v-if="discountAmount > 0" class="summary-row summary-row--discount">
              <span>Diskon</span>
              <span>- {{ formatCurrency(discountAmount) }}</span>
            </div>
            <div v-if="taxPercent > 0" class="summary-row">
              <span>Pajak ({{ taxPercent }}%)</span>
              <span>{{ formatCurrency(taxAmount) }}</span>
            </div>
          </div>

          <div class="summary-total">
            <span>TOTAL</span>
            <span class="total-amount">{{ formatCurrency(total) }}</span>
          </div>

          <!-- Payment Method -->
          <div class="payment-methods">
            <button
              v-for="m in [{id:'cash', label:'Tunai', icon: Wallet}, {id:'transfer', label:'Transfer', icon: CreditCard}, {id:'qris', label:'QRIS', icon: QrCode}]"
              :key="m.id"
              class="payment-method-btn"
              :class="{ active: paymentMethod === m.id }"
              @click="paymentMethod = m.id"
            >
              <component :is="m.icon" :size="18" />
              {{ m.label }}
            </button>
          </div>

          <!-- Notes -->
          <input
            v-model="notes"
            type="text"
            class="pos-notes"
            placeholder="Catatan (opsional)..."
          />

          <!-- Bayar Button -->
          <button
            class="pay-btn"
            :disabled="cart.length === 0"
            @click="openPayment"
          >
            <Calculator :size="20" />
            Proses Pembayaran [F2]
          </button>
        </div>
      </div>
    </div>

    <!-- ── Payment Modal ── -->
    <Teleport to="body">
      <div v-if="showPaymentModal" class="modal-overlay" @click.self="showPaymentModal = false">
        <div class="modal payment-modal">
          <div class="modal__header">
            <h3>Konfirmasi Pembayaran</h3>
            <button @click="showPaymentModal = false" class="modal__close"><X :size="16" /></button>
          </div>
          <div class="modal__body">
            <!-- Item List -->
            <div class="payment-items">
              <h4 class="payment-items__title">Daftar Barang ({{ cart.length }} item)</h4>
              <div class="payment-items__list">
                <div v-for="(item, idx) in cart" :key="idx" class="payment-items__row">
                  <span class="payment-items__name"><b>{{ item.qty }}×</b> {{ item.name }}</span>
                  <span class="payment-items__subtotal">{{ formatCurrency(item.subtotal) }}</span>
                </div>
              </div>
            </div>

            <div class="payment-total">
              <span>Total yang harus dibayar</span>
              <strong>{{ formatCurrency(total) }}</strong>
            </div>

            <div class="form-group">
              <label class="label">Uang Diterima</label>
              <input
                ref="paidAmountInput"
                v-model="formattedPaidAmount"
                type="text"
                inputmode="numeric"
                class="input text-xl font-bold"
                @keydown.enter.prevent="submitTransaction"
              />
            </div>

            <!-- Quick cash (only for cash payment) -->
            <div v-if="paymentMethod === 'cash'" class="quick-cash">
              <button
                v-for="amount in quickCash"
                :key="amount"
                class="quick-cash-btn"
                @click="paidAmount = amount"
              >
                {{ formatCurrency(amount) }}
              </button>
              <button class="quick-cash-btn" @click="paidAmount = total">Pas</button>
            </div>

            <!-- Change -->
            <div class="change-display" :class="{ 'change-display--ok': change >= 0, 'change-display--err': change < 0 }">
              <span>{{ change >= 0 ? 'Kembalian' : 'Kurang' }}</span>
              <strong>{{ formatCurrency(Math.abs(change)) }}</strong>
            </div>
          </div>
          <div class="modal__footer">
            <button @click="showPaymentModal = false" class="btn-secondary">Batal</button>
            <button
              @click="submitTransaction"
              class="pay-btn-confirm"
              :disabled="processing || change < 0"
            >
              <CheckCircle :size="18" />
              {{ processing ? 'Memproses...' : 'Konfirmasi & Bayar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Success Notification Modal ── -->
    <Teleport to="body">
      <div v-if="showSuccessModal" class="modal-overlay" @click.self="closeSuccessModal(false)">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl w-96 shadow-2xl p-6 text-center space-y-5 modal-bounce">
          <!-- Success Checked Icon -->
          <div class="mx-auto w-16 h-16 rounded-full bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center animate-bounce">
            <CheckCircle :size="36" />
          </div>
          
          <!-- Title & brief text -->
          <div class="space-y-1.5">
            <h3 class="text-xl font-black text-slate-900 dark:text-white">Transaksi Berhasil!</h3>
            <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 tracking-wider uppercase">
              No. Invoice: <span class="text-primary-600 dark:text-primary-400 font-bold">{{ lastTransaction?.invoice }}</span>
            </p>
          </div>

          <!-- Transaction details card -->
          <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-4 space-y-2.5 text-left border border-slate-100 dark:border-slate-800 text-sm">
            <div v-if="lastTransaction?.customer" class="flex justify-between">
              <span class="text-slate-500">Member</span>
              <span class="font-bold text-primary-600 dark:text-primary-400">{{ lastTransaction.customer.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Total Belanja</span>
              <span class="font-semibold text-slate-800 dark:text-slate-200">{{ formatCurrency(lastTransaction?.total) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Uang Diterima</span>
              <span class="font-semibold text-slate-800 dark:text-slate-200">{{ formatCurrency(lastTransaction?.paid) }}</span>
            </div>
            <div class="flex justify-between border-t border-dashed border-slate-200 dark:border-slate-700 pt-2 font-bold text-base">
              <span class="text-slate-800 dark:text-slate-250">Kembalian</span>
              <span class="text-emerald-600 dark:text-emerald-400">{{ formatCurrency(lastTransaction?.change) }}</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="grid grid-cols-2 gap-3 pt-1">
            <button 
              type="button" 
              @click="closeSuccessModal(false)" 
              class="w-full py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-bold rounded-xl transition duration-150 cursor-pointer active:scale-95"
            >
              Transaksi Baru [Esc]
            </button>
            <button 
              type="button" 
              @click="closeSuccessModal(true)"
              class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition duration-150 shadow-md shadow-emerald-600/10 cursor-pointer active:scale-95"
            >
              Lihat Struk [Enter]
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Receipt Modal ── -->
    <Teleport to="body">
      <div v-if="showReceiptModal" class="modal-overlay" @click.self="showReceiptModal = false">
        <div class="modal receipt-modal">
          <div class="modal__header no-print">
            <h3>Transaksi Berhasil</h3>
            <button @click="showReceiptModal = false" class="modal__close"><X :size="16" /></button>
          </div>
          <div :class="['receipt', settings?.receipt_paper_width === '80mm' ? 'receipt-80mm' : 'receipt-58mm']" v-if="lastTransaction">
            <!-- Receipt content -->
            <div class="receipt__header">
              <img v-if="settings?.store_logo" :src="`/storage/${settings.store_logo}`" :alt="`Logo ${settings?.store_name ?? 'Toko'}`" class="receipt__logo" />
              <h2>{{ settings?.store_name ?? 'Toko' }}</h2>
              <p>{{ settings?.store_address }}</p>
              <p>{{ settings?.store_phone }}</p>
            </div>
            <div class="receipt__divider">--------------------------------</div>
            <div class="receipt__meta">
              <div class="receipt__meta-row">
                <span>Invoice</span><span>{{ lastTransaction.invoice }}</span>
              </div>
              <div class="receipt__meta-row">
                <span>Waktu</span><span>{{ lastTransaction.time }}</span>
              </div>
              <div class="receipt__meta-row">
                <span>Metode</span><span class="capitalize">{{ lastTransaction.method }}</span>
              </div>
              <div v-if="lastTransaction.customer" class="receipt__meta-row">
                <span>Member</span><span>{{ lastTransaction.customer.name }}</span>
              </div>
              <div v-if="lastTransaction.customer" class="receipt__meta-row">
                <span>Poin Baru</span><span>+{{ Math.floor(lastTransaction.total / 10000) }} Poin</span>
              </div>
            </div>
            <div class="receipt__divider">--------------------------------</div>
            <div class="receipt__items">
              <div v-for="item in lastTransaction.items" :key="item.product_id" class="receipt__item">
                <div class="receipt__item-name">{{ item.name }}</div>
                <div class="receipt__item-row">
                  <span>{{ item.qty }} x {{ formatCurrency(item.price) }}</span>
                  <span>{{ formatCurrency(item.subtotal) }}</span>
                </div>
              </div>
            </div>
            <div class="receipt__divider">--------------------------------</div>
            <div class="receipt__totals">
              <div class="receipt__total-row"><span>Subtotal</span><span>{{ formatCurrency(lastTransaction.subtotal) }}</span></div>
              <div v-if="lastTransaction.discount > 0" class="receipt__total-row"><span>Diskon</span><span>- {{ formatCurrency(lastTransaction.discount) }}</span></div>
              <div v-if="lastTransaction.tax > 0" class="receipt__total-row"><span>Pajak</span><span>{{ formatCurrency(lastTransaction.tax) }}</span></div>
              <div class="receipt__total-row receipt__total-row--bold"><span>TOTAL</span><span>{{ formatCurrency(lastTransaction.total) }}</span></div>
              <div class="receipt__total-row"><span>Bayar</span><span>{{ formatCurrency(lastTransaction.paid) }}</span></div>
              <div class="receipt__total-row receipt__total-row--change"><span>Kembali</span><span>{{ formatCurrency(lastTransaction.change) }}</span></div>
            </div>
            <div class="receipt__divider">--------------------------------</div>
            <div class="receipt__footer">
              <p>{{ settings?.receipt_header ?? 'Terima kasih!' }}</p>
              <p class="text-xs">{{ settings?.receipt_footer }}</p>
              <div v-if="settings?.instagram || settings?.website" class="receipt__socials" :class="{ 'receipt__socials--single': !(settings?.instagram && settings?.website) }">
                <p v-if="settings?.instagram" class="receipt__social">
                  <svg class="receipt__social-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor" stroke="none"/></svg>
                  <span>{{ settings.instagram }}</span>
                </p>
                <p v-if="settings?.website" class="receipt__social">
                  <Globe class="receipt__social-icon" aria-hidden="true" />
                  <span>{{ settings.website }}</span>
                </p>
              </div>
            </div>
          </div>
          <div class="modal__footer no-print">
            <button @click="showReceiptModal = false" class="btn-secondary">Tutup</button>
            <button @click="printReceipt" class="btn-primary">
              🖨️ Print Struk
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Add Customer Modal ── -->
    <Teleport to="body">
      <div v-if="showAddCustomerModal" class="modal-overlay" @click.self="showAddCustomerModal = false">
        <div class="modal" style="max-width: 380px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
          <div class="modal__header">
            <h3>Tambah Member Baru</h3>
            <button @click="showAddCustomerModal = false" class="modal__close"><X :size="16" /></button>
          </div>
          <form @submit.prevent="submitAddCustomer">
            <div class="modal__body">
              <div class="form-group">
                <label class="label">Nama Lengkap</label>
                <input
                  ref="customerNameInput"
                  v-model="customerForm.name"
                  type="text"
                  class="input"
                  placeholder="Masukkan nama member"
                  required
                />
              </div>
              <div class="form-group">
                <label class="label">No. Telepon / HP</label>
                <input
                  v-model="customerForm.phone"
                  type="text"
                  class="input"
                  placeholder="Contoh: 081234567890"
                />
              </div>
              <div class="form-group">
                <label class="label">Alamat (Opsional)</label>
                <textarea
                  v-model="customerForm.address"
                  class="pos-notes"
                  placeholder="Masukkan alamat lengkap..."
                  rows="2"
                  style="resize: none;"
                />
              </div>
            </div>
            <div class="modal__footer">
              <button type="button" @click="showAddCustomerModal = false" class="btn-secondary">Batal</button>
              <button type="submit" class="btn-primary" :disabled="customerForm.processing" style="background: linear-gradient(135deg, #526d8a, #435a74); border: none;">
                {{ customerForm.processing ? 'Menyimpan...' : 'Simpan Member' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Save Draft Modal ── -->
    <Teleport to="body">
      <div v-if="showCameraScanner" class="modal-overlay camera-scan-overlay" @click.self="closeCameraScanner">
        <div class="modal camera-scan-modal" role="dialog" aria-modal="true" aria-labelledby="camera-scan-title">
          <div class="modal__header">
            <h3 id="camera-scan-title">Scan Barcode dengan Kamera</h3>
            <button type="button" @click="closeCameraScanner" class="modal__close" aria-label="Tutup kamera"><X :size="16" /></button>
          </div>
          <div class="modal__body camera-scan-body">
            <div v-if="!cameraError" class="camera-preview">
              <video ref="cameraVideo" autoplay muted playsinline aria-label="Tampilan kamera untuk scan barcode"></video>
              <div v-if="cameraReady" class="camera-scan-frame" aria-hidden="true"></div>
              <div v-else class="camera-loading">Meminta akses kamera…</div>
            </div>
            <p v-if="cameraError" class="camera-scan-error" role="alert">{{ cameraError }}</p>
            <p v-else class="camera-scan-hint">Arahkan barcode produk ke dalam bingkai. Produk akan otomatis masuk ke keranjang saat terbaca.</p>
          </div>
          <div class="modal__footer">
            <button type="button" @click="closeCameraScanner" class="btn-secondary">Tutup Kamera</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Save Draft Modal ── -->
    <Teleport to="body">
      <div v-if="showDraftSaveModal" class="modal-overlay" @click.self="showDraftSaveModal = false">
        <div class="modal" style="max-width: 380px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
          <div class="modal__header">
            <h3>Simpan Draf Transaksi</h3>
            <button @click="showDraftSaveModal = false" class="modal__close"><X :size="16" /></button>
          </div>
          <div class="modal__body">
            <p class="text-xs text-slate-500 mb-2">
              Beri nama atau label pengenal pada draf ini agar mudah diidentifikasi saat dipanggil kembali.
            </p>
            <div class="form-group">
              <label class="label">Nama / Label Pengenal (Opsional)</label>
              <input
                ref="draftLabelInput"
                v-model="draftLabel"
                type="text"
                class="input"
                placeholder="Contoh: Pelanggan Jaket Hitam, Budi, dll."
                maxlength="50"
                @keydown.enter.prevent="confirmSaveDraft"
              />
            </div>
          </div>
          <div class="modal__footer">
            <button @click="showDraftSaveModal = false" class="btn-secondary">Batal</button>
            <button @click="confirmSaveDraft" class="btn-primary" style="background: linear-gradient(135deg, #526d8a, #435a74); border: none;">
              <FolderDown :size="16" />
              Simpan Draf
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Draft List Modal ── -->
    <Teleport to="body">
      <div v-if="showDraftListModal" class="modal-overlay" @click.self="showDraftListModal = false">
        <div class="modal" style="max-width: 600px; width: 90%; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
          <div class="modal__header">
            <h3>Daftar Draf Transaksi</h3>
            <button @click="showDraftListModal = false" class="modal__close"><X :size="16" /></button>
          </div>
          <div class="modal__body">
            <div v-if="draftTransactions.length === 0" class="text-center py-8 text-slate-400">
              Tidak ada draf transaksi tersimpan.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-left" style="border-collapse: collapse;">
                <thead>
                  <tr class="border-b border-slate-100 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="pb-3 text-left">Label Pengenal</th>
                    <th class="pb-3 text-left">Waktu</th>
                    <th class="pb-3 text-center">Item</th>
                    <th class="pb-3 text-right">Total</th>
                    <th class="pb-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(draft, idx) in draftTransactions" :key="draft.id" class="text-sm hover:bg-slate-50/50">
                    <td class="py-3 font-semibold text-slate-700 dark:text-slate-200">{{ draft.label }}</td>
                    <td class="py-3 text-xs text-slate-400">{{ draft.time }}</td>
                    <td class="py-3 text-center text-slate-600 dark:text-slate-400">
                      {{ draft.cart.reduce((sum, item) => sum + item.qty, 0) }} pcs
                    </td>
                    <td class="py-3 text-right font-extrabold text-slate-900 dark:text-white">
                      {{ formatCurrency(getDraftTotal(draft)) }}
                    </td>
                    <td class="py-3 text-right space-x-2 whitespace-nowrap">
                      <button
                        @click="recallDraft(idx)"
                        class="px-3 py-1.5 bg-primary-600 text-white rounded-lg text-xs font-bold hover:bg-primary-700 transition"
                      >
                        Panggil
                      </button>
                      <button
                        @click="deleteDraft(idx)"
                        class="px-2.5 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-bold hover:bg-red-100 transition inline-flex items-center justify-center"
                      >
                        <Trash2 :size="12" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal__footer">
            <button @click="showDraftListModal = false" class="btn-secondary">Tutup</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Custom Confirmation Modal ── -->
    <Teleport to="body">
      <div v-if="showConfirmModal" class="modal-overlay" style="z-index: 99999;" @click.self="showConfirmModal = false">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl w-96 shadow-2xl p-6 text-center space-y-5 modal-bounce">
          <div class="mx-auto w-14 h-14 rounded-full bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 flex items-center justify-center">
            <AlertTriangle :size="28" />
          </div>
          
          <div class="space-y-2">
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ confirmTitle }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
              {{ confirmMessage }}
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-2">
            <button 
              type="button" 
              @click="showConfirmModal = false" 
              class="w-full py-2.5 px-4 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-bold rounded-xl transition duration-150 cursor-pointer active:scale-95"
            >
              Batal
            </button>
            <button 
              type="button" 
              @click="executeConfirm"
              class="w-full py-2.5 px-4 bg-red-650 hover:bg-red-755 text-white text-sm font-bold rounded-xl transition duration-150 shadow-md shadow-red-600/10 cursor-pointer active:scale-95"
              style="background-color: #dc2626;"
            >
              Ya, Lanjutkan
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
:global(.page-content) {
  padding: 12px !important;
}
.pos-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  height: calc(100vh - 88px);
  overflow: hidden;
}
@media (max-width: 1024px) {
  .pos-layout { grid-template-columns: 1fr; height: auto; overflow: visible; }
}

/* Keep the POS page scrollable when a phone browser uses a desktop-width viewport. */
@media (hover: none) and (pointer: coarse) {
  .pos-layout {
    grid-template-columns: minmax(0, 1fr);
    height: auto;
    min-height: calc(100dvh - 88px);
    overflow: visible;
  }
  .pos-left,
  .catalog-container,
  .catalog-grid,
  .pos-right {
    height: auto;
    min-height: 0;
    overflow: visible;
  }
  .catalog-container,
  .catalog-grid { flex: none; }
}

/* ── Left ── */
.pos-left {
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: hidden;
}

/* Catalog Container */
.catalog-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
  overflow: hidden;
}

/* Category Tabs */
.category-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  scrollbar-width: thin;
  flex-shrink: 0;
  cursor: grab;
  user-select: none;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-x: contain;
}
.category-tabs.is-dragging {
  cursor: grabbing;
  scroll-behavior: auto;
}
.category-tabs::-webkit-scrollbar {
  height: 4px;
}
.category-tabs::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.dark .category-tabs::-webkit-scrollbar-thumb {
  background: #475569;
}

.category-tab {
  flex: 0 0 auto;
  padding: 6px 12px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid transparent;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}
.dark .category-tab {
  background: #1e293b;
  color: #94a3b8;
  border-color: #334155;
}
.category-tab:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.dark .category-tab:hover {
  background: #334155;
  color: #f1f5f9;
}
.category-tab--active {
  background: #526d8a;
  color: white;
  border-color: #526d8a;
}
.dark .category-tab--active {
  background: #526d8a;
  color: white;
  border-color: #526d8a;
}
.category-tab--active:hover {
  background: #435a74;
  color: white;
}
.dark .category-tab--active:hover {
  background: #526d8a;
  color: white;
}

/* Catalog Grid */
.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  grid-auto-rows: max-content;
  gap: 8px;
  overflow-y: auto;
  flex: 1;
  padding: 4px;
  scrollbar-width: thin;
}
.catalog-grid::-webkit-scrollbar {
  width: 6px;
}

/* Product Card */
.product-card {
  display: flex;
  flex-direction: column;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
  position: relative;
  overflow: hidden;
}
.dark .product-card {
  background: #1e293b;
  border-color: #334155;
}
.product-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  border-color: #526d8a;
}
.dark .product-card:hover {
  border-color: #526d8a;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
}
.product-card:active {
  transform: translateY(0);
}

.product-card__image {
  width: 100%;
  height: 70px;
  object-fit: cover;
  border-radius: 8px;
  margin-bottom: 6px;
  background: #f8fafc;
}
.dark .product-card__image {
  background: #0f172a;
}

.product-card__image-placeholder {
  width: 100%;
  height: 70px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  color: #94a3b8;
  border-radius: 8px;
  margin-bottom: 6px;
}
.dark .product-card__image-placeholder {
  background: #0f172a;
  color: #475569;
}

.product-card__name {
  font-size: 0.72rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.25;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  height: 1.85rem;
}
.dark .product-card__name {
  color: #f1f5f9;
}

.product-card__sku {
  font-size: 0.65rem;
  color: #64748b;
  margin-bottom: 2px;
}
.dark .product-card__sku {
  color: #94a3b8;
}

.product-card__barcode {
  width: 80px;
  height: 22px;
  margin-top: 2px;
  border-radius: 3px;
}

.product-card__meta {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 4px;
  margin-top: auto;
  padding-top: 6px;
  border-top: 1px solid #f1f5f9;
}
.dark .product-card__meta {
  border-color: #334155;
}

.product-card__price {
  font-size: 0.72rem;
  font-weight: 800;
  color: #526d8a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dark .product-card__price {
  color: #60a5fa;
}

.product-card__stock {
  align-self: flex-start;
  padding: 1px 5px;
  border-radius: 9999px;
  font-size: 0.55rem;
  font-weight: 700;
  white-space: nowrap;
}
.product-card__stock--ok {
  background: #f0fdf4;
  color: #16a34a;
}
.dark .product-card__stock--ok {
  background: #052e16;
  color: #4ade80;
}
.product-card__stock--warning {
  background: #fef2f2;
  color: #dc2626;
}
.dark .product-card__stock--warning {
  background: #450a0a;
  color: #f87171;
}

/* Search */
.search-area { position: relative; flex-shrink: 0; }
.search-wrap { position: relative; }
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-clear { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; }
.pos-search {
  width: 100%; padding: 8px 32px 8px 38px;
  border: 2px solid #e2e8f0; border-radius: 10px;
  font-size: 0.85rem; background: white;
  transition: border-color 0.15s, box-shadow 0.15s;
  outline: none;
}
.pos-search:focus { border-color: #526d8a; box-shadow: 0 0 0 3px rgba(82,109,138,0.12); }

.search-results {
  position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: white; border: 1px solid #e2e8f0; border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  z-index: 50; max-height: 320px; overflow-y: auto;
}
.search-loading { padding: 16px; text-align: center; color: #94a3b8; font-size: 0.85rem; }
.search-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; cursor: pointer; transition: background 0.1s;
  border-bottom: 1px solid #f8fafc;
}
.search-item:last-child { border-bottom: none; }
.search-item:hover { background: #f0f7ff; }
.search-item--highlighted {
  background: #f0f7ff;
  border-left: 4px solid #526d8a;
  padding-left: 12px;
}
.search-item__name { font-weight: 500; font-size: 0.9rem; color: #0f172a; }
.search-item__meta { font-size: 0.75rem; color: #94a3b8; }
.search-item__price { font-weight: 600; color: #526d8a; font-size: 0.9rem; white-space: nowrap; }

/* Cart */
.cart-area {
  flex: 1; overflow: auto;
  background: white; border-radius: 12px;
  border: 1px solid #f1f5f9;
}
.cart-list {
  min-width: 600px;
}
.cart-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; height: 100%; min-height: 200px; color: #cbd5e1;
}
.cart-header {
  display: grid; grid-template-columns: 1fr 100px 110px 100px 32px;
  gap: 8px; padding: 10px 16px;
  background: #f8fafc; border-bottom: 1px solid #f1f5f9;
  font-size: 0.75rem; font-weight: 600; color: #64748b;
  text-transform: uppercase; letter-spacing: 0.04em;
  position: sticky; top: 0; z-index: 10;
}
.cart-item {
  display: grid; grid-template-columns: 1fr 100px 110px 100px 32px;
  gap: 8px; padding: 10px 16px; align-items: center;
  border-bottom: 1px solid #f8fafc; transition: background 0.1s;
}
.cart-item:last-child { border-bottom: none; }
.cart-item:hover { background: #fafafa; }
.cart-item__price { font-size: 0.85rem; color: #475569; }
.cart-item__subtotal { font-weight: 600; color: #0f172a; font-size: 0.9rem; }
.cart-item__qty { display: flex; align-items: center; gap: 6px; }
.qty-btn {
  width: 26px; height: 26px; border-radius: 6px;
  background: #f1f5f9; border: none; cursor: pointer; color: #475569;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.1s;
}
.qty-btn:hover { background: #e2e8f0; }
.qty-input {
  width: 44px; text-align: center; padding: 4px;
  border: 1px solid #e2e8f0; border-radius: 6px;
  font-size: 0.85rem; font-weight: 600;
}
.cart-item__remove {
  width: 28px; height: 28px; border-radius: 6px;
  background: none; border: none; cursor: pointer;
  color: #cbd5e1; display: flex; align-items: center; justify-content: center;
  transition: all 0.1s;
}
.cart-item__remove:hover { background: #fff1f2; color: #f43f5e; }

/* Discount */
.discount-row {
  display: flex; align-items: center; gap: 12px;
  background: white; border-radius: 10px; padding: 12px 16px;
  border: 1px solid #f1f5f9; flex-shrink: 0;
}
.discount-controls { display: flex; gap: 8px; align-items: center; margin-left: auto; }
.discount-type {
  padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 8px;
  font-size: 0.85rem; background: white; cursor: pointer; outline: none;
}
.discount-input {
  width: 120px; padding: 6px 10px; border: 1px solid #e2e8f0;
  border-radius: 8px; font-size: 0.9rem; font-weight: 600; outline: none;
}
.discount-input:focus { border-color: #526d8a; box-shadow: 0 0 0 2px rgba(82,109,138,0.12); }

/* ── Right ── */
.pos-right {
  display: flex;
  flex-direction: column;
  gap: 8px;
  height: 100%;
  min-height: 0;
  overflow: hidden;
}
@media (max-width: 1024px) {
  .pos-right { height: auto; overflow: visible; }
}
.summary-card {
  background: white; border-radius: 14px; padding: 10px;
  border: 1px solid #f1f5f9;
  display: flex; flex-direction: column; gap: 8px;
}
.summary-title { font-size: 0.9rem; font-weight: 700; color: #0f172a; }
.summary-rows { display: flex; flex-direction: column; gap: 6px; }
.summary-row { display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; }
.summary-row--discount { color: #16a34a; }
.summary-total {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px; background: #0f172a; border-radius: 10px; color: white;
}
.summary-total > span { font-size: 0.8rem; font-weight: 600; opacity: 0.7; }
.total-amount { font-size: 1.4rem; font-weight: 800; }

/* Payment Methods */
.payment-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.payment-method-btn {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 10px 6px; border-radius: 8px;
  border: 2px solid #e2e8f0; background: white;
  font-size: 0.75rem; font-weight: 500; color: #64748b;
  cursor: pointer; transition: all 0.15s;
}
.payment-method-btn.active { border-color: #526d8a; background: #eff6ff; color: #526d8a; }
.payment-method-btn:hover:not(.active) { background: #f8fafc; }

/* Notes */
.pos-notes {
  width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0;
  border-radius: 8px; font-size: 0.85rem; outline: none;
  font-family: inherit;
}
.pos-notes:focus { border-color: #526d8a; box-shadow: 0 0 0 2px rgba(82,109,138,0.12); }

/* Pay Button */
.pay-btn {
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, #526d8a, #435a74);
  color: white; border: none; border-radius: 10px;
  font-size: 1rem; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all 0.15s;
}
.pay-btn:hover:not(:disabled) { background: linear-gradient(135deg, #435a74, #1e40af); transform: translateY(-1px); }
.pay-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.clear-btn {
  width: 100%; padding: 8px;
  background: none; border: 1px solid #fecaca; border-radius: 8px;
  color: #f43f5e; font-size: 0.8rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: all 0.15s;
}
.clear-btn:hover { background: #fff1f2; }

/* ── Payment Modal ── */
.payment-modal { max-width: 440px; }

.payment-items {
  margin-bottom: 12px;
}
.payment-items__title {
  font-size: 0.7rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}
.payment-items__list {
  max-height: 120px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  border: 1px solid #f1f5f9;
  border-radius: 8px;
  background: #fafbfd;
}
.payment-items__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  border-bottom: 1px solid #f1f5f9;
  gap: 8px;
}
.payment-items__row:last-child {
  border-bottom: none;
}
.payment-items__name {
  font-size: 0.75rem;
  font-weight: 500;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
  flex: 1;
}
.payment-items__name b {
  font-weight: 800;
  color: #1e293b;
  margin-right: 2px;
}
.payment-items__subtotal {
  font-size: 0.75rem;
  font-weight: 700;
  color: #1e293b;
  white-space: nowrap;
  flex-shrink: 0;
}
.payment-total {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 16px; background: #f8fafc; border-radius: 10px; margin-bottom: 8px;
}
.payment-total > span { font-size: 0.8rem; color: #64748b; }
.payment-total > strong { font-size: 1.6rem; font-weight: 800; color: #0f172a; }

.quick-cash { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-top: 8px; }
.quick-cash-btn {
  padding: 8px 4px; border: 1px solid #e2e8f0; border-radius: 8px;
  background: white; font-size: 0.72rem; font-weight: 600; color: #475569;
  cursor: pointer; transition: all 0.1s; text-align: center;
}
.quick-cash-btn:hover { background: #eff6ff; border-color: #93c5fd; color: #526d8a; }

.change-display {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px; border-radius: 10px; margin-top: 8px;
}
.change-display--ok { background: #f0fdf4; }
.change-display--ok span { color: #16a34a; font-weight: 500; }
.change-display--ok strong { color: #15803d; font-size: 1.3rem; font-weight: 800; }
.change-display--err { background: #fff1f2; }
.change-display--err span { color: #e11d48; font-weight: 500; }
.change-display--err strong { color: #be123c; font-size: 1.3rem; font-weight: 800; }

.pay-btn-confirm {
  flex: 1; padding: 12px 20px;
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: white; border: none; border-radius: 8px;
  font-size: 0.95rem; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all 0.15s;
}
.pay-btn-confirm:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #166534); }
.pay-btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Receipt Modal ── */
.receipt-modal { max-width: 340px; }
.receipt {
  font-family: inherit;
  font-size: 0.82rem; line-height: 1.5;
  padding: 16px 20px;
}
.receipt__header { text-align: center; margin-bottom: 8px; }
.receipt__logo {
  display: block;
  width: 34mm;
  height: 16mm;
  object-fit: contain;
  margin: 0 auto 4px;
}
.receipt__header h2 { font-size: 1rem; font-weight: 700; margin-bottom: 2px; }
.receipt__header p { font-size: 0.75rem; color: #475569; }
.receipt__socials { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px; margin-top: 6px; }
.receipt__socials--single { grid-template-columns: minmax(0, 1fr); }
.receipt__social { display: flex; align-items: flex-start; justify-content: center; gap: 3px; min-width: 0; overflow-wrap: anywhere; text-align: center; }
.receipt__social span { min-width: 0; overflow-wrap: anywhere; }
.receipt__social-icon { width: 12px; height: 12px; flex: 0 0 12px; }
.receipt__divider { color: #94a3b8; margin: 8px 0; font-size: 0.75rem; }
.receipt__meta-row, .receipt__total-row {
  display: flex; justify-content: space-between; gap: 8px;
  font-size: 0.8rem; margin: 3px 0;
}
.receipt__item { margin-bottom: 6px; }
.receipt__item-name { font-weight: 600; font-size: 0.8rem; }
.receipt__item-row {
  display: flex; justify-content: space-between;
  font-size: 0.78rem; color: #475569;
}
.receipt__total-row--bold { font-weight: 700; font-size: 0.9rem; }
.receipt__total-row--change { color: #16a34a; font-weight: 700; }
.receipt__footer { text-align: center; margin-top: 8px; color: #475569; font-size: 0.78rem; }

/* Modals */
.modal {
  max-height: 90vh; overflow-y: auto;
}
.modal__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 24px; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; background: white; z-index: 1;
}
.modal__header h3 { font-size: 1rem; font-weight: 600; color: #0f172a; }
.modal__close {
  width: 28px; height: 28px; border-radius: 6px;
  background: #f1f5f9; border: none; cursor: pointer; color: #64748b;
  display: flex; align-items: center; justify-content: center;
}
.modal__body { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
.modal__footer {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 16px 24px; border-top: 1px solid #f1f5f9;
}

.form-group { display: flex; flex-direction: column; gap: 6px; }

</style>

<style>
/* Print styles */
@media print {
  .receipt__logo {
    filter: brightness(0);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  @page {
    margin: 0;
    size: auto;
  }
  body {
    background-color: white !important;
    color: black !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  /* Hide unnecessary modal elements and elements marked with no-print */
  .no-print, .modal__footer, .modal__header {
    display: none !important;
  }
  /* Ensure modal overlay containing the receipt is visible but transparent */
  .modal-overlay {
    position: static !important;
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    padding: 0 !important;
    display: block !important;
  }
  /* Show only receipt */
  .receipt-modal {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border: none !important;
  }
  .receipt {
    font-family: 'Courier New', Courier, monospace !important;
    padding: 0 !important;
    margin: 0 auto !important;
    background: transparent !important;
    box-sizing: border-box !important;
  }
  .receipt-58mm {
    width: 58mm !important;
    max-width: 58mm !important;
    font-size: 11px !important;
    line-height: 1.3 !important;
  }
  .receipt-80mm {
    width: 80mm !important;
    max-width: 80mm !important;
    font-size: 13px !important;
    line-height: 1.4 !important;
  }
  .receipt__divider {
    letter-spacing: -1px;
  }
}
</style>

<style scoped>

.save-draft-btn {
  width: 100%; padding: 10px;
  background: none; border: 1px solid #d3dce4; border-radius: 8px;
  color: #526d8a; font-size: 0.8rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: all 0.15s;
  margin-top: 6px;
}
.save-draft-btn:hover:not(:disabled) { background: #e7ecf1; }
.save-draft-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.draft-list-trigger {
  height: 38px; padding: 0 12px;
  background: #eff6ff; color: #526d8a;
  border: 1px solid #bfdbfe; border-radius: 10px;
  font-weight: 600; font-size: 0.8rem; cursor: pointer;
  display: flex; align-items: center; gap: 6px;
  transition: all 0.15s; white-space: nowrap;
}
.draft-list-trigger:hover { background: #dbeafe; }

.camera-scan-trigger {
  height: 38px; padding: 0 12px;
  background: #f8fafc; color: #334155;
  border: 1px solid #cbd5e1; border-radius: 10px;
  font-weight: 600; font-size: 0.8rem; cursor: pointer;
  display: flex; align-items: center; gap: 6px;
  transition: all 0.15s; white-space: nowrap;
}
.camera-scan-trigger:hover { background: #eff6ff; border-color: #93c5fd; color: #435a74; }
.camera-scan-overlay { z-index: 100000; }
.camera-scan-modal { width: min(92vw, 560px); border-radius: 20px; overflow: hidden; }
.camera-scan-body { display: flex; flex-direction: column; gap: 12px; }
.camera-preview {
  position: relative; width: 100%; aspect-ratio: 4 / 3; overflow: hidden;
  display: grid; place-items: center; background: #0f172a; border-radius: 14px;
}
.camera-preview video { width: 100%; height: 100%; object-fit: cover; }
.camera-scan-frame {
  position: absolute; left: 10%; top: 35%; width: 80%; height: 30%;
  border: 2px solid #38bdf8; border-radius: 12px;
  box-shadow: 0 0 0 999px rgba(15, 23, 42, 0.25);
}
.camera-loading { position: absolute; color: white; font-size: 0.9rem; }
.camera-scan-hint, .camera-scan-error { margin: 0; font-size: 0.85rem; line-height: 1.5; }
.camera-scan-hint { color: #64748b; }
.camera-scan-error { color: #b91c1c; background: #fef2f2; padding: 10px 12px; border-radius: 10px; }

/* Keep the cart and payment controls beside the catalog on wide tablets. */
@media (hover: none) and (pointer: coarse) and (orientation: landscape) and (min-width: 900px) and (min-height: 500px) {
  .pos-layout {
    grid-template-columns: minmax(0, 1fr) 340px;
    height: calc(100dvh - 88px);
    min-height: 0;
    overflow: hidden;
  }
  .pos-left,
  .pos-right {
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }
  .catalog-container {
    height: auto;
    min-height: 0;
    overflow: hidden;
    flex: 1;
  }
  .catalog-grid {
    height: auto;
    min-height: 0;
    overflow-y: auto;
    flex: 1;
  }
}

/* Phone landscape has enough width for two columns, but needs page scrolling. */
@media (hover: none) and (pointer: coarse) and (orientation: landscape) and (min-width: 700px) and (max-height: 499px) {
  .pos-layout {
    grid-template-columns: minmax(0, 1fr) minmax(260px, 300px);
    height: auto;
    min-height: 0;
    overflow: visible;
    align-items: start;
  }
  .pos-left,
  .pos-right {
    height: auto;
    min-height: 0;
    overflow: visible;
  }
  .catalog-container,
  .catalog-grid {
    height: auto;
    min-height: 0;
    overflow: visible;
    flex: none;
  }
}

@media (max-width: 640px) {
  .camera-scan-trigger { padding: 0 10px; }
  .camera-scan-trigger span { display: none; }
}
</style>
