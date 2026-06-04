<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import {
  Search, ShoppingCart, Trash2, Plus, Minus, CreditCard,
  Wallet, QrCode, Barcode, X, CheckCircle, Package,
  Calculator, Percent, FolderDown, FolderOpen, AlertTriangle,
} from '@lucide/vue'

const toast   = useToast()
const page    = usePage()
const settings = computed(() => page.props.settings)

// ── Search & Products ─────────────────────────────
const searchQuery      = ref('')
const searchResults    = ref([])
const searching        = ref(false)
const searchInput      = ref(null)
const highlightedIndex = ref(-1)
const resultsContainer = ref(null)
let searchTimer        = null

async function searchProducts(q) {
  if (!q || q.length < 1) { searchResults.value = []; return }
  searching.value = true
  try {
    const res = await axios.get(route('kasir.product.search'), { params: { q } })
    searchResults.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    searching.value = false
  }
}

watch(searchQuery, (val) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => searchProducts(val), 150)
  highlightedIndex.value = -1
})

watch(searchResults, (newResults) => {
  if (newResults && newResults.length > 0) {
    highlightedIndex.value = 0
  } else {
    highlightedIndex.value = -1
  }
})

function highlightNext() {
  if (searchResults.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length
  scrollToHighlighted()
}

function highlightPrev() {
  if (searchResults.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length
  scrollToHighlighted()
}

function selectHighlighted() {
  if (barcodeBuffer.length > 2) return
  if (searchResults.value.length === 0) return
  if (highlightedIndex.value >= 0 && highlightedIndex.value < searchResults.value.length) {
    addToCart(searchResults.value[highlightedIndex.value])
  }
}

function scrollToHighlighted() {
  setTimeout(() => {
    if (!resultsContainer.value) return
    const container = resultsContainer.value
    const items = container.querySelectorAll('.search-item')
    if (items && items[highlightedIndex.value]) {
      const activeItem = items[highlightedIndex.value]
      const containerHeight = container.clientHeight
      const itemHeight = activeItem.clientHeight
      const itemTop = activeItem.offsetTop
      
      if (itemTop + itemHeight > container.scrollTop + containerHeight) {
        container.scrollTop = itemTop + itemHeight - containerHeight
      } else if (itemTop < container.scrollTop) {
        container.scrollTop = itemTop
      }
    }
  }, 0)
}

// ── Barcode USB HID scanner support ──────────────
let barcodeBuffer = ''
let barcodeTimer  = null

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

async function searchByBarcode(barcode) {
  try {
    const res = await axios.get(route('kasir.product.search'), { params: { q: barcode } })
    if (res.data.length === 1) {
      addToCart(res.data[0])
      toast.success(`${res.data[0].name} ditambahkan`)
    } else if (res.data.length > 1) {
      searchQuery.value = barcode
      searchResults.value = res.data
    } else {
      toast.warning('Barcode tidak ditemukan')
    }
  } catch (e) {}
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  loadDraftsFromStorage()
  setTimeout(() => {
    searchInput.value?.focus()
  }, 100)
})
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))

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
  if (discountType.value === 'percent') return subtotal.value * (discountValue.value / 100)
  return Math.min(discountValue.value, subtotal.value)
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
    await axios.post(route('kasir.store'), {
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
    lastTransaction.value = {
      invoice: 'INV-' + Date.now(),
      items:   [...cart.value],
      subtotal: subtotal.value,
      discount: discountAmount.value,
      tax:      taxAmount.value,
      total:    total.value,
      paid:     paidAmount.value,
      change:   change.value,
      method:   paymentMethod.value,
      time:     new Date().toLocaleString('id-ID'),
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
</script>

<template>
  <AppLayout>
    <template #title>Kasir</template>

    <div class="pos-layout no-print">

      <!-- LEFT: Products Search + Cart -->
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
                placeholder="Cari produk (nama / SKU / barcode) atau scan barcode..."
                class="pos-search"
                autocomplete="off"
                @keydown.down.prevent="highlightNext"
                @keydown.up.prevent="highlightPrev"
                @keydown.enter.prevent="selectHighlighted"
              />
              <button v-if="searchQuery" @click="searchQuery = ''; searchResults = []" class="search-clear">
                <X :size="16" />
              </button>
            </div>

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

          <!-- Search Results -->
          <div v-if="searchResults.length > 0 || searching" ref="resultsContainer" class="search-results">
            <div v-if="searching" class="search-loading">Mencari...</div>
            <div
              v-for="(product, idx) in searchResults"
              :key="product.id"
              class="search-item"
              :class="{ 'search-item--highlighted': idx === highlightedIndex }"
              @click="addToCart(product)"
              @mouseenter="highlightedIndex = idx"
            >
              <div class="search-item__info">
                <span class="search-item__name">{{ product.name }}</span>
                <span class="search-item__meta">{{ product.sku }} · Stok: {{ product.stock }} {{ product.unit }}</span>
              </div>
              <span class="search-item__price">{{ formatCurrency(product.sell_price) }}</span>
            </div>
          </div>
        </div>

        <!-- Cart Items -->
        <div class="cart-area">
          <div v-if="cart.length === 0" class="cart-empty">
            <ShoppingCart :size="48" />
            <p>Keranjang kosong</p>
            <p class="text-sm text-slate-400">Scan barcode atau cari produk di atas</p>
          </div>

          <div v-else class="cart-list">
            <!-- Header -->
            <div class="cart-header">
              <span>Produk</span>
              <span>Harga</span>
              <span>Qty</span>
              <span>Subtotal</span>
              <span></span>
            </div>

            <!-- Items -->
            <div v-for="(item, i) in cart" :key="item.product_id" class="cart-item">
              <div class="cart-item__name">
                <p class="font-medium text-slate-800">{{ item.name }}</p>
                <p class="text-xs text-slate-400">{{ item.sku }}</p>
              </div>
              <span class="cart-item__price">{{ formatCurrency(item.price) }}</span>
              <div class="cart-item__qty">
                <button @click="updateQty(i, -1)" class="qty-btn"><Minus :size="12" /></button>
                <input
                  :value="item.qty"
                  @change="setQty(i, $event.target.value)"
                  type="number" min="1"
                  class="qty-input"
                />
                <button @click="updateQty(i, 1)" class="qty-btn"><Plus :size="12" /></button>
              </div>
              <span class="cart-item__subtotal">{{ formatCurrency(item.subtotal) }}</span>
              <button @click="removeFromCart(i)" class="cart-item__remove">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>
        </div>

        <!-- Discount Row -->
        <div v-if="cart.length > 0" class="discount-row">
          <span class="text-sm font-medium text-slate-600">Diskon:</span>
          <div class="discount-controls">
            <select v-model="discountType" class="discount-type">
              <option value="nominal">Rp</option>
              <option value="percent">%</option>
            </select>
            <input
              v-model="discountValue"
              type="number" min="0"
              :max="discountType === 'percent' ? 100 : subtotal"
              class="discount-input"
              placeholder="0"
            />
          </div>
        </div>
      </div>

      <!-- RIGHT: Summary + Pay -->
      <div class="pos-right">
        <!-- Customer Section Card -->
        <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-4 shadow-sm flex flex-col gap-3">
          <h4 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-2">
            <User :size="16" class="text-indigo-500" />
            Pelanggan / Member
          </h4>
          
          <!-- Selected Customer -->
          <div v-if="selectedCustomer" class="flex items-center justify-between p-2.5 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl border border-indigo-100/50 dark:border-indigo-900/30">
            <div>
              <p class="font-bold text-slate-850 dark:text-slate-250 text-sm">{{ selectedCustomer.name }}</p>
              <p class="text-[10px] text-slate-500 dark:text-slate-450 font-bold mt-0.5">
                {{ selectedCustomer.phone || '-' }} · Poin: <span class="text-indigo-600 dark:text-indigo-400 font-extrabold">{{ selectedCustomer.points }}</span>
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
                class="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
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
              class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 text-xs font-bold rounded-xl border border-indigo-100 dark:border-indigo-900/30 transition flex items-center justify-center whitespace-nowrap"
              type="button"
            >
              + Baru
            </button>
          </div>
        </div>

        <div class="summary-card">
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
          <textarea
            v-model="notes"
            class="pos-notes"
            placeholder="Catatan (opsional)..."
            rows="2"
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

          <!-- Draft Button -->
          <button
            v-if="cart.length > 0"
            class="save-draft-btn"
            @click="openSaveDraftModal"
            type="button"
          >
            <FolderDown :size="16" />
            Simpan sebagai Draf
          </button>

          <!-- Clear cart -->
          <button
            v-if="cart.length > 0"
            class="clear-btn"
            @click="clearCart"
          >
            <Trash2 :size="14" />
            Kosongkan Keranjang
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
              No. Invoice: <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ lastTransaction?.invoice }}</span>
            </p>
          </div>

          <!-- Transaction details card -->
          <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-4 space-y-2.5 text-left border border-slate-100 dark:border-slate-800 text-sm">
            <div v-if="lastTransaction?.customer" class="flex justify-between">
              <span class="text-slate-500">Member</span>
              <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ lastTransaction.customer.name }}</span>
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
              <button type="submit" class="btn-primary" :disabled="customerForm.processing" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
                {{ customerForm.processing ? 'Menyimpan...' : 'Simpan Member' }}
              </button>
            </div>
          </form>
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
            <button @click="confirmSaveDraft" class="btn-primary" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); border: none;">
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
                        class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700 transition"
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
/* ── Layout ── */
.pos-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  height: calc(100vh - 120px);
  overflow: hidden;
}
@media (max-width: 1024px) {
  .pos-layout { grid-template-columns: 1fr; height: auto; }
}

/* ── Left ── */
.pos-left {
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: hidden;
}

/* Search */
.search-area { position: relative; flex-shrink: 0; }
.search-wrap { position: relative; }
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-clear { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; }
.pos-search {
  width: 100%; padding: 14px 40px 14px 46px;
  border: 2px solid #e2e8f0; border-radius: 12px;
  font-size: 0.95rem; background: white;
  transition: border-color 0.15s, box-shadow 0.15s;
  outline: none;
}
.pos-search:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

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
  border-left: 4px solid #3b82f6;
  padding-left: 12px;
}
.search-item__name { font-weight: 500; font-size: 0.9rem; color: #0f172a; }
.search-item__meta { font-size: 0.75rem; color: #94a3b8; }
.search-item__price { font-weight: 600; color: #2563eb; font-size: 0.9rem; white-space: nowrap; }

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
.discount-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); }

/* ── Right ── */
.pos-right { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 80px; height: fit-content; }
.summary-card {
  background: white; border-radius: 14px; padding: 20px;
  border: 1px solid #f1f5f9;
  display: flex; flex-direction: column; gap: 14px;
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
.payment-method-btn.active { border-color: #2563eb; background: #eff6ff; color: #2563eb; }
.payment-method-btn:hover:not(.active) { background: #f8fafc; }

/* Notes */
.pos-notes {
  width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0;
  border-radius: 8px; font-size: 0.85rem; resize: none; outline: none;
  font-family: inherit;
}
.pos-notes:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); }

/* Pay Button */
.pay-btn {
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white; border: none; border-radius: 10px;
  font-size: 1rem; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all 0.15s;
}
.pay-btn:hover:not(:disabled) { background: linear-gradient(135deg, #1d4ed8, #1e40af); transform: translateY(-1px); }
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
.payment-modal { max-width: 400px; }
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
.quick-cash-btn:hover { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }

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
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.82rem; line-height: 1.5;
  padding: 16px 20px;
}
.receipt__header { text-align: center; margin-bottom: 8px; }
.receipt__header h2 { font-size: 1rem; font-weight: 700; margin-bottom: 2px; }
.receipt__header p { font-size: 0.75rem; color: #475569; }
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

/* Print styles */
@media print {
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
  .no-print, .modal__footer, .modal__header, aside, nav, header, .sidebar, .navbar {
    display: none !important;
  }
  .app-shell, .main-content, .page-content {
    margin: 0 !important;
    padding: 0 !important;
    min-height: 0 !important;
    height: auto !important;
    width: 100% !important;
    display: block !important;
    background: transparent !important;
  }
  .modal-overlay {
    position: static !important;
    display: block !important;
    background: none !important;
    backdrop-filter: none !important;
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    box-shadow: none !important;
  }
  .modal {
    box-shadow: none !important;
    border-radius: 0 !important;
    max-height: none !important;
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    background: transparent !important;
    border: none !important;
  }
  .receipt {
    font-family: 'Courier New', Courier, monospace !important;
    padding: 2mm 3mm !important;
    margin: 0 !important;
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

.save-draft-btn {
  width: 100%; padding: 10px;
  background: none; border: 1px solid #c7d2fe; border-radius: 8px;
  color: #4f46e5; font-size: 0.8rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: all 0.15s;
  margin-top: 6px;
}
.save-draft-btn:hover:not(:disabled) { background: #e0e7ff; }
.save-draft-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.draft-list-trigger {
  height: 48px; padding: 0 16px;
  background: #eff6ff; color: #2563eb;
  border: 1px solid #bfdbfe; border-radius: 12px;
  font-weight: 600; font-size: 0.85rem; cursor: pointer;
  display: flex; align-items: center; gap: 8px;
  transition: all 0.15s; white-space: nowrap;
}
.draft-list-trigger:hover { background: #dbeafe; }
</style>
