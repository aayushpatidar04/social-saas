<template>
    <div
        class="relative min-h-screen overflow-x-hidden bg-[#050816] text-white"
    >
        <VideoBackground />

        <div class="relative z-10 flex min-h-screen flex-col">
            <header
                class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-5"
            >
                <Link href="/" class="flex items-center gap-3 !text-white">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-lg shadow-lg shadow-blue-600/30"
                        >💬</span
                    >
                    <span class="text-lg font-bold tracking-tight">{{
                        site.name
                    }}</span>
                </Link>
                <Link
                    href="/"
                    class="text-sm text-slate-300 transition-colors hover:text-white"
                    >← Back to home</Link
                >
            </header>

            <main class="flex flex-1 items-center justify-center px-4 py-10">
                <div class="w-full max-w-md">
                    <div
                        class="rounded-2xl border border-white/10 bg-[#0b1230]/70 p-8 shadow-2xl shadow-black/40 backdrop-blur-xl"
                    >
                        <div class="mb-8 text-center">
                            <h1 class="text-3xl font-bold tracking-tight">
                                {{ title }}
                            </h1>
                            <p
                                v-if="subtitle"
                                class="mt-2 text-sm text-slate-400"
                            >
                                {{ subtitle }}
                            </p>
                        </div>

                        <slot />
                    </div>

                    <div class="mt-6 text-center text-xs text-slate-500">
                        <slot name="below" />
                    </div>
                </div>
            </main>

            <SiteFooter compact />
        </div>
    </div>
</template>

<script setup>
import Link from "@/Components/Link.vue";
import VideoBackground from "@/Components/VideoBackground.vue";
import SiteFooter from "@/Components/SiteFooter.vue";
import { site } from "@/site";

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
});
</script>
