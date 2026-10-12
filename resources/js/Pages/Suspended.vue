<template>
    <AuthLayout title="Access paused">
        <div class="text-center space-y-6">
            <div class="text-5xl">⏸️</div>
            <p class="text-slate-200">{{ reason }}</p>
            <p class="text-sm text-slate-400">
                Please contact your administrator or the platform owner to restore access.
            </p>
            <button @click="logout" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-white">
                Sign out
            </button>
        </div>
    </AuthLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineProps({ reason: { type: String, default: 'Your organization is not active.' } })

const logout = () => router.post('/logout')
</script>
