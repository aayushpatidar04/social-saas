<template>
    <AdminLayout>
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Integrations</h1>
            <p class="text-slate-400 max-w-3xl">
                These are the <strong class="text-slate-200">platform-wide apps</strong>. Every organization connects its
                accounts through them, unless the organization has been allowed to register its own app.
                Values are encrypted in the database; secrets are never shown again after saving.
            </p>
        </div>

        <div class="space-y-6">
            <CredentialForm v-for="provider in providers" :key="provider.key" :provider="provider" :urls="urls(provider)" />
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CredentialForm from '@/Components/CredentialForm.vue'

defineProps({ providers: Array })

const urls = (p) => ({
    update: `/admin/credentials/${p.key}`,
    test: `/admin/credentials/${p.key}/test`,
    destroy: `/admin/credentials/${p.key}`,
})
</script>
