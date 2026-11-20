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

// Auto close mobile sidebar on navigation
watch(() => page.url, () => {
  mobileOpen.value = false
})

// Lock body scroll when mobile sidebar is open
watch(mobileOpen, (val) => {
  if (val) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

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
      @touchmove.prevent
    />

    <!-- Sidebar -->
    <aside
      class="sidebar"
      :class="{ 'sidebar--collapsed': !sidebarOpen, 'sidebar--mobile-open': mobileOpen }"
    >
      <!-- Logo / Store Name -->
      <div class="sidebar__header">
        <div class="sidebar__logo" :style="settings?.store_logo ? (sidebarOpen ? 'background: transparent; width: 112px; height: 72px; min-width: 112px;' : 'background: transparent; width: 40px; height: 40px; min-width: 40px;') : ''">
          <img v-if="settings?.store_logo" :src="`/storage/${settings.store_logo}`" :alt="storeName" class="w-full h-full object-contain" />
          <Store v-else :size="22" />
        </div>
        <Transition name="fade-slide">
          <div v-if="sidebarOpen" class="sidebar__brand">
            <span class="sidebar__brand-name">
              {{ storeName }}
            </span>
          </div>
        </Transition>
        <button class="sidebar__toggle" @click="sidebarOpen = !sidebarOpen" :class="{ 'is-collapsed': !sidebarOpen }" title="Toggle Sidebar">
          <component :is="sidebarOpen ? Menu : ChevronRight" :size="18" />
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
  min-height: 100dvh;
  background-color: #edf2f8;
}

/* ── Sidebar ── */
.sidebar {
  width: 260px;
  height: 100vh;
  height: 100dvh;
  bottom: 0;
  background-color: #657f9b;
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 50;
  overflow: hidden;
  border-right: 1px solid #526d8a;
  box-shadow: 2px 0 12px rgba(25, 42, 60, 0.12);
}

.sidebar--collapsed {
  width: 72px;
}
.sidebar--collapsed .sidebar__header {
  min-height: 56px;
  padding: 8px;
}
.sidebar--collapsed .sidebar__logo {
  display: none;
}
.sidebar--collapsed .sidebar__toggle {
  top: 50%;
  left: 50%;
  right: auto;
  transform: translate(-50%, -50%);
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
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  gap: 8px;
  padding: 16px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.16);
  min-height: 0;
}

.sidebar__logo {
  width: 40px;
  height: 40px;
  min-width: 40px;
  background: linear-gradient(135deg, #7187a1, #526d8a);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.sidebar__brand { width: 100%; flex: none; overflow: hidden; text-align: center; margin-top: -8px; }
.sidebar__brand-name {
  display: block;
  color: #ffffff;
  font-weight: 800;
  font-size: 0.95rem;
  line-height: 1.25;
  letter-spacing: -0.02em;
  white-space: normal;
  overflow-wrap: anywhere;
}
.sidebar__brand-sub {
  display: block;
  color: #d5dfeb;
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
  background: transparent;
  border: none;
  color: #d5dfeb;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  position: absolute;
  top: 12px;
  right: 12px;
}
.sidebar__toggle:hover {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
}

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
  overscroll-behavior: contain;
}

.sidebar__nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  color: #e3eaf2;
  text-decoration: none;
  transition: all 0.15s ease;
  margin-bottom: 2px;
  white-space: nowrap;
}
.sidebar__nav-item:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
}
.sidebar__nav-item--active {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  font-weight: 600;
}
.sidebar__nav-item--active .sidebar__nav-icon {
  color: #ffffff;
}

.sidebar__nav-icon {
  width: 22px;
  min-width: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c8d5e3;
}
.sidebar__nav-item:hover .sidebar__nav-icon {
  color: #ffffff;
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
  border-top: 1px solid rgba(255, 255, 255, 0.16);
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
  background: #edf2f6;
  border: 1px solid #d5dfe8;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #526d8a;
  transition: all 0.2s ease;
}
.sidebar__user-avatar:hover {
  background: #e3eaf1;
  color: #526d8a;
  border-color: #cedae5;
}

.sidebar__user-info { 
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden; 
}
.sidebar__user-name {
  display: block;
  color: #ffffff;
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
  color: #d5dfeb;
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
  background: #526d8a;
  border-bottom: 1px solid #435a74;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 40;
  box-shadow: 0 2px 8px rgba(46, 39, 87, 0.18);
}

.navbar__left { display: flex; align-items: center; gap: 16px; }

.navbar__mobile-menu {
  display: none;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.22);
  color: #ffffff;
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
  color: #ffffff;
  letter-spacing: -0.01em;
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
  background: transparent;
  border: 1px solid transparent;
  color: #fde68a;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
}
.navbar__alert-btn:hover { background: rgba(255, 255, 255, 0.1); }

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
  background: #edf2f6;
  border: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #526d8a;
  transition: all 0.2s ease;
}
.navbar__user-avatar:hover {
  background: #ffffff;
  color: #435a74;
  border-color: #ffffff;
}
.navbar__user-meta { display: flex; flex-direction: column; }
.navbar__user-name { font-size: 0.8rem; font-weight: 600; color: #ffffff; }
.navbar__user-role { font-size: 0.7rem; color: #c8d5e8; }

@media (max-width: 640px) {
  .navbar__user-meta { display: none; }
}

/* ── Page Content ── */
.page-content {
  flex: 1;
  padding: 22px;
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
