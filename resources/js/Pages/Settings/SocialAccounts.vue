<template>
    <AppLayout>
        <Head title="Social Accounts" />

        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                        Social Accounts
                    </h1>
                    <p class="mt-1 text-slate-400">
                        Connect the accounts AI should read and reply on
                    </p>
                </div>

                <!-- Plan usage -->
                <div class="w-48">
                    <div
                        class="mb-1.5 flex justify-between text-xs text-slate-400"
                    >
                        <span>Accounts used</span>
                        <span class="tabular-nums text-slate-200">
                            {{ limits.used }} / {{ limits.max ?? "∞" }}
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-2 rounded-full bg-emerald-500 transition-all duration-500"
                            :style="{ width: usagePercent + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Connect -->
            <section v-if="can_manage" :class="[card, 'mb-8']">
                <h3 class="mb-4 text-lg font-bold">Connect an account</h3>
                <div class="flex flex-wrap gap-3">
                    <template v-for="p in platformButtons" :key="p.key">
                        <a
                            v-if="availability[p.key]"
                            :href="connect_urls[p.key]"
                            :class="[
                                'rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
                                p.classes,
                            ]"
                            :title="p.hint"
                        >
                            + {{ p.label }}
                        </a>
                        <span
                            v-else
                            class="cursor-not-allowed rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-500"
                            title="Not set up yet. Ask your administrator to configure this integration."
                        >
                            {{ p.label }} · not set up
                        </span>
                    </template>
                </div>
                <p class="mt-4 text-xs leading-relaxed text-slate-500">
                    <strong class="text-slate-400">Facebook</strong> connects
                    Pages (and lets you pick the Instagram accounts linked to
                    them).
                    <strong class="text-slate-400">Instagram</strong> connects
                    an Instagram Business or Creator account directly — no
                    Facebook Page needed. You can connect as many accounts as
                    your plan allows, from different logins.
                </p>
            </section>
            <p v-else class="mb-8 text-sm text-slate-400">
                Only organization admins and managers can connect or disconnect
                accounts.
            </p>

            <!-- Connected Accounts -->
            <div class="grid gap-4">
                <article
                    v-for="account in accounts"
                    :key="account.id"
                    class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md transition-all hover:border-white/20 hover:bg-white/10"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-lg font-bold">
                                    {{ account.platform_account_name }}
                                </h3>
                                <span
                                    :class="getStatusClass(account.status)"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                >
                                    {{ account.status }}
                                </span>
                            </div>

                            <p class="mb-4 text-xs capitalize text-slate-400">
                                <span
                                    class="mr-1 inline-block h-2 w-2 rounded-full"
                                    :class="platformColor(account.platform)"
                                ></span>
                                {{ account.platform }}
                                <span
                                    v-if="connectionLabel(account)"
                                    class="normal-case text-slate-500"
                                >
                                    · {{ connectionLabel(account) }}
                                </span>
                            </p>

                            <div class="space-y-1.5 text-sm text-slate-400">
                                <p v-if="account.last_synced_at">
                                    Last synced:
                                    <span class="text-slate-300">{{
                                        formatDate(account.last_synced_at)
                                    }}</span>
                                </p>
                                <p
                                    v-if="
                                        account.auth_mode ===
                                            'instagram_login' &&
                                        account.token_expires_at
                                    "
                                    :class="
                                        tokenSoon(account)
                                            ? 'text-yellow-300'
                                            : ''
                                    "
                                >
                                    Access token renews automatically · valid
                                    until
                                    {{ formatDate(account.token_expires_at) }}
                                </p>
                                <p
                                    v-if="
                                        isMeta(account) &&
                                        account.status === 'connected'
                                    "
                                    class="flex items-center gap-2"
                                    :class="
                                        account.webhook_subscribed_at
                                            ? 'text-green-300'
                                            : 'text-yellow-300'
                                    "
                                >
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :class="
                                            account.webhook_subscribed_at
                                                ? 'animate-pulse bg-emerald-400'
                                                : 'bg-yellow-400'
                                        "
                                    ></span>
                                    {{
                                        account.webhook_subscribed_at
                                            ? "Real-time comments active"
                                            : "Real-time comments not subscribed (syncing on schedule)"
                                    }}
                                </p>
                                <p
                                    v-if="
                                        account.error_message &&
                                        account.status !== 'connected'
                                    "
                                    class="text-red-300"
                                >
                                    {{ account.error_message }}
                                </p>
                                <p
                                    v-if="
                                        account.platform === 'youtube' &&
                                        account.metadata?.pubsub_subscribed
                                    "
                                    class="flex items-center gap-2 text-green-300"
                                >
                                    <span
                                        class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-emerald-400"
                                    ></span>
                                    Webhook active (PubSubHubbub)
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <LoadingButton
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                :base-classes="btn.amber"
                                @click="syncNow(account.id, true)"
                            >
                                Full Sync
                            </LoadingButton>

                            <LoadingButton
                                v-if="
                                    account.platform === 'facebook' ||
                                    account.platform === 'instagram'
                                "
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                :base-classes="btn.ghost"
                                @click="syncNow(account.id, false)"
                            >
                                Sync Now
                            </LoadingButton>

                            <LoadingButton
                                v-if="account.platform === 'youtube'"
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                :base-classes="btn.ghost"
                                @click="syncYoutube(account.id)"
                            >
                                Sync Now
                            </LoadingButton>

                            <LoadingButton
                                v-if="account.platform === 'twitter'"
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                :base-classes="btn.ghost"
                                @click="syncTwitter(account.id)"
                            >
                                Sync Now
                            </LoadingButton>

                            <LoadingButton
                                v-if="account.platform === 'linkedin'"
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                :base-classes="btn.ghost"
                                @click="syncLinkedIn(account.id)"
                            >
                                Sync Now
                            </LoadingButton>

                            <a
                                v-if="
                                    can_manage &&
                                    account.status === 'expired' &&
                                    account.reconnect_url
                                "
                                :href="account.reconnect_url"
                                :class="btn.yellow"
                            >
                                Re-authorize
                            </a>

                            <LoadingButton
                                v-if="
                                    can_manage &&
                                    account.status === 'disconnected'
                                "
                                :loading="loading.reconnect[account.id]"
                                :loading-text="'Reconnecting...'"
                                :base-classes="btn.green"
                                @click="reconnect(account)"
                            >
                                Reconnect
                            </LoadingButton>

                            <LoadingButton
                                v-if="
                                    can_manage &&
                                    isMeta(account) &&
                                    account.status === 'connected'
                                "
                                :loading="loading.webhook[account.id]"
                                :loading-text="'Refreshing...'"
                                :base-classes="btn.ghost"
                                @click="resubscribe(account)"
                            >
                                Refresh webhook
                            </LoadingButton>

                            <LoadingButton
                                v-if="
                                    can_manage &&
                                    account.status !== 'disconnected'
                                "
                                :loading="loading.disconnect[account.id]"
                                :loading-text="'Removing...'"
                                :base-classes="btn.red"
                                @click="disconnect(account.id)"
                            >
                                Disconnect
                            </LoadingButton>
                        </div>
                    </div>
                </article>

                <section
                    v-if="accounts.length === 0"
                    :class="[card, 'py-12 text-center']"
                >
                    <p class="mb-1 text-slate-300">
                        No social accounts connected yet
                    </p>
                    <p v-if="can_manage" class="text-sm text-slate-500">
                        Use the buttons above to connect your first account.
                    </p>
                </section>
            </div>

            <!-- Platform status -->
            <section :class="[card, 'mt-10']">
                <h3 class="mb-5 text-lg font-bold">Platforms</h3>
                <div class="grid gap-3 text-sm md:grid-cols-2">
                    <div
                        v-for="p in platformButtons"
                        :key="p.key"
                        class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-3"
                    >
                        <span
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                            :class="
                                availability[p.key]
                                    ? 'bg-emerald-400'
                                    : 'bg-slate-500'
                            "
                        ></span>
                        <div>
                            <p class="font-medium text-slate-200">
                                {{ p.label }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{
                                    availability[p.key]
                                        ? p.note
                                        : "not set up by your administrator"
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, reactive } from "vue";
import { Head, router } from "@inertiajs/vue3";
import axios from "axios";
import AppLayout from "@/Layouts/AppLayout.vue";
import { toast } from "@/composables/useToast";
import LoadingButton from "@/Components/LoadingButton.vue";

const props = defineProps({
    accounts: Array,
    availability: { type: Object, default: () => ({}) },
    connect_urls: { type: Object, default: () => ({}) },
    limits: { type: Object, default: () => ({ used: 0, max: null }) },
    can_manage: { type: Boolean, default: false },
    available_platforms: Array,
});

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const btnBase = "rounded-lg border px-3 py-1.5 text-sm transition-colors";
const btn = {
    ghost: `${btnBase} border-white/10 bg-white/5 text-slate-200 hover:bg-white/10`,
    amber: `${btnBase} border-amber-500/30 bg-amber-500/15 text-amber-200 hover:bg-amber-500/25`,
    green: `${btnBase} border-emerald-500/30 bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25`,
    yellow: `${btnBase} border-yellow-500/30 bg-yellow-500/15 text-yellow-200 hover:bg-yellow-500/25`,
    red: `${btnBase} border-red-500/30 bg-red-500/15 text-red-200 hover:bg-red-500/25`,
};

const platformButtons = [
    {
        key: "facebook",
        label: "Facebook",
        classes:
            "border-blue-500/30 bg-blue-500/20 text-blue-100 hover:bg-blue-500/30",
        note: "Pages, with Instagram accounts linked to them",
        hint: "Connect Facebook Pages and choose which to add",
    },
    {
        key: "instagram",
        label: "Instagram",
        classes:
            "border-pink-500/30 bg-gradient-to-r from-pink-500/25 to-orange-500/25 text-pink-100 hover:from-pink-500/35 hover:to-orange-500/35",
        note: "direct login, no Facebook Page needed",
        hint: "Connect an Instagram Business/Creator account directly",
    },
    {
        key: "youtube",
        label: "YouTube",
        classes:
            "border-red-500/30 bg-red-500/20 text-red-100 hover:bg-red-500/30",
        note: "webhook + scheduled sync",
        hint: "",
    },
    {
        key: "twitter",
        label: "X",
        classes: "border-white/20 bg-white/10 text-slate-100 hover:bg-white/20",
        note: "scheduled sync",
        hint: "",
    },
    {
        key: "linkedin",
        label: "LinkedIn",
        classes:
            "border-sky-500/30 bg-sky-500/20 text-sky-100 hover:bg-sky-500/30",
        note: "company pages",
        hint: "",
    },
];

// same dot colors as the Analytics "By platform" bars
const platformColor = (p) =>
    ({
        facebook: "bg-blue-500",
        instagram: "bg-pink-500",
        youtube: "bg-red-500",
        twitter: "bg-slate-300",
        linkedin: "bg-sky-600",
    })[p] ?? "bg-slate-500";

const usagePercent = computed(() => {
    const { used, max } = props.limits ?? {};
    if (!max) return 0;
    return Math.min(100, Math.round(((used ?? 0) / max) * 100));
});

// ---------------------------------------------------------------- state + helpers
const loading = reactive({
    facebook: false,
    youtube: false,
    twitter: false,
    linkedin: false,
    sync: {},
    webhook: {},
    disconnect: {},
    reconnect: {},
});

const isMeta = (a) => a.platform === "facebook" || a.platform === "instagram";

const connectionLabel = (a) => {
    if (a.platform !== "instagram") return "";
    if (a.auth_mode === "instagram_login")
        return "connected directly (no Facebook Page)";
    return a.parent?.platform_account_name
        ? `via Facebook Page “${a.parent.platform_account_name}”`
        : "via Facebook";
};

const tokenSoon = (a) =>
    a.token_expires_at && new Date(a.token_expires_at) - Date.now() < 7 * 864e5;

const errorMessage = (e, fallback) =>
    e.response?.data?.message || e.response?.data?.error || fallback;

// ---------------------------------------------------------------- actions
const reconnect = async (account) => {
    loading.reconnect[account.id] = true;
    try {
        const r = await axios.post(
            `/settings/social-accounts/${account.id}/reconnect`,
        );
        toast.success(r.data.message || "Account reconnected");
        router.reload();
    } catch (e) {
        if (e.response?.status === 409 && e.response.data.reauthorize_url) {
            window.location.href = e.response.data.reauthorize_url;
            return;
        }
        toast.error(errorMessage(e, "Reconnect failed"));
    } finally {
        loading.reconnect[account.id] = false;
    }
};

const resubscribe = async (account) => {
    loading.webhook[account.id] = true;
    try {
        const r = await axios.post(
            `/settings/social-accounts/${account.id}/resubscribe`,
        );
        toast.success(r.data.message || "Webhook refreshed");
        router.reload();
    } catch (e) {
        toast.error(errorMessage(e, "Could not refresh the webhook"));
    } finally {
        loading.webhook[account.id] = false;
    }
};

const syncLinkedIn = async (accountId) => {
    loading.sync[accountId] = true;
    try {
        const response = await axios.post(
            `/settings/social-accounts/${accountId}/linkedin-sync`,
        );
        toast.success(response.data.message || "LinkedIn synced successfully");
    } catch (error) {
        toast.error(errorMessage(error, "LinkedIn sync failed"));
    } finally {
        loading.sync[accountId] = false;
    }
};

const syncNow = async (accountId, fullSync) => {
    loading.sync[accountId] = true;
    try {
        const response = await axios.post(
            `/settings/social-accounts/${accountId}/sync`,
            null,
            {
                params: { full_sync: fullSync ? 1 : 0 },
            },
        );
        toast.success(
            response.data.message ||
                (fullSync ? "Full sync started" : "Sync completed"),
        );
    } catch (error) {
        toast.error(errorMessage(error, "Sync failed"));
    } finally {
        loading.sync[accountId] = false;
    }
};

const syncYoutube = async (accountId) => {
    loading.sync[accountId] = true;
    try {
        const response = await axios.post(
            `/settings/social-accounts/${accountId}/youtube-sync`,
        );
        toast.success(response.data.message || "YouTube synced successfully");
    } catch (error) {
        toast.error(errorMessage(error, "YouTube sync failed"));
    } finally {
        loading.sync[accountId] = false;
    }
};

// Not used in the template right now (the webhook button is disabled), kept for later
const toggleYoutubeWebhook = async (account) => {
    if (account.metadata?.pubsub_subscribed) {
        if (
            !confirm(
                "Disable real-time webhook for this channel? Cron sync will continue.",
            )
        )
            return;
    }
    loading.webhook[account.id] = true;
    try {
        const url = account.metadata?.pubsub_subscribed
            ? `/youtube/unsubscribe/${account.id}`
            : `/youtube/subscribe/${account.id}`;
        const response = await axios.post(url);
        toast.success(
            response.data.message ||
                (account.metadata?.pubsub_subscribed
                    ? "Webhook unsubscribed"
                    : "Webhook subscribed"),
        );
        router.reload();
    } catch (error) {
        toast.error(errorMessage(error, "YouTube webhook toggle failed"));
    } finally {
        loading.webhook[account.id] = false;
    }
};

const syncTwitter = async (accountId) => {
    loading.sync[accountId] = true;
    try {
        const response = await axios.post(
            `/settings/social-accounts/${accountId}/twitter-sync`,
        );
        toast.success(response.data.message || "X/Twitter synced successfully");
    } catch (error) {
        toast.error(errorMessage(error, "X sync failed"));
    } finally {
        loading.sync[accountId] = false;
    }
};

const disconnect = async (accountId) => {
    if (!confirm("Are you sure you want to disconnect this account?")) return;
    loading.disconnect[accountId] = true;
    try {
        const response = await axios.post(
            `/settings/social-accounts/${accountId}/disconnect`,
        );
        toast.success(response.data.message || "Account disconnected");
        router.reload();
    } catch (error) {
        toast.error(errorMessage(error, "Disconnect failed"));
    } finally {
        loading.disconnect[accountId] = false;
    }
};

// ---------------------------------------------------------------- formatting
const getStatusClass = (status) => {
    const classes = {
        connected: "bg-green-500/15 text-green-300",
        disconnected: "bg-red-500/15 text-red-300",
        expired: "bg-yellow-500/15 text-yellow-300",
        error: "bg-red-500/15 text-red-300",
    };
    return classes[status] || "bg-slate-500/20 text-slate-300";
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString();
};
</script>
