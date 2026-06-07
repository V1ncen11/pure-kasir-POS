<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Tag,
  BarChart3,
  Users,
  Settings,
  Bell,
  LogOut,
  Menu,
  X,
  ChevronRight,
  Store,
  AlertTriangle,
  User,
  Truck,
  ArrowDownToLine,
  History,
  Ruler,
} from '@lucide/vue'

const page = usePage()
const user  = computed(() => page.props.auth.user)
const settings = computed(() => page.props.settings)
const lowStockCount = computed(() => page.props.lowStockCount ?? 0)

const toast = useToast()

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast.success(flash.success)
  if (flash?.error)   toast.error(flash.error)
}, { immediate: true, deep: true })

watch(() => page.props.status, (status) => {
  if (status) {
    if (status === 'profile-updated') {
      toast.success('Informasi profil berhasil diperbarui!')
    } else if (status === 'avatar-updated') {
      toast.success('Foto profil berhasil diperbarui!')
    } else if (status === 'password-updated') {
      toast.success('Kata sandi berhasil diperbarui!')
    } else {
      toast.info(status)
    }
  }
}, { immediate: true })

const sidebarOpen      = ref(true)
const mobileOpen       = ref(false)
const showLogoutModal  = ref(false)

function confirmLogout() {
  router.post(route('logout'))
}

const storeName = computed(() => settings.value?.store_name ?? 'Kasir POS')

const formattedStoreName = computed(() => {
  const name = storeName.value
  const parts = name.split(' ')
  if (parts.length >= 3) {
    return {
      first: parts[0],
      middle: parts[1],
      last: parts.slice(2).join(' ')
    }
  } else if (parts.length === 2) {
    return {
      first: parts[0],
      middle: parts[1],
      last: ''
    }
  }
  return {
    first: name,
    middle: '',
    last: ''
  }
})

const isAdmin = computed(() => user.value?.role === 'admin')

// Nav items — filtered by role
const navItems = computed(() => {
  const items = [
    {
      label: 'Dashboard',
      icon: LayoutDashboard,
      route: 'dashboard',
      roles: ['admin', 'kasir'],
    },
    {
      label: 'Kasir',
      icon: ShoppingCart,
      route: 'kasir.index',
      roles: ['admin', 'kasir'],
    },
    {
      label: 'Transaksi',
      icon: BarChart3,
      route: 'transactions.index',
      roles: ['admin', 'kasir'],
    },
    {
      label: 'Produk',
      icon: Package,
      route: 'products.index',
      roles: ['admin'],
      badge: lowStockCount.value > 0 ? lowStockCount.value : null,
      badgeColor: 'warning',
    },
    {
      label: 'Kategori',
      icon: Tag,
      route: 'categories.index',
      roles: ['admin'],
    },
    {
      label: 'Satuan',
      icon: Ruler,
      route: 'satuan.index',
      roles: ['admin'],
    },
    {
      label: 'Stok Masuk',
      icon: ArrowDownToLine,
      route: 'purchases.index',
      roles: ['admin'],
    },
    {
      label: 'Supplier',
      icon: Truck,
      route: 'suppliers.index',
      roles: ['admin'],
    },
    {
      label: 'Pelanggan',
      icon: Users,
      route: 'customers.index',
      roles: ['admin'],
    },
    {
      label: 'Laporan',
      icon: BarChart3,
      route: 'reports.index',
      roles: ['admin'],
    },
    {
      label: 'Pengguna',
      icon: Users,
      route: 'users.index',
      roles: ['admin'],
    },
    {
      label: 'Log Aktivitas',
      icon: History,
      route: 'activity-logs.index',
      roles: ['admin'],
    },
    {
      label: 'Pengaturan',
      icon: Settings,
      route: 'settings.index',
      roles: ['admin'],
    },
  ]

  return items.filter(item => item.roles.includes(user.value?.role))
})

function isActive(routeName) {
  return route().current(routeName) || route().current(routeName + '.*')
}
</script>

<template>
  <div class="app-shell">
    <!-- Mobile Overlay -->
    <div
      v-if="mobileOpen"
      class="mobile-overlay"
      @click="mobileOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="sidebar"
      :class="{ 'sidebar--collapsed': !sidebarOpen, 'sidebar--mobile-open': mobileOpen }"
    >
      <!-- Logo / Store Name -->
      <div class="sidebar__header">
        <div class="sidebar__logo" :style="settings?.store_logo ? 'background: #ffffff; padding: 2px;' : ''">
          <img v-if="settings?.store_logo" :src="`/storage/${settings.store_logo}`" :alt="storeName" class="w-full h-full object-contain rounded-lg" />
          <Store v-else :size="22" />
        </div>
        <Transition name="fade-slide">
          <div v-if="sidebarOpen" class="sidebar__brand">
            <span class="sidebar__brand-name" style="font-weight: 800;">
              <span class="text-blue-900">{{ formattedStoreName.first }}</span>
              <span class="text-blue-600 ml-1">{{ formattedStoreName.middle }}</span>
              <span class="text-sky-500 ml-1" v-if="formattedStoreName.last">{{ formattedStoreName.last }}</span>
            </span>
          </div>
        </Transition>
        <button class="sidebar__toggle" @click="sidebarOpen = !sidebarOpen">
          <ChevronRight :size="16" :class="{ 'rotate-180': sidebarOpen }" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="sidebar__nav">
        <Link
          v-for="item in navItems"
          :key="item.route"
          :href="route(item.route)"
          class="sidebar__nav-item"
          :class="{ 'sidebar__nav-item--active': isActive(item.route) }"
        >
          <span class="sidebar__nav-icon">
            <component :is="item.icon" :size="20" />
          </span>
          <Transition name="fade-slide">
            <span v-if="sidebarOpen" class="sidebar__nav-label">{{ item.label }}</span>
          </Transition>
          <span
            v-if="item.badge && sidebarOpen"
            class="sidebar__nav-badge"
            :class="`sidebar__nav-badge--${item.badgeColor}`"
          >{{ item.badge }}</span>
          <!-- Tooltip saat collapsed -->
          <span v-if="!sidebarOpen" class="sidebar__tooltip">{{ item.label }}</span>
        </Link>
      </nav>

      <!-- User info -->
      <div class="sidebar__footer">
        <Link :href="route('profile.edit')" class="sidebar__user" title="Profil Saya">
          <div class="sidebar__user-avatar" style="overflow:hidden;">
            <img v-if="user?.avatar_url" :src="user.avatar_url" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" />
            <User v-else :size="18" />
          </div>
          <Transition name="fade-slide">
            <div v-if="sidebarOpen || mobileOpen" class="sidebar__user-info">
              <span class="sidebar__user-name">{{ user?.name }}</span>
              <div class="sidebar__user-status">
                <span class="status-dot"></span>
                <span class="sidebar__user-role">{{ user?.role === 'admin' ? 'Admin' : 'Kasir' }}</span>
              </div>
            </div>
          </Transition>
        </Link>
        <button
          v-if="sidebarOpen || mobileOpen"
          class="sidebar__logout"
          title="Logout"
          @click="showLogoutModal = true"
        >
          <LogOut :size="18" />
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" :class="{ 'main-content--expanded': !sidebarOpen }">

      <!-- Top Navbar -->
      <header class="navbar">
        <div class="navbar__left">
          <!-- Mobile menu button -->
          <button class="navbar__mobile-menu" @click="mobileOpen = !mobileOpen">
            <component :is="mobileOpen ? X : Menu" :size="22" />
          </button>
          <!-- Page title slot -->
          <div class="navbar__page-title">
            <slot name="title">Dashboard</slot>
          </div>
        </div>

        <div class="navbar__right">
          <!-- Low stock alert -->
          <Link
            v-if="isAdmin && lowStockCount > 0"
            :href="route('products.index')"
            class="navbar__alert-btn"
            title="Produk stok menipis"
          >
            <AlertTriangle :size="18" />
            <span class="navbar__alert-badge">{{ lowStockCount }}</span>
          </Link>

          <!-- User dropdown -->
          <Link :href="route('profile.edit')" class="navbar__user" title="Profil Saya">
            <div class="navbar__user-avatar" style="overflow:hidden;">
              <img v-if="user?.avatar_url" :src="user.avatar_url" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" />
              <User v-else :size="18" />
            </div>
            <div class="navbar__user-meta">
              <span class="navbar__user-name">{{ user?.name }}</span>
              <span class="navbar__user-role">{{ user?.role === 'admin' ? 'Admin' : 'Kasir' }}</span>
            </div>
          </Link>

          <!-- Logout Button (Top Navbar) -->
          <button
            class="navbar__logout-btn"
            title="Logout"
            @click="showLogoutModal = true"
          >
            <LogOut :size="18" />
          </button>
        </div>
      </header>

      <!-- Page Content -->
      <main class="page-content">
        <slot />
      </main>
    </div>
  </div>

  <!-- ── Logout Confirmation Modal ── -->
  <Teleport to="body">
    <Transition name="logout-fade">
      <div v-if="showLogoutModal" class="logout-backdrop" @click.self="showLogoutModal = false">
        <div class="logout-modal">
          <!-- Icon -->
          <div class="logout-modal__icon">
            <LogOut :size="28" />
          </div>

          <!-- Content -->
          <div class="logout-modal__content">
            <h3 class="logout-modal__title">Keluar dari Sistem?</h3>
            <p class="logout-modal__desc">
              Kamu akan keluar dari akun <strong>{{ user?.name }}</strong>.
              Pastikan semua pekerjaan sudah tersimpan.
            </p>
          </div>

          <!-- Actions -->
          <div class="logout-modal__actions">
            <button class="logout-btn-cancel" @click="showLogoutModal = false">
              Batal
            </button>
            <button class="logout-btn-confirm" @click="confirmLogout">
              <LogOut :size="16" />
              Ya, Keluar
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* ── App Shell ── */
.app-shell {
  display: flex;
  min-height: 100vh;
  background-color: #f1f6fb; /* Soft sky blue-gray background */
}

/* ── Sidebar ── */
.sidebar {
  width: 260px;
  height: 100vh;
  background-color: #f4f8fd; /* Soft light blue pastel background */
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 50;
  overflow: hidden;
  border-right: 1px solid #d0e1fd; /* Subtle blue border */
  box-shadow: 2px 0 12px rgba(37, 99, 235, 0.03); /* Extremely soft shadow */
}

.sidebar--collapsed {
  width: 72px;
}

/* Mobile */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    width: 260px !important;
  }
  .sidebar--mobile-open {
    transform: translateX(0);
  }
  .mobile-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.3);
    z-index: 49;
    backdrop-filter: blur(4px);
  }
}

/* Header */
.sidebar__header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 16px;
  border-bottom: 1px solid #e1eefb;
  min-height: 72px;
}

.sidebar__logo {
  width: 40px;
  height: 40px;
  min-width: 40px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.sidebar__brand { flex: 1; overflow: hidden; }
.sidebar__brand-name {
  display: block;
  color: #1e3a8a; /* Dark royal blue */
  font-weight: 700;
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sidebar__brand-sub {
  display: block;
  color: #0ea5e9; /* Vibrant sky blue */
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.sidebar__toggle {
  width: 28px;
  height: 28px;
  min-width: 28px;
  border-radius: 6px;
  background: #ffffff;
  border: 1px solid #d0e1fd;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}
.sidebar__toggle:hover {
  background: #e0f2fe;
  color: #0284c7;
  border-color: #bae6fd;
}
.sidebar__toggle svg { transition: transform 0.25s ease; }
.rotate-180 { transform: rotate(180deg); }

@media (max-width: 768px) {
  .sidebar__toggle {
    display: none !important;
  }
}

/* Nav */
.sidebar__nav {
  flex: 1;
  padding: 12px 8px;
  overflow-y: auto;
  overflow-x: hidden;
}

.sidebar__nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  color: #475569; /* Slate gray for readability */
  text-decoration: none;
  transition: all 0.15s ease;
  margin-bottom: 2px;
  white-space: nowrap;
}
.sidebar__nav-item:hover {
  background: #e9f2fc;
  color: #1e40af;
}
.sidebar__nav-item--active {
  background: #dbeafe; /* Light blue accent */
  color: #1d4ed8; /* Active royal blue text */
  font-weight: 600;
}
.sidebar__nav-item--active .sidebar__nav-icon {
  color: #2563eb;
}

.sidebar__nav-icon {
  width: 22px;
  min-width: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
}
.sidebar__nav-item:hover .sidebar__nav-icon {
  color: #1d4ed8;
}

.sidebar__nav-label {
  font-size: 0.875rem;
  font-weight: 500;
}

.sidebar__nav-badge {
  margin-left: auto;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 99px;
}
.sidebar__nav-badge--warning {
  background: #fef3c7;
  color: #d97706;
  border: 1px solid #fde68a;
}
.sidebar__nav-badge--danger {
  background: #fee2e2;
  color: #ef4444;
  border: 1px solid #fca5a5;
}

/* Tooltip on collapsed */
.sidebar__tooltip {
  display: none;
  position: absolute;
  left: calc(100% + 12px);
  background: #1e293b;
  color: #e2e8f0;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.8rem;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  pointer-events: none;
  z-index: 100;
  border: 1px solid rgba(255,255,255,0.08);
}
.sidebar--collapsed .sidebar__nav-item:hover .sidebar__tooltip {
  display: block;
}

/* Footer */
.sidebar__footer {
  padding: 12px 8px;
  border-top: 1px solid #e1eefb;
  display: flex;
  align-items: center;
  gap: 8px;
}

.sidebar__user {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  overflow: hidden;
}

.sidebar__user-avatar {
  width: 36px;
  height: 36px;
  min-width: 36px;
  background: #e0ecfb;
  border: 1px solid #cce0f9;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #2563eb;
  transition: all 0.2s ease;
}
.sidebar__user-avatar:hover {
  background: #dbeafe;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

.sidebar__user-info { 
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden; 
}
.sidebar__user-name {
  display: block;
  color: #1e3a8a; /* Dark navy */
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}
.sidebar__user-status {
  display: flex;
  align-items: center;
  gap: 5px;
}
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #10b981; /* Green */
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
  display: inline-block;
  animation: pulse-dot 2s infinite;
}
.sidebar__user-role {
  color: #64748b;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  line-height: 1;
}

@keyframes pulse-dot {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.sidebar__logout {
  width: 32px;
  height: 32px;
  min-width: 32px;
  border-radius: 8px;
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
}
.sidebar__logout:hover { background: #fca5a5; color: #b91c1c; }

/* ── Main Content ── */
.main-content {
  flex: 1;
  margin-left: 260px;
  transition: margin-left 0.25s ease;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.main-content--expanded { margin-left: 72px; }

@media (max-width: 768px) {
  .main-content { margin-left: 0; }
}

/* ── Navbar ── */
.navbar {
  height: 64px;
  background: rgba(255, 255, 255, 0.85); /* Premium glassmorphism */
  backdrop-filter: blur(8px);
  border-bottom: 1px solid #d0e1fd;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 40;
  box-shadow: 0 4px 20px -2px rgba(37, 99, 235, 0.04);
}

.navbar__left { display: flex; align-items: center; gap: 16px; }

.navbar__mobile-menu {
  display: none;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #f1f5f9;
  border: none;
  color: #475569;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
@media (max-width: 768px) {
  .navbar__mobile-menu { display: flex; }
}

.navbar__page-title {
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
}

.navbar__right { display: flex; align-items: center; gap: 12px; }

/* ── Navbar Logout Button ── */
.navbar__logout-btn {
  display: none;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #ef4444;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}
.navbar__logout-btn:hover {
  background: #fca5a5;
  color: #b91c1c;
}
@media (max-width: 768px) {
  .navbar__logout-btn {
    display: flex;
  }
}
.main-content--expanded .navbar__logout-btn {
  display: flex;
}

.navbar__alert-btn {
  position: relative;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #d97706;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
}
.navbar__alert-btn:hover { background: #fef3c7; }

.navbar__alert-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  width: 16px;
  height: 16px;
  border-radius: 99px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.navbar__user {
  display: flex;
  align-items: center;
  gap: 10px;
}
.navbar__user-avatar {
  width: 36px;
  height: 36px;
  background: #e0ecfb;
  border: 1px solid #cce0f9;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #2563eb;
  transition: all 0.2s ease;
}
.navbar__user-avatar:hover {
  background: #dbeafe;
  color: #1d4ed8;
  border-color: #bfdbfe;
}
.navbar__user-meta { display: flex; flex-direction: column; }
.navbar__user-name { font-size: 0.8rem; font-weight: 600; color: #0f172a; }
.navbar__user-role { font-size: 0.7rem; color: #94a3b8; }

@media (max-width: 640px) {
  .navbar__user-meta { display: none; }
}

/* ── Page Content ── */
.page-content {
  flex: 1;
  padding: 24px;
}

@media (max-width: 640px) {
  .page-content { padding: 16px; }
}

/* ── Transitions ── */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-6px);
}

/* ── Logout Confirmation Modal ── */
.logout-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  padding: 16px;
}

.logout-modal {
  background: #ffffff;
  border-radius: 20px;
  padding: 32px;
  width: 100%;
  max-width: 380px;
  box-shadow:
    0 24px 48px -12px rgba(15, 23, 42, 0.25),
    0 0 0 1px rgba(226, 232, 240, 0.8);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  text-align: center;
}

.logout-modal__icon {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: linear-gradient(135deg, #fee2e2, #fecaca);
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
}

.logout-modal__content { display: flex; flex-direction: column; gap: 8px; }

.logout-modal__title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.logout-modal__desc {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}

.logout-modal__actions {
  display: flex;
  gap: 10px;
  width: 100%;
}

.logout-btn-cancel {
  flex: 1;
  padding: 11px 20px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  font-family: inherit;
}
.logout-btn-cancel:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #1e293b;
}

.logout-btn-confirm {
  flex: 1;
  padding: 11px 20px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-family: inherit;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}
.logout-btn-confirm:hover {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
  transform: translateY(-1px);
}
.logout-btn-confirm:active { transform: translateY(0); }

/* Single-transition approach: backdrop fade + modal bounce via descendant selector */
.logout-fade-enter-active {
  transition: opacity 0.25s ease;
}
.logout-fade-leave-active {
  transition: opacity 0.2s ease;
}
.logout-fade-enter-from,
.logout-fade-leave-to {
  opacity: 0;
}

/* Modal bounces in when backdrop enters */
.logout-fade-enter-active .logout-modal {
  animation: logoutBounceIn 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

/* Modal shrinks out when backdrop leaves */
.logout-fade-leave-active .logout-modal {
  animation: logoutBounceOut 0.2s ease-in both;
}

@keyframes logoutBounceIn {
  0%   { opacity: 0; transform: scale(0.6) translateY(30px); }
  55%  { opacity: 1; transform: scale(1.06) translateY(-6px); }
  75%  { transform: scale(0.96) translateY(3px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}

@keyframes logoutBounceOut {
  0%   { opacity: 1; transform: scale(1) translateY(0); }
  100% { opacity: 0; transform: scale(0.8) translateY(16px); }
}

/* Global Print Styles (Shared across all pages) */
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
  .no-print, aside, nav, header, .sidebar, .navbar, .mobile-overlay {
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
    border: none !important;
    box-shadow: none !important;
  }
}
</style>
