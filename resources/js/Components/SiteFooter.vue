<template>
    <footer class="border-t border-white/10 bg-[#030512]/70 backdrop-blur-md">
        <!-- Compact (login / auth pages) -->
        <div
            v-if="compact"
            class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-6 py-5 text-xs text-slate-400 sm:flex-row"
        >
            <p class="text-center sm:text-left">
                Built for
                <a v-if="site.arihant.url" :href="site.arihant.url" target="_blank" rel="noopener noreferrer" class="font-medium text-slate-200 hover:text-blue-300">{{ site.arihant.name }}</a>
                <span v-else class="font-medium text-slate-200">{{ site.arihant.name }}</span>
                · Powered by
                <a :href="site.intouch.url" target="_blank" rel="noopener noreferrer" class="font-medium text-slate-200 hover:text-blue-300">{{ site.intouch.name }}</a>
            </p>
        </div>

        <!-- Full (landing page) -->
        <template v-else>
            <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 md:grid-cols-3">
                <div>
                    <h4 class="mb-3 text-lg font-bold text-white">{{ site.name }}</h4>
                    <p class="text-sm text-slate-400">
                        AI-powered comment analysis, lead detection and automated replies for your social channels.
                    </p>
                </div>

                <div class="space-y-5 text-sm">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wider text-slate-500">Built for</p>
                        <a v-if="site.arihant.url" :href="site.arihant.url" target="_blank" rel="noopener noreferrer" class="font-semibold text-white hover:text-blue-300">{{ site.arihant.name }}</a>
                        <span v-else class="font-semibold text-white">{{ site.arihant.name }}</span>
                        <a v-if="site.arihant.url && site.arihant.label" :href="site.arihant.url" target="_blank" rel="noopener noreferrer" class="block text-slate-400 hover:text-blue-300">{{ site.arihant.label }}</a>
                    </div>
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wider text-slate-500">Powered by</p>
                        <a :href="site.intouch.url" target="_blank" rel="noopener noreferrer" class="font-semibold text-white hover:text-blue-300">{{ site.intouch.name }}</a>
                        <a :href="site.intouch.url" target="_blank" rel="noopener noreferrer" class="block text-slate-400 hover:text-blue-300">{{ site.intouch.label }}</a>
                    </div>
                </div>

                <div class="text-sm">
                    <p class="mb-1 text-xs uppercase tracking-wider text-slate-500">Developer contact</p>
                    <a :href="`mailto:${site.contactEmail}`" class="inline-flex items-center gap-2 font-medium text-white hover:text-blue-300">{{ site.contactEmail }}</a>
                    <p class="mt-3 text-slate-400">Questions, access requests or support? Write to us.</p>
                </div>
            </div>

            <div class="border-t border-white/5 px-6 py-5 text-center text-xs text-slate-500">
                © {{ year }} {{ site.intouch.name }}. All rights reserved. · Built for {{ site.arihant.name }}.
            </div>
        </template>
    </footer>
</template>

<script setup>
import { site } from '@/site'

defineProps({ compact: { type: Boolean, default: false } })

const year = new Date().getFullYear()
</script>