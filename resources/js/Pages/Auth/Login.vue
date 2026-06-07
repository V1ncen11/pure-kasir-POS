<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { watch } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    sessionKicked: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const toast = useToast();

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk - Toko Amir Udin" />

    <div class="min-h-screen w-full flex bg-[#f1f6fb] text-slate-800 overflow-x-hidden font-sans">
        
        <!-- Left Side: Real-world Store Imagery Showcase (Hidden on Mobile) -->
        <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 relative overflow-hidden bg-blue-950 select-none">
            <!-- Full-bleed background photo -->
            <img 
                src="/images/login_hero.png" 
                alt="Modern Retail Store Checkout" 
                class="absolute inset-0 w-full h-full object-cover opacity-80"
            />
            <!-- Refined high-contrast linear overlay (Sky/Blue hues) -->
            <div class="absolute inset-0 bg-gradient-to-t from-blue-950 via-blue-900/50 to-blue-900/20 pointer-events-none"></div>

            <!-- Top Brand Header / Logo -->
            <div class="flex items-center space-x-3.5 z-10">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 backdrop-blur-md border border-white/20 p-2 shadow-lg">
                    <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-extrabold tracking-tight leading-tight">
                        <span class="text-white">Toko</span>
                        <span class="text-sky-300 ml-1.5">Amir</span>
                        <span class="text-cyan-400 ml-1.5">Udin</span>
                    </h1>
                    <p class="text-[10px] font-semibold text-blue-200 tracking-widest uppercase leading-none">Aplikasi Kasir Pintar</p>
                </div>
            </div>

            <!-- Bottom Tagline & Brand Statement -->
            <div class="z-10 mt-auto max-w-lg">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-medium bg-white/10 backdrop-blur-md border border-white/10 text-white mb-4">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                    Sistem Kasir Aktif
                </span>
                <h2 class="text-3xl font-bold text-white tracking-tight leading-tight">
                    Mendukung operasional ritel, kuliner, dan butik modern.
                </h2>
                <p class="mt-2.5 text-blue-100 text-sm leading-relaxed">
                    Catat penjualan, pantau stok barang, dan lihat laporan keuangan dalam satu aplikasi kasir terintegrasi.
                </p>
                
                <div class="mt-8 flex items-center space-x-4 text-xs text-blue-300/80 border-t border-white/10 pt-4">
                    <span>&copy; 2026 Kasir POS.</span>
                    <span>&bull;</span>
                    <span>Sistem Aman & Terenkripsi.</span>
                </div>
            </div>
        </div>

        <!-- Right Side: Luxury Minimalist Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 md:p-16 bg-[#f1f6fb] relative overflow-hidden">
            
            <!-- Decorative background blobs -->
            <div class="absolute -top-12 -right-12 w-96 h-96 bg-blue-300/30 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-12 -left-12 w-96 h-96 bg-sky-300/20 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Mobile Brand Header (Visible only on lg:hidden) -->
            <div class="absolute top-6 left-6 flex items-center space-x-2.5 lg:hidden z-10">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-sky-500 p-2 shadow-sm">
                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    </svg>
                </div>
                <span class="text-base font-bold text-blue-900 tracking-tight">Kasir POS</span>
            </div>

            <!-- Login Card -->
            <div class="w-full max-w-md bg-white/90 backdrop-blur-lg border border-blue-100/80 rounded-2xl p-8 sm:p-10 shadow-2xl shadow-blue-500/10 transition-all duration-300 z-10 hover:shadow-blue-500/15">
                
                <!-- Form Header -->
                <div class="text-left">
                    <h2 class="text-2xl sm:text-3xl font-bold text-blue-950 tracking-tight">
                        Masuk
                    </h2>
                    <p class="text-slate-500 text-sm mt-2 leading-relaxed">
                        Masukkan kredensial Anda untuk mengakses terminal kasir.
                    </p>
                </div>

                <!-- Session status message -->
                <div v-if="status" class="mt-4 mb-2 p-3.5 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-medium text-emerald-800">
                    {{ status }}
                </div>

                <!-- Session kicked warning -->
                <div v-if="sessionKicked" class="mt-4 mb-2 p-3.5 rounded-xl border border-amber-300 bg-amber-50 flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.538-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-xs font-bold text-amber-800">Sesi Anda Telah Diakhiri</p>
                        <p class="text-xs text-amber-700 mt-0.5">Akun ini diakses dari perangkat lain. Silakan masuk kembali.</p>
                    </div>
                </div>

                <!-- Main Form -->
                <form @submit.prevent="submit" class="mt-8 space-y-5">
                    
                    <!-- Email field -->
                    <div class="space-y-1.5">
                        <label for="email" class="text-[11px] font-bold text-blue-900/70 tracking-wider uppercase block">
                            Alamat Email
                        </label>
                        <div class="relative group">
                            <!-- Input Icon -->
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="h-4.5 w-4.5 text-blue-400/80 group-focus-within:text-blue-600 transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </div>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                class="w-full bg-blue-50/30 border border-blue-100/80 text-slate-900 rounded-xl py-3 pl-11 pr-4 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-200 text-sm hover:border-blue-200"
                                placeholder="nama@toko.com"
                                required
                                autofocus
                                autocomplete="username"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600 flex items-center gap-1 font-medium">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password field -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="password" class="text-[11px] font-bold text-blue-900/70 tracking-wider uppercase block">
                                Kata Sandi
                            </label>
                        </div>
                        <div class="relative group">
                            <!-- Input Icon -->
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="h-4.5 w-4.5 text-blue-400/80 group-focus-within:text-blue-600 transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <input
                                id="password"
                                type="password"
                                v-model="form.password"
                                class="w-full bg-blue-50/30 border border-blue-100/80 text-slate-900 rounded-xl py-3 pl-11 pr-4 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-200 text-sm hover:border-blue-200"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600 flex items-center gap-1 font-medium">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Extras: Remember me & Forgot Password -->
                    <div class="flex items-center justify-between text-sm pt-1">
                        <label class="flex items-center cursor-pointer select-none">
                            <div class="relative">
                                <input 
                                    type="checkbox" 
                                    name="remember" 
                                    v-model="form.remember" 
                                    class="sr-only peer"
                                />
                                <div class="w-4.5 h-4.5 bg-blue-50 border border-blue-100 rounded flex items-center justify-center transition-all duration-150 peer-checked:bg-blue-600 peer-checked:border-transparent">
                                    <svg class="h-3 w-3 text-white scale-0 peer-checked:scale-100 transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </div>
                            </div>
                            <span class="ms-2 text-xs text-slate-500 hover:text-slate-800 font-medium transition-colors">
                                Ingat saya
                            </span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs text-blue-600 hover:text-blue-800 transition-colors font-semibold"
                        >
                            Lupa kata sandi?
                        </Link>
                    </div>

                    <!-- Sign In Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-700 hover:to-sky-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/35 transition-all duration-200 hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center space-x-2 text-xs uppercase tracking-wider disabled:opacity-50 disabled:cursor-not-allowed group/btn"
                    >
                        <!-- Spinner Icon (Visible when loading) -->
                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        
                        <span v-if="!form.processing" class="flex items-center justify-center space-x-2">
                            <span>Masuk</span>
                            <svg class="h-3.5 w-3.5 transform group-hover/btn:translate-x-0.5 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </span>
                        <span v-else>Memproses...</span>
                    </button>

                </form>
            </div>
        </div>

    </div>
</template>


