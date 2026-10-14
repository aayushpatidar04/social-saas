<template>
    <AppLayout>
        <Head title="Integrations" />

        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Integrations
                </h1>
                <p class="mt-1 text-slate-400">
                    Register
                    <strong class="font-semibold text-slate-200"
                        >your own</strong
                    >
                    developer apps so accounts connect through your brand and
                    your own API limits. Leave a provider empty to keep using
                    the platform's shared app.
                </p>
            </div>

            <!-- Not enabled -->
            <section
                v-if="!enabled"
                :class="[card, 'flex items-start gap-3 text-sm text-slate-300']"
            >
                <LockClosedIcon
                    class="mt-0.5 h-5 w-5 shrink-0 text-slate-400"
                />
                <p>
                    Custom apps are not enabled for your organization. Your
                    accounts connect through the platform's apps. Contact the
                    platform owner if you need your own.
                </p>
            </section>

            <template v-else>
                <div
                    v-if="!canEdit"
                    class="mb-6 flex items-start gap-3 rounded-2xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-200 backdrop-blur-md"
                >
                    <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                    <p>Only organization admins can change these settings.</p>
                </div>

                <div
                    class="space-y-6"
                    :class="!canEdit && 'pointer-events-none opacity-60'"
                >
                    <div v-for="provider in providers" :key="provider.key">
                        <p
                            v-if="
                                !provider.credential &&
                                provider.platform_default_available
                            "
                            class="mb-2 text-xs text-slate-500"
                        >
                            Currently using the platform's shared
                            {{ provider.label }} app.
                        </p>
                        <CredentialForm
                            :provider="provider"
                            :urls="urls(provider)"
                        />
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import {
    ExclamationTriangleIcon,
    LockClosedIcon,
} from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import CredentialForm from "@/Components/CredentialForm.vue";

defineProps({ enabled: Boolean, canEdit: Boolean, providers: Array });

// shared card token (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const urls = (p) => ({
    update: `/settings/integrations/${p.key}`,
    test: `/settings/integrations/${p.key}/test`,
    destroy: `/settings/integrations/${p.key}`,
});
</script>
