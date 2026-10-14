<template>
    <Head title="Invitation" />

    <AuthShell icon="🔗" title="Invitation unavailable" subtitle="This link can't be used.">
        <div class="space-y-5 text-center">
            <p class="text-slate-300">
                This invitation link is invalid, has expired, or was already used.
            </p>
            <p class="text-sm text-slate-400">
                Ask your administrator to send you a new invitation.
            </p>
            <Link
                href="/login"
                class="inline-block rounded-lg border border-white/15 bg-white/10 px-6 py-2.5 text-sm font-medium !text-white transition-colors hover:bg-white/20"
            >
                Go to sign in
            </Link>
        </div>
    </AuthShell>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AuthShell from '@/Components/AuthShell.vue'
import Link from '@/Components/Link.vue'
</script>