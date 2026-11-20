<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { ref, watch } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';

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

const showPassword = ref(false);

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
    <Head title="Masuk - Warung Teh Ucu" />

    <div class="min-h-screen w-full flex bg-white text-slate-800 overflow-x-hidden font-sans">

        <!-- Left Side: Real-world Store Imagery Showcase (Hidden on Mobile) -->
        <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 relative overflow-hidden bg-primary-900 select-none">
            <!-- Full-bleed background photo -->
            <img
                src="/images/login_hero.png"
                alt="Modern Retail Store Checkout"
                class="absolute inset-0 w-full h-full object-cover opacity-80"
            />
            <!-- Refined high-contrast linear overlay (Sky/Blue hues) -->
            <div class="absolute inset-0 bg-gradient-to-t from-primary-900 via-primary-900/50 to-primary-900/20 pointer-events-none"></div>

            <!-- Top Brand Header / Logo -->
            <div class="flex items-center z-10">
                <div>

                    <p class="text-[10px] font-semibold text-primary-200 tracking-widest uppercase leading-none mt-1">MY POS</p>
                </div>
            </div>

            <!-- Bottom Tagline & Brand Statement -->
            <div class="z-10 mt-auto max-w-lg">
                <h2 class="text-3xl font-bold text-white tracking-tight leading-tight">
                    Mendukung operasional ritel, kuliner, dan butik modern.
                </h2>
                <p class="mt-2.5 text-primary-100 text-sm leading-relaxed">
                    Catat penjualan, pantau stok barang, dan lihat laporan keuangan dalam satu aplikasi kasir terintegrasi.
                </p>

                <div class="mt-8 flex items-center space-x-4 text-xs text-primary-300/80 border-t border-white/10 pt-4">
                    <span>&copy; 2026 Kasir POS.</span>
                    <span>&bull;</span>
                    <span>Sistem Aman & Terenkripsi.</span>
                </div>
            </div>
        </div>

        <!-- Right Side: Luxury Minimalist Form -->
        <div class="w-full lg:w-1/2 min-h-screen flex items-center justify-center px-6 py-24 sm:px-12 lg:px-20 bg-white relative">

            <!-- Mobile Brand Header (Visible only on lg:hidden) -->
            <div class="absolute top-6 left-6 flex items-center lg:hidden">
                <span class="text-base font-bold text-primary-900 tracking-tight">Warung Teh Ucu</span>
            </div>

            <!-- Login Form -->
            <div class="w-full max-w-[790px]">

                <!-- Form Header -->
                <div class="text-center mb-9">
                    <h2 class="text-2xl sm:text-3xl font-bold text-slate-950 tracking-tight">
                        Masuk ke Kasir POS
                    </h2>
                    <p class="text-slate-500 text-base mt-2 leading-relaxed">
                        Masukkan email dan kata sandi untuk melanjutkan.
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
                        <label for="email" class="text-[11px] font-bold text-primary-900/70 tracking-wider uppercase block">
                            Alamat Email
                        </label>
                        <div class="relative group">
                            <!-- Input Icon -->
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                class="w-full h-[62px] bg-white border border-slate-300 text-slate-900 rounded-xl px-5 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200 text-base hover:border-slate-400"
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
                            <label for="password" class="text-[11px] font-bold text-primary-900/70 tracking-wider uppercase block">
                                Kata Sandi
                            </label>
                        </div>
                        <div class="relative group">
                            <!-- Input Icon -->
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                class="w-full h-[62px] bg-white border border-slate-300 text-slate-900 rounded-xl px-5 pr-12 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200 text-base hover:border-slate-400"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            />
                            <!-- Toggle Password Visibility Button -->
                            <button
                                v-if="form.password"
                                type="button"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-primary-400/80 hover:text-primary-600 transition-colors focus:outline-none"
                                @click="showPassword = !showPassword"
                            >
                                <component :is="showPassword ? EyeOff : Eye" :size="18" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600 flex items-center gap-1 font-medium">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Extras: Remember me & Forgot Password -->
                    <div class="flex items-center text-sm pt-1">
                        <label class="flex items-center cursor-pointer select-none">
                            <div class="relative">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    v-model="form.remember"
                                    class="sr-only peer"
                                />
                                <div class="w-4.5 h-4.5 bg-primary-50 border border-primary-100 rounded flex items-center justify-center transition-all duration-150 peer-checked:bg-primary-600 peer-checked:border-transparent">
                                    <svg class="h-3 w-3 text-white scale-0 peer-checked:scale-100 transition-transform duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </div>
                            </div>
                            <span class="ms-2 text-xs text-slate-500 hover:text-slate-800 font-medium transition-colors">
                                Ingat saya
                            </span>
                        </label>

                    </div>

                    <!-- Sign In Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 px-5 rounded-xl shadow-sm transition-all duration-200 flex items-center justify-center space-x-2 text-base disabled:opacity-50 disabled:cursor-not-allowed group/btn"
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

                <div class="mt-7 text-center">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-base text-primary-600 hover:text-primary-800 transition-colors font-semibold"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>
            </div>
        </div>

    </div>
</template>


