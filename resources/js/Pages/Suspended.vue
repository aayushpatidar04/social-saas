<template>
    <Head title="Access paused" />

    <AuthShell icon="⏸️" title="Access paused">
        <div class="space-y-5 text-center">
            <p
                class="rounded-lg border border-yellow-600/30 bg-yellow-900/20 p-4 text-yellow-100"
            >
                {{ reason }}
            </p>
            <p class="text-sm text-slate-400">
                Please contact your administrator or the platform owner to
                restore access.
            </p>
            <button
                @click="logout"
                class="rounded-lg border border-white/15 bg-white/10 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-white/20"
            >
                Sign out
            </button>
        </div>
    </AuthShell>
</template>

<script setup>
import { Head, router } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";

defineProps({
    reason: { type: String, default: "Your organization is not active." },
});

const logout = () => router.post("/logout");
</script>
