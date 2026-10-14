<template>
    <AppLayout>
        <Head title="Inbox" />

        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Inbox
                </h1>
                <p class="mt-1 text-slate-400">
                    Customer conversations across all your social accounts
                </p>
            </div>
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

        <!-- Filters -->
        <section :class="[card, 'mb-6']">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div class="relative">
                    <MagnifyingGlassIcon
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
                    />
                    <input
                        v-model="filters.search"
                        @input="applyFilters"
                        type="text"
                        placeholder="Search conversations..."
                        :class="[field, 'pl-9']"
                    />
                </div>

                <select
                    v-model="filters.status"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Status</option>
                    <option value="new">New</option>
                    <option value="pending_approval">Pending Approval</option>
                    <option value="replied">Replied</option>
                    <option value="reviewed">Reviewed</option>
                </select>

                <select
                    v-model="filters.sentiment"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Sentiment</option>
                    <option value="positive">Positive</option>
                    <option value="neutral">Neutral</option>
                    <option value="negative">Negative</option>
                </select>

                <select
                    v-model="filters.platform"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Platforms</option>
                    <option value="facebook">Facebook</option>
                    <option value="instagram">Instagram</option>
                    <option value="youtube">YouTube</option>
                    <option value="twitter">X/Twitter</option>
                    <option value="linkedin">LinkedIn</option>
                </select>

                <select
                    v-model="filters.intent"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Intent</option>
                    <option value="sales">Sales</option>
                    <option value="support">Support</option>
                    <option value="complaint">Complaint</option>
                    <option value="question">Question</option>
                    <option value="lead">Lead</option>
                    <option value="general">General</option>
                </select>
            </div>
        </section>

        <!-- Thread List -->
        <div v-if="loading" class="py-10 text-center">
            <div class="inline-flex items-center gap-3 text-slate-400">
                <ArrowPathIcon class="h-5 w-5 animate-spin" />
                Loading conversations...
            </div>
        </div>

        <section
            v-else-if="threads.length === 0"
            :class="[card, 'py-12 text-center']"
        >
            <p class="text-slate-400">No conversations found</p>
        </section>

        <div v-else class="space-y-3">
            <article
                v-for="thread in threads"
                :key="thread.id"
                @click="openThreadDetail(thread)"
                class="cursor-pointer rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:border-white/20 hover:bg-white/10"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-lg font-bold">
                            {{ thread.author_name }}
                        </h3>

                        <p class="text-xs capitalize text-slate-400">
                            <span
                                class="mr-1 inline-block h-2 w-2 rounded-full"
                                :class="platformColor(thread.platform)"
                            ></span>
                            {{ platformLabel(thread.platform) }}
                            · {{ formatDate(thread.commented_at) }}
                            <span v-if="thread.thread_replies?.length">
                                · 💬 {{ thread.thread_replies.length }} replies
                            </span>
                        </p>
                    </div>

                    <span
                        :class="getStatusColor(thread.status)"
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                    >
                        {{ statusLabel(thread.status) }}
                    </span>
                </div>

                <!-- Root Message -->
                <p class="mb-3 line-clamp-2 text-sm text-slate-300">
                    {{ thread.content }}
                </p>

                <!-- Latest Message Preview -->
                <div
                    v-if="latestMessage(thread)"
                    class="mb-3 rounded-xl border border-white/10 bg-white/[0.03] p-3"
                >
                    <p class="mb-1 text-xs text-slate-500">
                        Latest message ·
                        {{
                            latestMessage(thread).direction === "outbound"
                                ? "You / AI"
                                : latestMessage(thread).author_name
                        }}
                    </p>
                    <p class="line-clamp-1 text-sm text-slate-300">
                        {{ latestMessage(thread).content }}
                    </p>
                </div>

                <!-- Analysis Tags -->
                <div class="mb-3 flex flex-wrap gap-2">
                    <span
                        v-if="thread.sentiment"
                        :class="getSentimentColor(thread.sentiment)"
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                    >
                        {{ thread.sentiment }} ({{ thread.sentiment_score }}%)
                    </span>

                    <span
                        v-if="thread.intent"
                        :class="getIntentColor(thread.intent)"
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                    >
                        {{ thread.intent }}
                    </span>

                    <span
                        v-if="thread.is_lead"
                        class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-300"
                    >
                        ✓ Lead ({{ thread.lead_score }}%)
                    </span>
                </div>

                <!-- Conversation Mini Timeline -->
                <div
                    v-if="thread.thread_replies?.length"
                    class="mb-4 space-y-2 border-l-2 border-white/10 pl-4"
                >
                    <div
                        v-for="reply in thread.thread_replies.slice(-3)"
                        :key="reply.id"
                        class="text-sm"
                    >
                        <span
                            :class="
                                reply.direction === 'outbound'
                                    ? 'text-blue-300'
                                    : 'text-slate-300'
                            "
                            class="font-semibold"
                        >
                            {{
                                reply.direction === "outbound"
                                    ? "You / AI"
                                    : reply.author_name
                            }}:
                        </span>
                        <span class="line-clamp-1 text-slate-400">
                            {{ reply.content }}
                        </span>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="flex gap-2" @click.stop>
                    <LoadingButton
                        :loading="replyingToId === thread.id"
                        :loading-text="'Sending...'"
                        :base-classes="primaryBtn"
                        @click="replyToThread(thread)"
                    >
                        Reply
                    </LoadingButton>

                    <LoadingButton
                        v-if="thread.status !== 'replied'"
                        :loading="loadingMarkResponded[thread.id]"
                        :loading-text="'Saving...'"
                        :base-classes="ghostBtn"
                        @click="markAsResponded(thread)"
                    >
                        Mark Replied
                    </LoadingButton>
                </div>
            </article>
        </div>

        <!-- Pagination -->
        <div v-if="pagination?.links" class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="link in pagination.links"
                :key="link.label"
                v-html="link.label"
                :disabled="!link.url"
                @click="goToPage(link.url)"
                :class="[
                    'rounded-lg border px-3 py-1.5 text-sm transition-colors',
                    link.active
                        ? 'border-blue-500/40 bg-blue-500/20 text-blue-200'
                        : 'border-white/10 bg-white/5 text-slate-300',
                    !link.url
                        ? 'cursor-not-allowed opacity-50'
                        : 'hover:bg-white/10',
                ]"
            />
        </div>

        <!-- Thread Detail Modal -->
        <div
            v-if="selectedThread"
            class="thin-scroll fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-sm"
            @click.self="selectedThread = null"
        >
            <!-- centers the modal, and lets the overlay scroll if the screen is very short -->
            <div class="flex min-h-full items-center justify-center p-4">
                <div
                    class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0b1230]/95 backdrop-blur-md"
                >
                    <!-- Header (fixed) -->
                    <div
                        class="flex shrink-0 items-start justify-between border-b border-white/10 p-6"
                    >
                        <div>
                            <h2 class="text-2xl font-bold">Conversation</h2>
                            <p class="text-sm text-slate-400">
                                {{ platformLabel(selectedThread.platform) }}
                                · {{ selectedThread.author_name }}
                            </p>
                        </div>

                        <button
                            @click="selectedThread = null"
                            class="rounded-lg p-1 text-2xl leading-none text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                        >
                            ×
                        </button>
                    </div>

                    <!-- Thread bubbles (scrollable) -->
                    <div
                        class="thin-scroll min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-6"
                    >
                        <!-- Root -->
                        <MessageBubble :message="selectedThread" />

                        <!-- Replies -->
                        <MessageBubble
                            v-for="reply in selectedThread.thread_replies"
                            :key="reply.id"
                            :message="reply"
                        />
                    </div>

                    <!-- Footer (fixed) -->
                    <div
                        class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-white/10 p-6"
                    >
                        <LoadingButton
                            :loading="replyingToId === selectedThread.id"
                            :loading-text="'Sending...'"
                            :base-classes="primaryBtn"
                            @click="replyToThread(selectedThread)"
                        >
                            Reply to Latest Customer Message
                        </LoadingButton>

                        <LoadingButton
                            :base-classes="ghostBtn"
                            @click="selectedThread = null"
                        >
                            Close
                        </LoadingButton>
                    </div>
                </div>
            </div>
        </div>

        <ReplyModal
            v-if="replyingTo"
            :comment="replyingTo"
            @close="
                () => {
                    replyingTo = null;
                    replyingToId = null;
                }
            "
            @sent="handleReplySent"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, defineComponent, h, reactive } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import {
    ArrowPathIcon,
    CheckBadgeIcon,
    ChatBubbleLeftRightIcon,
    ClockIcon,
    InboxArrowDownIcon,
    MagnifyingGlassIcon,
} from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import ReplyModal from "@/Components/Comments/ReplyModal.vue";
import { toast } from "@/composables/useToast";
import LoadingButton from "@/Components/LoadingButton.vue";

const page = usePage();

// ---------------------------------------------------------------- shared style tokens (same as Analytics)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const field =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none [&>option]:bg-[#0b1230]";

const primaryBtn =
    "rounded-lg border border-blue-500/30 bg-blue-500/20 px-3 py-1.5 text-sm text-blue-100 transition-colors hover:bg-blue-500/30";

const ghostBtn =
    "rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10";

// ---------------------------------------------------------------- state
const commentsProp = computed(() => page.props.comments || {});
const initialFilters = computed(() => page.props.filters || {});

const loading = ref(false);
const selectedThread = ref(null);
const replyingTo = ref(null);
const replyingToId = ref(null);

const loadingMarkResponded = reactive({});

const filters = ref({
    search: initialFilters.value.search || "",
    status: initialFilters.value.status || "",
    sentiment: initialFilters.value.sentiment || "",
    platform: initialFilters.value.platform || "",
    intent: initialFilters.value.intent || "",
});

const threads = computed(() => {
    return commentsProp.value.data || [];
});

const pagination = computed(() => {
    return {
        links: commentsProp.value.links || [],
        meta: commentsProp.value.meta || null,
    };
});

const stats = computed(() => [
    {
        label: "Total threads",
        value: threads.value.length,
        icon: ChatBubbleLeftRightIcon,
        tone: "text-white",
        glow: "bg-slate-300",
    },
    {
        label: "New",
        value: threads.value.filter((c) => c.status === "new").length,
        icon: InboxArrowDownIcon,
        tone: "text-blue-400",
        glow: "bg-blue-400",
    },
    {
        label: "Pending",
        value: threads.value.filter((c) => c.status === "pending_approval")
            .length,
        icon: ClockIcon,
        tone: "text-yellow-400",
        glow: "bg-yellow-400",
    },
    {
        label: "Replied",
        value: threads.value.filter((c) => c.status === "replied").length,
        icon: CheckBadgeIcon,
        tone: "text-green-400",
        glow: "bg-green-400",
    },
]);

// ---------------------------------------------------------------- actions
const markAsResponded = async (thread) => {
    loadingMarkResponded[thread.id] = true;
    try {
        const response = await fetch(`/inbox/${thread.id}/mark-responded`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
        });

        if (response.ok) {
            toast.success("Marked as responded");
            refreshComments();
        } else {
            toast.error("Failed to mark as responded");
        }
    } catch (error) {
        toast.error("Error marking as responded");
    } finally {
        loadingMarkResponded[thread.id] = false;
    }
};

const applyFilters = () => {
    loading.value = true;
    router.get("/inbox", filters.value, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

const goToPage = (url) => {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

const refreshComments = () => {
    router.reload({
        only: ["comments"],
    });
};

const openThreadDetail = (thread) => {
    selectedThread.value = thread;
};

const latestMessage = (thread) => {
    const replies = thread.thread_replies || [];

    if (!replies.length) {
        return null;
    }

    return replies[replies.length - 1];
};

const getReplyTarget = (thread) => {
    const allMessages = [thread, ...(thread.thread_replies || [])];

    const inboundMessages = allMessages
        .filter((item) => item.direction === "inbound")
        .sort((a, b) => new Date(b.commented_at) - new Date(a.commented_at));

    return inboundMessages[0] || thread;
};

const replyToThread = (thread) => {
    replyingTo.value = getReplyTarget(thread);
    replyingToId.value = thread.id;
};

const handleReplySent = () => {
    replyingTo.value = null;
    replyingToId.value = null;
    selectedThread.value = null;
    refreshComments();
    toast.success("Reply sent successfully");
};

// ---------------------------------------------------------------- formatting + colors
const formatDate = (date) => {
    if (!date) return "";

    return (
        new Date(date).toLocaleDateString() +
        " " +
        new Date(date).toLocaleTimeString()
    );
};

const platformLabel = (platform) => {
    const labels = {
        facebook: "Facebook",
        instagram: "Instagram",
        youtube: "YouTube",
        twitter: "X/Twitter",
        linkedin: "LinkedIn",
    };

    return labels[platform] || platform;
};

// same dot colors as the Analytics "By platform" bars
const platformColor = (p) =>
    ({
        facebook: "bg-blue-500",
        instagram: "bg-pink-500",
        youtube: "bg-red-500",
        twitter: "bg-slate-300",
        linkedin: "bg-sky-600",
    })[p] ?? "bg-slate-500";

const statusLabel = (status) => (status || "").replaceAll("_", " ");

const getStatusColor = (status) => {
    const colors = {
        new: "bg-blue-500/15 text-blue-300",
        pending_approval: "bg-yellow-500/15 text-yellow-300",
        replied: "bg-green-500/15 text-green-300",
        reviewed: "bg-slate-500/20 text-slate-300",
        sent: "bg-blue-500/15 text-blue-300",
    };

    return colors[status] || "bg-slate-500/20 text-slate-300";
};

const getSentimentColor = (sentiment) => {
    const colors = {
        positive: "bg-green-500/15 text-green-300",
        neutral: "bg-slate-500/20 text-slate-300",
        negative: "bg-red-500/15 text-red-300",
        pending: "bg-yellow-500/15 text-yellow-300",
    };

    return colors[sentiment] || "bg-slate-500/20 text-slate-300";
};

const getIntentColor = (intent) => {
    const colors = {
        sales: "bg-blue-500/15 text-blue-300",
        support: "bg-purple-500/15 text-purple-300",
        complaint: "bg-red-500/15 text-red-300",
        lead: "bg-green-500/15 text-green-300",
        question: "bg-yellow-500/15 text-yellow-300",
        general: "bg-slate-500/20 text-slate-300",
    };

    return colors[intent] || "bg-slate-500/20 text-slate-300";
};

// ---------------------------------------------------------------- message bubble
const MessageBubble = defineComponent({
    name: "MessageBubble",
    props: {
        message: {
            type: Object,
            required: true,
        },
    },
    setup(props) {
        const isOutbound = computed(
            () => props.message.direction === "outbound",
        );

        const name = computed(() => {
            if (isOutbound.value) {
                return props.message.sender_type === "ai" ? "AI / You" : "You";
            }

            return props.message.author_name || "Customer";
        });

        const dateText = computed(() => {
            if (!props.message.commented_at) return "";

            return (
                new Date(props.message.commented_at).toLocaleDateString() +
                " " +
                new Date(props.message.commented_at).toLocaleTimeString()
            );
        });

        return () =>
            h(
                "div",
                {
                    class: isOutbound.value
                        ? "flex justify-end"
                        : "flex justify-start",
                },
                [
                    h(
                        "div",
                        {
                            class: [
                                "max-w-2xl rounded-2xl border p-4 text-slate-100",
                                isOutbound.value
                                    ? "border-blue-500/30 bg-blue-500/20"
                                    : "border-white/10 bg-white/5",
                            ],
                        },
                        [
                            h(
                                "p",
                                {
                                    class: "mb-1 text-xs text-slate-400",
                                },
                                `${name.value} · ${dateText.value}`,
                            ),
                            h(
                                "p",
                                {
                                    class: "whitespace-pre-line text-sm",
                                },
                                props.message.content || "",
                            ),
                        ],
                    ),
                ],
            );
    },
});
</script>

<style scoped>
/* thin, dark-theme scrollbar */
.thin-scroll {
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
.thin-scroll::-webkit-scrollbar {
    /* Chrome, Edge, Safari */
    width: 6px;
    height: 6px;
}
.thin-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.thin-scroll::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
}
.thin-scroll::-webkit-scrollbar-thumb:hover {
    background-color: rgba(255, 255, 255, 0.35);
}
</style>
