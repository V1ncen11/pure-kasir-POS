<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import { Head } from '@inertiajs/vue3'

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    avatarUrl: { type: String, default: null },
})
</script>

<template>
    <Head title="Profil Saya" />

    <AppLayout>
        <template #title>Profil Saya</template>

        <div class="max-w-3xl mx-auto space-y-6">

            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Profil Saya</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola informasi akun dan foto profil Anda.
                </p>
            </div>

            <!-- Info Profil -->
            <div class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-3xl p-6 shadow-sm">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    :avatar-url="avatarUrl"
                />
            </div>

        </div>
    </AppLayout>
</template>
