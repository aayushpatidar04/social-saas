<template>
    <AppLayout>
        <Head title="Analytics" />

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                Analytics
            </h1>
            <p class="mt-1 text-slate-400">
                Social media insights powered by AI
            </p>
        </div>

        <!-- Summary cards -->
        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div
                v-for="s in stats"
                :key="s.label"
                class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:-translate-y-0.5 hover:border-white/20 hover:bg-white/10"
            >
                <div
                    class="absolute -right-6 -top-6 h-20 w-20 rounded-full opacity-20 blur-2xl"
                    :class="s.glow"
                ></div>
                <component :is="s.icon" class="mb-3 h-6 w-6" :class="s.tone" />
                <p
                    class="text-xs font-medium uppercase tracking-wide text-slate-400"
                >
                    {{ s.label }}
                </p>
                <p class="mt-1 text-3xl font-bold tabular-nums" :class="s.tone">
                    {{ s.value }}
                </p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Sentiment -->
            <section :class="card">
                <h3 class="mb-5 text-lg font-bold">Sentiment distribution</h3>
                <p v-if="!sentimentRows.length" class="text-sm text-slate-400">
                    No data yet.
                </p>
                <div v-else class="space-y-4">
                    <div v-for="row in sentimentRows" :key="row.key">
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span class="flex items-center gap-2 capitalize text-slate-300">
                                <span
                                    class="h-2.5 w-2.5 rounded-full"
                                    :style="{ background: row.color }"
                                ></span>
                                {{ row.key }}
                            </span>
                            <span class="font-semibold tabular-nums">{{
                                row.count
                            }}</span>
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-2 rounded-full transition-all duration-500"
                                :style="{
                                    width: row.percentage + '%',
                                    background: row.color,
                                }"
                            ></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Platforms -->
            <section :class="card">
                <h3 class="mb-5 text-lg font-bold">By platform</h3>
                <p v-if="!platformRows.length" class="text-sm text-slate-400">
                    No data yet.
                </p>
                <div v-else class="space-y-4">
                    <div v-for="row in platformRows" :key="row.key">
                        <div class="mb-1.5 flex justify-between text-sm">
                            <span class="capitalize text-slate-300">{{
                                row.key
                            }}</span>
                            <span class="font-semibold tabular-nums">{{
                                row.count
                            }}</span>
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-2 rounded-full transition-all duration-500"
                                :class="platformColor(row.key)"
                                :style="{ width: row.percentage + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import {
    ChatBubbleLeftRightIcon,
    CheckBadgeIcon,
    FaceSmileIcon,
    UserPlusIcon,
} from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({
            summary: {
                total_comments: 0,
                response_rate: 0,
                total_leads: 0,
                avg_sentiment_score: 0,
            },
            sentiment_distribution: {
                positive: { count: 0, percentage: 0 },
                neutral: { count: 0, percentage: 0 },
                negative: { count: 0, percentage: 0 },
                pending: { count: 0, percentage: 0 },
            },
            by_platform: {},
        }),
    },
});

const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

// all defensive: a missing key must not break the page
const summary = computed(() => props.metrics?.summary ?? {});
const n = (v) => Number(v ?? 0).toLocaleString();

const stats = computed(() => [
    {
        label: "Total comments",
        value: n(summary.value.total_comments),
        icon: ChatBubbleLeftRightIcon,
        tone: "text-white",
        glow: "bg-slate-300",
    },
    {
        label: "Response rate",
        value: `${summary.value.response_rate ?? 0}%`,
        icon: CheckBadgeIcon,
        tone: "text-green-400",
        glow: "bg-green-400",
    },
    {
        label: "Total leads",
        value: n(summary.value.total_leads),
        icon: UserPlusIcon,
        tone: "text-blue-400",
        glow: "bg-blue-400",
    },
    {
        label: "Avg sentiment",
        value: `${summary.value.avg_sentiment_score ?? 0}/100`,
        icon: FaceSmileIcon,
        tone: "text-purple-400",
        glow: "bg-purple-400",
    },
]);

const SENTIMENT_COLORS = {
    positive: "#22c55e",
    neutral: "#64748b",
    negative: "#ef4444",
    pending: "#eab308",
};

const sentimentRows = computed(() =>
    Object.entries(props.metrics?.sentiment_distribution ?? {}).map(
        ([key, item]) => ({
            key,
            count: item?.count ?? 0,
            percentage: item?.percentage ?? 0,
            color: SENTIMENT_COLORS[key] ?? "#64748b",
        }),
    ),
);

const platformRows = computed(() =>
    Object.entries(props.metrics?.by_platform ?? {}).map(([key, d]) => ({
        key,
        count: d?.count ?? 0,
        percentage: d?.percentage ?? 0,
    })),
);

const platformColor = (p) =>
    ({
        facebook: "bg-blue-500",
        instagram: "bg-pink-500",
        youtube: "bg-red-500",
        twitter: "bg-slate-300",
        linkedin: "bg-sky-600",
    })[p] ?? "bg-slate-500";
</script>