<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-white mb-2">Integrations</h1>
            <p class="text-slate-400 mb-8">
                Register <strong class="text-slate-200">your own</strong> developer apps so accounts connect through your brand
                and your own API limits. Leave a provider empty to keep using the platform's shared app.
            </p>

            <div v-if="!enabled" class="p-6 bg-slate-800 border border-slate-700 rounded-lg text-slate-300">
                Custom apps are not enabled for your organization. Your accounts connect through the platform's apps.
                Contact the platform owner if you need your own.
            </div>

            <template v-else>
                <p v-if="!canEdit" class="mb-6 p-4 bg-yellow-900/30 border border-yellow-700 rounded-lg text-yellow-200 text-sm">
                    Only organization admins can change these settings.
                </p>
                <div class="space-y-6" :class="!canEdit && 'pointer-events-none opacity-60'">
                    <div v-for="provider in providers" :key="provider.key">
                        <p v-if="!provider.credential && provider.platform_default_available" class="text-xs text-slate-500 mb-2">
                            Currently using the platform's shared {{ provider.label }} app.
                        </p>
                        <CredentialForm :provider="provider" :urls="urls(provider)" />
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import CredentialForm from '@/Components/CredentialForm.vue'

defineProps({ enabled: Boolean, canEdit: Boolean, providers: Array })

const urls = (p) => ({
    update: `/settings/integrations/${p.key}`,
    test: `/settings/integrations/${p.key}/test`,
    destroy: `/settings/integrations/${p.key}`,
})
</script>
