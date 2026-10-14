<template>
    <AdminLayout>
        <Head title="Integrations" />

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Integrations</h1>
            <p class="mt-2 max-w-3xl text-slate-400">
                These are the <strong class="text-slate-200">platform-wide apps</strong>. Every organization connects its
                accounts through them, unless the organization has been allowed to register its own app.
            </p>
            <p class="mt-3 inline-flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3 py-1.5 text-sm text-emerald-200">
                <LockClosedIcon class="h-4 w-4" />
                Values are encrypted in the database. Secrets are never shown again after saving.
            </p>
        </div>

        <div class="grid items-start gap-8 lg:grid-cols-[16rem_1fr]">
            <!-- Provider navigation -->
            <aside class="lg:sticky lg:top-24">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md">
                    <div class="mb-1 flex items-center justify-between text-xs uppercase tracking-wide text-slate-400">
                        <span>Setup progress</span>
                        <span>{{ configuredCount }} / {{ providers.length }}</span>
                    </div>
                    <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-1.5 rounded-full bg-gradient-to-r from-violet-500 to-emerald-400 transition-all duration-500"
                            :style="{ width: progress + '%' }"
                        ></div>
                    </div>

                    <nav class="flex flex-wrap gap-2 lg:flex-col lg:gap-1">
                        <button
                            v-for="p in providers"
                            :key="p.key"
                            @click="jump(p.key)"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                        >
                            <span class="flex-1 truncate">{{ p.label }}</span>
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dot(p)"></span>
                        </button>
                    </nav>
                </div>
            </aside>

            <!-- Provider cards -->
            <div class="space-y-5">
                <CredentialForm
                    v-for="p in providers"
                    :key="p.key"
                    :ref="setRef(p.key)"
                    :provider="p"
                    :urls="urls(p)"
                    accent="violet"
                    collapsible
                    :default-open="p.key === firstOpenKey"
                />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CredentialForm from '@/Components/CredentialForm.vue'

const props = defineProps({ providers: Array })

const urls = (p) => ({
    update: `/admin/credentials/${p.key}`,
    test: `/admin/credentials/${p.key}/test`,
    destroy: `/admin/credentials/${p.key}`,
})

const configuredCount = computed(() => props.providers.filter((p) => p.credential?.configured && p.credential?.is_active).length)
const progress = computed(() => (props.providers.length ? Math.round((configuredCount.value / props.providers.length) * 100) : 0))

// open the first provider that still needs setting up (or the first one)
const firstOpenKey = computed(() => (props.providers.find((p) => !p.credential?.configured) ?? props.providers[0])?.key)

const dot = (p) => {
    const c = p.credential
    if (!c) return 'bg-slate-600'
    if (!c.is_active || !c.configured) return 'bg-yellow-400'

    return 'bg-emerald-400'
}

// sidebar click: expand that card and scroll to it
const forms = ref({})
const setRef = (key) => (el) => {
    if (el) forms.value[key] = el
}

const jump = async (key) => {
    forms.value[key]?.expand()
    await nextTick()
    document.getElementById(`provider-${key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => {
    const hash = window.location.hash.replace('#', '')
    if (hash) jump(hash)
})
</script>