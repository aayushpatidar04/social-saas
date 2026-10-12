<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex flex-wrap justify-between items-start gap-4 mb-2">
                <div>
                    <h1 class="text-3xl font-bold text-white">Social Accounts</h1>
                    <p class="text-sm text-slate-400 mt-1">
                        {{ limits.used }} of {{ limits.max ?? "unlimited" }} accounts used
                    </p>
                </div>
            </div>

            <div v-if="can_manage" class="mb-8 bg-slate-800 rounded-lg border border-slate-700 p-5">
                <p class="text-sm text-slate-400 mb-4">Connect an account</p>
                <div class="flex flex-wrap gap-3">
                    <template v-for="p in platformButtons" :key="p.key">
                        <a v-if="availability[p.key]" :href="connect_urls[p.key]"
                            :class="['px-4 py-2 rounded-lg font-medium text-sm text-white', p.classes]"
                            :title="p.hint">
                            + {{ p.label }}
                        </a>
                        <span v-else
                            class="px-4 py-2 rounded-lg text-sm text-slate-500 bg-slate-700/50 cursor-not-allowed"
                            title="Not set up yet. Ask your administrator to configure this integration.">
                            {{ p.label }} · not set up
                        </span>
                    </template>
                </div>
                <p class="text-xs text-slate-500 mt-4">
                    <strong class="text-slate-400">Facebook</strong> connects Pages (and lets you pick the Instagram accounts linked to them).
                    <strong class="text-slate-400">Instagram</strong> connects an Instagram Business or Creator account directly —
                    no Facebook Page needed. You can connect as many accounts as your plan allows, from different logins.
                </p>
            </div>
            <p v-else class="mb-8 text-sm text-slate-400">Only organization admins and managers can connect or disconnect accounts.</p>

            <!-- Connected Accounts -->
            <div class="grid gap-4">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="bg-slate-800 rounded-lg border border-slate-700 p-6"
                >
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-white mb-2">
                                {{ account.platform_account_name }}
                            </h3>
                            <p class="text-slate-400 text-sm mb-4">
                                Platform:
                                <span
                                    class="capitalize font-mono text-blue-400"
                                    >{{ account.platform }}</span
                                >
                                <span v-if="connectionLabel(account)" class="ml-2 text-xs text-slate-500">
                                    · {{ connectionLabel(account) }}
                                </span>
                            </p>
                            <div class="space-y-2 text-sm text-slate-400">
                                <p>
                                    Status:
                                    <span
                                        :class="getStatusClass(account.status)"
                                        >{{ account.status }}</span
                                    >
                                </p>
                                <p v-if="account.last_synced_at">
                                    Last synced:
                                    {{ formatDate(account.last_synced_at) }}
                                </p>
                                <p v-if="account.auth_mode === 'instagram_login' && account.token_expires_at"
                                    :class="tokenSoon(account) ? 'text-yellow-400' : ''">
                                    Access token renews automatically · valid until {{ formatDate(account.token_expires_at) }}
                                </p>
                                <p v-if="isMeta(account) && account.status === 'connected'"
                                    :class="account.webhook_subscribed_at ? 'text-green-400' : 'text-yellow-400'">
                                    {{ account.webhook_subscribed_at ? "🟢 Real-time comments active" : "🟡 Real-time comments not subscribed (syncing on schedule)" }}
                                </p>
                                <p v-if="account.error_message && account.status !== 'connected'" class="text-red-400">
                                    {{ account.error_message }}
                                </p>
                                <p
                                    v-if="
                                        account.platform === 'youtube' &&
                                        account.metadata?.pubsub_subscribed
                                    "
                                    class="text-green-400"
                                >
                                    🟢 Webhook active (PubSubHubbub)
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <LoadingButton
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                base-classes="bg-amber-700 hover:bg-amber-600 text-white rounded text-sm"
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
                                base-classes="bg-slate-700 hover:bg-slate-600 text-white rounded text-sm"
                                @click="syncNow(account.id, false)"
                            >
                                Sync Now
                            </LoadingButton>

                            <template v-if="account.platform === 'youtube'">
                                <LoadingButton
                                    :loading="loading.sync[account.id]"
                                    :loading-text="'Syncing...'"
                                    base-classes="bg-slate-700 hover:bg-slate-600 text-white rounded text-sm"
                                    @click="syncYoutube(account.id)"
                                >
                                    Sync Now
                                </LoadingButton>
                                <LoadingButton
                                    :loading="loading.webhook[account.id]"
                                    :loading-text="'Toggling...'"
                                    :class="[
                                        'px-3 py-2 text-white rounded text-sm',
                                        account.metadata?.pubsub_subscribed
                                            ? 'bg-green-700 hover:bg-green-600'
                                            : 'bg-orange-700 hover:bg-orange-600',
                                    ]"
                                    @click="toggleYoutubeWebhook(account)"
                                >
                                    {{
                                        account.metadata?.pubsub_subscribed
                                            ? "Webhook Active"
                                            : "Subscribe Webhook"
                                    }}
                                </LoadingButton>
                            </template>

                            <LoadingButton
                                v-if="account.platform === 'twitter'"
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                base-classes="bg-slate-700 hover:bg-slate-600 text-white rounded text-sm"
                                @click="syncTwitter(account.id)"
                            >
                                Sync Now
                            </LoadingButton>

                            <LoadingButton
                                v-if="account.platform === 'linkedin'"
                                :loading="loading.sync[account.id]"
                                :loading-text="'Syncing...'"
                                base-classes="bg-slate-700 hover:bg-slate-600 text-white rounded text-sm"
                                @click="syncLinkedIn(account.id)"
                            >
                                Sync Now
                            </LoadingButton>

                            <a v-if="can_manage && account.status === 'expired' && account.reconnect_url"
                                :href="account.reconnect_url"
                                class="px-3 py-2 bg-yellow-700 hover:bg-yellow-600 text-white rounded text-sm">
                                Re-authorize
                            </a>

                            <LoadingButton
                                v-if="can_manage && account.status === 'disconnected'"
                                :loading="loading.reconnect[account.id]"
                                :loading-text="'Reconnecting...'"
                                base-classes="bg-green-800 hover:bg-green-700 text-white rounded text-sm"
                                @click="reconnect(account)"
                            >
                                Reconnect
                            </LoadingButton>

                            <LoadingButton
                                v-if="can_manage && isMeta(account) && account.status === 'connected'"
                                :loading="loading.webhook[account.id]"
                                :loading-text="'Refreshing...'"
                                base-classes="bg-slate-700 hover:bg-slate-600 text-white rounded text-sm"
                                @click="resubscribe(account)"
                            >
                                Refresh webhook
                            </LoadingButton>

                            <LoadingButton
                                v-if="can_manage && account.status !== 'disconnected'"
                                :loading="loading.disconnect[account.id]"
                                :loading-text="'Removing...'"
                                base-classes="bg-red-900 hover:bg-red-800 text-red-200 rounded text-sm"
                                @click="disconnect(account.id)"
                            >
                                Disconnect
                            </LoadingButton>
                        </div>
                    </div>
                </div>

                <div
                    v-if="accounts.length === 0"
                    class="bg-slate-800 rounded-lg border border-slate-700 p-5 mb-2 text-center"
                >
                    <p class="text-slate-400 text-lg mb-4">
                        No social accounts connected yet
                    </p>
                    <p v-if="can_manage" class="text-sm text-slate-500">Use the buttons above to connect your first account.</p>
                </div>
            </div>

            <!-- Platform status -->
            <div class="mt-12 bg-slate-800 rounded-lg border border-slate-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Platforms</h3>
                <div class="grid md:grid-cols-2 gap-3 text-sm text-slate-300">
                    <div v-for="p in platformButtons" :key="p.key">
                        {{ availability[p.key] ? "✅" : "⚪" }} {{ p.label }}
                        <span class="text-slate-500">— {{ availability[p.key] ? p.note : "not set up by your administrator" }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";
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

const platformButtons = [
    { key: "facebook", label: "Facebook", classes: "bg-blue-600 hover:bg-blue-700", note: "Pages, with Instagram accounts linked to them", hint: "Connect Facebook Pages and choose which to add" },
    { key: "instagram", label: "Instagram", classes: "bg-gradient-to-r from-pink-600 to-orange-500 hover:opacity-90", note: "direct login, no Facebook Page needed", hint: "Connect an Instagram Business/Creator account directly" },
    { key: "youtube", label: "YouTube", classes: "bg-red-600 hover:bg-red-700", note: "webhook + scheduled sync", hint: "" },
    { key: "twitter", label: "X", classes: "bg-black hover:bg-gray-800 border border-slate-600", note: "scheduled sync", hint: "" },
    { key: "linkedin", label: "LinkedIn", classes: "bg-blue-700 hover:bg-blue-800", note: "company pages", hint: "" },
];

const isMeta = (a) => a.platform === "facebook" || a.platform === "instagram";

const connectionLabel = (a) => {
    if (a.platform !== "instagram") return "";
    if (a.auth_mode === "instagram_login") return "connected directly (no Facebook Page)";
    return a.parent?.platform_account_name ? `via Facebook Page “${a.parent.platform_account_name}”` : "via Facebook";
};

const tokenSoon = (a) => a.token_expires_at && new Date(a.token_expires_at) - Date.now() < 7 * 864e5;

const errorMessage = (e, fallback) => e.response?.data?.message || e.response?.data?.error || fallback;

const reconnect = async (account) => {
    loading.reconnect[account.id] = true;
    try {
        const r = await axios.post(`/settings/social-accounts/${account.id}/reconnect`);
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
        const r = await axios.post(`/settings/social-accounts/${account.id}/resubscribe`);
        toast.success(r.data.message || "Webhook refreshed");
        router.reload();
    } catch (e) {
        toast.error(errorMessage(e, "Could not refresh the webhook"));
    } finally {
        loading.webhook[account.id] = false;
    }
};

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
        toast.error(
            errorMessage(error, "YouTube webhook toggle failed"),
        );
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

const getStatusClass = (status) => {
    const classes = {
        connected: "text-green-400",
        disconnected: "text-red-400",
        expired: "text-yellow-400",
        error: "text-red-400",
    };
    return classes[status] || "text-slate-400";
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString();
};
</script>
