<template>
    <AppLayout>
        <Head title="Analytics" />

        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Analytics
                </h1>
                <p class="mt-1 text-slate-400">
                    Social media insights powered by AI
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                    :class="
                        live
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                            : 'border-white/10 bg-white/5 text-slate-400'
                    "
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="
                            live
                                ? 'animate-pulse bg-emerald-400'
                                : 'bg-slate-500'
                        "
                    ></span>
                    {{
                        live
                            ? "Live"
                            : hasPusher
                              ? "Connecting…"
                              : "Auto-refresh every minute"
                    }}
                </span>
                <button
                    @click="refresh()"
                    :disabled="refreshing"
                    class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10 disabled:opacity-50"
                >
                    <ArrowPathIcon
                        class="h-4 w-4"
                        :class="refreshing && 'animate-spin'"
                    />
                    Refresh
                </button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
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

        <!-- Charts row -->
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Sentiment donut -->
            <section :class="card">
                <h3 class="mb-5 text-lg font-bold">Sentiment</h3>

                <div class="flex items-center gap-6">
                    <div
                        class="relative h-32 w-32 shrink-0 rounded-full"
                        :style="{ background: donut }"
                    >
                        <div
                            class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-[#0b1230]"
                        >
                            <span class="text-2xl font-bold tabular-nums">{{
                                sentimentTotal
                            }}</span>
                            <span
                                class="text-[10px] uppercase tracking-wide text-slate-400"
                                >comments</span
                            >
                        </div>
                    </div>

                    <ul class="min-w-0 flex-1 space-y-2">
                        <li
                            v-for="row in sentimentRows"
                            :key="row.key"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <span
                                class="flex items-center gap-2 capitalize text-slate-300"
                            >
                                <span
                                    class="h-2.5 w-2.5 rounded-full"
                                    :style="{ background: row.color }"
                                ></span>
                                {{ row.key }}
                            </span>
                            <span class="tabular-nums text-slate-200">
                                {{ row.count }}
                                <span class="text-xs text-slate-500"
                                    >({{ row.percent }}%)</span
                                >
                            </span>
                        </li>
                    </ul>
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

            <!-- Lead status -->
            <section :class="card">
                <h3 class="mb-5 text-lg font-bold">Lead status</h3>
                <p v-if="!leadRows.length" class="text-sm text-slate-400">
                    No leads yet.
                </p>
                <div v-else class="space-y-4">
                    <div v-for="row in leadRows" :key="row.key">
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
                                class="h-2 rounded-full bg-emerald-500 transition-all duration-500"
                                :style="{ width: row.percent + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Activity -->
        <section :class="[card, 'mb-6']">
            <div class="mb-5 flex items-center justify-between">
                <h3 class="text-lg font-bold">
                    Activity
                    <span class="text-sm font-normal text-slate-400"
                        >(last 24 hours)</span
                    >
                </h3>
                <span class="text-sm text-slate-400"
                    >{{ activityTotal }} comments</span
                >
            </div>

            <p
                v-if="!timeline.length"
                class="py-6 text-center text-sm text-slate-400"
            >
                No activity yet.
            </p>
            <div v-else class="flex h-40 items-end gap-1.5 sm:gap-2">
                <div
                    v-for="(item, idx) in timeline"
                    :key="idx"
                    class="group flex h-full flex-1 flex-col items-center justify-end"
                    :title="`${item.time}: ${item.count}`"
                >
                    <span
                        class="mb-1 text-xs tabular-nums text-slate-300 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        {{ item.count }}
                    </span>
                    <!-- explicit pixel height: percentage heights collapse inside a flex column -->
                    <div
                        class="w-full rounded-t bg-gradient-to-t from-blue-700 to-blue-400 transition-all group-hover:from-blue-600 group-hover:to-sky-300"
                        :style="{ height: barHeight(item.count) + 'px' }"
                    ></div>
                    <span
                        class="mt-2 h-4 text-center text-[10px] text-slate-500"
                    >
                        {{ idx % 3 === 0 ? item.time : "" }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Recent comments -->
        <section :class="card">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-bold">Recent comments</h3>
                <Link href="/inbox" class="text-sm">View all →</Link>
            </div>

            <div v-if="recentComments.length === 0" class="py-10 text-center">
                <p class="text-slate-400">
                    No comments yet. Connect your first social account to get
                    started!
                </p>
                <Link
                    href="/settings/social-accounts"
                    class="mt-3 inline-block text-sm"
                    >Connect an account →</Link
                >
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="comment in recentComments"
                    :key="comment.id"
                    @click="router.visit('/inbox')"
                    class="cursor-pointer rounded-xl border border-white/10 bg-white/[0.03] p-4 transition-all hover:border-white/20 hover:bg-white/[0.07]"
                >
                    <div
                        class="mb-2 flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ comment.author_name }}
                            </p>
                            <p class="text-xs capitalize text-slate-400">
                                <span
                                    class="mr-1 inline-block h-2 w-2 rounded-full"
                                    :class="
                                        platformColor(
                                            comment.social_account?.platform,
                                        )
                                    "
                                ></span>
                                {{
                                    comment.social_account?.platform ??
                                    "unknown"
                                }}
                                · {{ timeAgo(comment.commented_at) }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="sentimentBadge(comment.sentiment)"
                            >
                                {{ comment.sentiment }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="intentBadge(comment.intent)"
                            >
                                {{ comment.intent }}
                            </span>
                        </div>
                    </div>

                    <p class="mb-3 line-clamp-2 text-sm text-slate-300">
                        {{ comment.content }}
                    </p>

                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <span>Lead score</span>
                        <div
                            class="h-1.5 w-24 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-1.5 rounded-full bg-emerald-500"
                                :style="{
                                    width: clamp(comment.lead_score) + '%',
                                }"
                            ></div>
                        </div>
                        <span class="font-semibold tabular-nums text-white">{{
                            comment.lead_score ?? 0
                        }}</span>
                    </div>
                </article>
            </div>
        </section>
    </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import Pusher from "pusher-js";
import {
    ArrowPathIcon,
    CheckBadgeIcon,
    ChatBubbleLeftRightIcon,
    FaceSmileIcon,
    InboxArrowDownIcon,
    StarIcon,
    UserPlusIcon,
} from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import Link from "@/Components/Link.vue";

const page = usePage();
const metrics = ref(page.props.metrics ?? {});
const recentComments = ref(page.props.recentComments || []);

const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

// ---------------------------------------------------------------- data (all defensive: a missing key must not break the page)
const summary = computed(() => metrics.value.summary ?? {});
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
        label: "New comments",
        value: n(summary.value.new_comments),
        icon: InboxArrowDownIcon,
        tone: "text-yellow-400",
        glow: "bg-yellow-400",
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
        label: "Qualified leads",
        value: n(summary.value.qualified_leads),
        icon: StarIcon,
        tone: "text-emerald-400",
        glow: "bg-emerald-400",
    },
    {
        label: "Avg sentiment",
        value: `${summary.value.avg_sentiment_score ?? 0}/100`,
        icon: FaceSmileIcon,
        tone: "text-purple-400",
        glow: "bg-purple-400",
    },
]);

// sentiment ----------------------------------------------------------------
const SENTIMENT_COLORS = {
    positive: "#22c55e",
    neutral: "#64748b",
    negative: "#ef4444",
    pending: "#eab308",
};

const sentimentTotal = computed(() =>
    Object.values(metrics.value.sentiment_distribution ?? {}).reduce(
        (sum, item) => sum + (item?.count ?? 0),
        0,
    ),
);

const sentimentRows = computed(() =>
    Object.entries(metrics.value.sentiment_distribution ?? {}).map(
        ([key, item]) => ({
            key,
            count: item?.count ?? 0,
            color: SENTIMENT_COLORS[key] ?? "#64748b",
            percent: sentimentTotal.value
                ? Math.round(((item?.count ?? 0) / sentimentTotal.value) * 100)
                : 0,
        }),
    ),
);

// pure-CSS donut: a conic gradient built from the percentages (no chart library needed)
const donut = computed(() => {
    if (!sentimentTotal.value) return "conic-gradient(#334155 0 100%)";
    let from = 0;
    const stops = sentimentRows.value
        .filter((r) => r.count > 0)
        .map((r) => {
            const to = from + (r.count / sentimentTotal.value) * 100;
            const stop = `${r.color} ${from}% ${to}%`;
            from = to;
            return stop;
        });
    return `conic-gradient(${stops.join(", ")})`;
});

// platforms ----------------------------------------------------------------
const platformRows = computed(() =>
    Object.entries(metrics.value.by_platform ?? {}).map(([key, d]) => ({
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

// leads (the old code divided by total_leads, which is 0 for a new organization => NaN%) --------
const leadRows = computed(() => {
    const by = metrics.value.lead_metrics?.by_status ?? {};
    const total =
        metrics.value.lead_metrics?.total_leads ||
        Object.values(by).reduce((a, b) => a + b, 0);

    return Object.entries(by).map(([key, count]) => ({
        key,
        count,
        percent: total ? Math.round((count / total) * 100) : 0,
    }));
});

// activity -----------------------------------------------------------------
const timeline = computed(() => metrics.value.activity_timeline ?? []);
const maxActivity = computed(() =>
    Math.max(...timeline.value.map((t) => t.count), 1),
);
const activityTotal = computed(() =>
    timeline.value.reduce((s, t) => s + t.count, 0),
);
const barHeight = (count) =>
    Math.max(count ? 8 : 3, Math.round((count / maxActivity.value) * 112));

// recent comments ----------------------------------------------------------
const clamp = (v) => Math.min(100, Math.max(0, Number(v ?? 0)));

const sentimentBadge = (s) =>
    ({
        positive: "bg-green-500/15 text-green-300",
        neutral: "bg-slate-500/20 text-slate-300",
        negative: "bg-red-500/15 text-red-300",
        pending: "bg-yellow-500/15 text-yellow-300",
    })[s] ?? "bg-slate-500/20 text-slate-300";

const intentBadge = (i) =>
    ({
        sales: "bg-blue-500/15 text-blue-300",
        support: "bg-purple-500/15 text-purple-300",
        complaint: "bg-red-500/15 text-red-300",
        lead: "bg-green-500/15 text-green-300",
        question: "bg-yellow-500/15 text-yellow-300",
        general: "bg-slate-500/20 text-slate-300",
    })[i] ?? "bg-slate-500/20 text-slate-300";

const timeAgo = (date) => {
    if (!date) return "";
    const s = Math.max(
        0,
        Math.floor((Date.now() - new Date(date).getTime()) / 1000),
    );
    if (s < 60) return "just now";
    if (s < 3600) return `${Math.floor(s / 60)}m ago`;
    if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
    if (s < 86400 * 7) return `${Math.floor(s / 86400)}d ago`;
    return new Date(date).toLocaleDateString();
};

// ---------------------------------------------------------------- live updates
const hasPusher = !!page.props.pusher_key;
const live = ref(false);
const refreshing = ref(false);
let pusher = null;
let timer = null;

// keep local copies in sync after a reload
watch(
    () => page.props.metrics,
    (v) => {
        if (v) metrics.value = v;
    },
);
watch(
    () => page.props.recentComments,
    (v) => {
        if (v) recentComments.value = v;
    },
);

const refresh = (silent = false) => {
    if (refreshing.value) return;
    refreshing.value = !silent;
    router.reload({
        only: ["metrics", "recentComments"],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (refreshing.value = false),
    });
};

onMounted(() => {
    if (hasPusher) {
        // The old code always created a Pusher client, which throws when no key is configured
        // and took the whole page down on installations without Pusher.
        try {
            pusher = new Pusher(page.props.pusher_key, {
                cluster: page.props.pusher_cluster,
                forceTLS: true,
            });
            pusher.connection.bind("connected", () => (live.value = true));
            ["disconnected", "unavailable", "failed"].forEach((e) =>
                pusher.connection.bind(e, () => (live.value = false)),
            );

            const orgId =
                page.props.organization?.id ??
                page.props.auth.user.organization_id;
            pusher
                .subscribe(`analytics.org.${orgId}`)
                .bind("metric.updated", (data) => {
                    if (data?.type && metrics.value[data.type] !== undefined)
                        metrics.value[data.type] = data.data;
                });
        } catch (e) {
            console.warn("Real-time updates unavailable:", e);
        }
    }

    // Without a working live connection, poll once a minute (skipped while the tab is hidden)
    timer = setInterval(() => {
        if (!live.value && !document.hidden) refresh(true);
    }, 60000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    pusher?.disconnect();
});
</script>