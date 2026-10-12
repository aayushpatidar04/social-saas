<template>
    <AuthLayout title="Invitation">
        <div class="text-center space-y-4">
            <div class="text-5xl">🔗</div>
            <p class="text-slate-200">This invitation link is invalid, has expired, or was already used.</p>
            <p class="text-sm text-slate-400">Ask your administrator to send you a new invitation.</p>
            <Link href="/login" class="inline-block mt-2">Go to sign in</Link>
        </div>
    </AuthLayout>
</template>

<script setup>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import Link from '@/Components/Link.vue'
</script>
