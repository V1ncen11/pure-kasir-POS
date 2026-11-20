<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    avatarUrl: { type: String, default: null },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

// ── Avatar Upload ─────────────────────────────────
const avatarPreview   = ref(props.avatarUrl);
const avatarInput     = ref(null);
const avatarUploading = ref(false);

watch(() => props.avatarUrl, (newVal) => {
    avatarPreview.value = newVal;
});

function triggerAvatarSelect() {
    avatarInput.value?.click();
}

function onAvatarChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran foto tidak boleh melebihi 2MB!');
        return;
    }

    // Preview lokal dulu
    const reader = new FileReader();
    reader.onload = (event) => {
        avatarPreview.value = event.target.result;
    };
    reader.readAsDataURL(file);

    // Upload ke server
    const data = new FormData();
    data.append('avatar', file);
    avatarUploading.value = true;

    router.post(route('profile.avatar'), data, {
        forceFormData: true,
        onFinish: () => { avatarUploading.value = false; },
    });
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Informasi Profil</h2>
            <p class="mt-1 text-sm text-gray-600">
                Perbarui informasi profil dan alamat email akun Anda.
            </p>
        </header>

        <!-- Avatar / Foto Profil -->
        <div class="mt-6 flex items-center gap-5">
            <!-- Foto atau Inisial -->
            <div class="relative">
                <div v-if="avatarPreview"
                    class="w-20 h-20 rounded-2xl overflow-hidden ring-2 ring-primary-200 ring-offset-2 shadow-md">
                    <img :src="avatarPreview" alt="Foto profil" class="w-full h-full object-cover" />
                </div>
                <div v-else
                    class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center ring-2 ring-primary-200 ring-offset-2 shadow-md">
                    <span class="text-white font-bold text-2xl">{{ getInitials(user.name) }}</span>
                </div>
                <!-- Badge loading -->
                <div v-if="avatarUploading"
                    class="absolute inset-0 rounded-2xl bg-black/40 flex items-center justify-center">
                    <svg class="animate-spin w-6 h-6 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
            </div>

            <!-- Action upload -->
            <div>
                <input
                    ref="avatarInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="onAvatarChange"
                />
                <button
                    type="button"
                    @click="triggerAvatarSelect"
                    :disabled="avatarUploading"
                    class="px-4 py-2 text-xs font-bold text-primary-700 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-xl transition disabled:opacity-50"
                >
                    {{ avatarUploading ? 'Mengupload...' : 'Ganti Foto Profil' }}
                </button>
                <p class="mt-1.5 text-xs text-gray-400">JPG, PNG, atau WEBP. Maks 2MB.</p>
                <p v-if="status === 'avatar-updated'" class="mt-1 text-xs font-semibold text-green-600">
                    ✓ Foto profil berhasil diperbarui!
                </p>
            </div>
        </div>

        <form @submit.prevent="form.patch(route('profile.update'))" class="mt-6 space-y-6">
            <div>
                <InputLabel for="name" value="Nama Lengkap" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Alamat Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Email Anda belum diverifikasi.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        Klik di sini untuk kirim ulang email verifikasi.
                    </Link>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                    Link verifikasi baru telah dikirimkan ke email Anda.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful || status === 'profile-updated'" class="text-sm text-gray-600">
                        ✓ Tersimpan.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
