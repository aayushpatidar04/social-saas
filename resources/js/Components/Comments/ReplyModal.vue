<template>
    <Transition
        appear
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
    >
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="close"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="reply-title"
                class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#0b1230]/95 shadow-2xl shadow-black/60"
            >
                <!-- Header (fixed) -->
                <div class="shrink-0 border-b border-white/10 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500/40 to-indigo-500/40 text-base font-bold"
                            >
                                {{
                                    (comment.author_name ||
                                        "?")[0].toUpperCase()
                                }}
                            </span>
                            <div class="min-w-0">
                                <h2
                                    id="reply-title"
                                    class="truncate text-xl font-bold"
                                >
                                    Reply to {{ comment.author_name }}
                                </h2>
                                <p
                                    class="mt-0.5 flex items-center gap-2 text-sm text-slate-400"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="meta.dot"
                                    ></span>
                                    {{ meta.label }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="close"
                            class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                            aria-label="Close"
                        >
                            <XMarkIcon class="h-6 w-6" />
                        </button>
                    </div>
                </div>

                <!-- Body (scrolls) -->
                <div
                    class="thin-scroll min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain p-6"
                >
                    <!-- Original comment -->
                    <div
                        class="rounded-xl border border-white/10 border-l-4 bg-white/[0.04] p-4"
                        :class="meta.border"
                    >
                        <div
                            class="mb-2 flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-slate-400"
                            >
                                Original comment
                            </p>
                            <div class="flex gap-2">
                                <span
                                    v-if="
                                        comment.sentiment &&
                                        comment.sentiment !== 'pending'
                                    "
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                                    :class="sentimentClass"
                                >
                                    {{ comment.sentiment }}
                                </span>
                                <span
                                    v-if="comment.intent"
                                    class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-300"
                                >
                                    {{ comment.intent }}
                                </span>
                            </div>
                        </div>
                        <p
                            class="whitespace-pre-line break-words text-slate-100"
                        >
                            {{ comment.content }}
                        </p>
                    </div>

                    <!-- Mode tabs -->
                    <div
                        class="inline-flex rounded-lg border border-white/10 bg-white/5 p-1"
                    >
                        <button
                            type="button"
                            @click="setMode('manual')"
                            :class="tab(mode === 'manual')"
                        >
                            <PencilSquareIcon class="h-4 w-4" /> Write my own
                        </button>
                        <button
                            v-if="comment.ai_response_text"
                            type="button"
                            @click="setMode('ai')"
                            :class="tab(mode === 'ai')"
                        >
                            <SparklesIcon class="h-4 w-4" /> AI suggestion
                        </button>
                    </div>

                    <!-- Reply input -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label
                                for="reply-text"
                                class="text-sm font-medium text-slate-300"
                                >Your reply</label
                            >
                            <button
                                v-if="mode === 'ai' && edited"
                                type="button"
                                @click="resetToSuggestion"
                                class="inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300"
                            >
                                <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
                                Restore suggestion
                            </button>
                        </div>

                        <textarea
                            id="reply-text"
                            ref="textarea"
                            v-model="replyMessage"
                            placeholder="Write a thoughtful reply..."
                            rows="6"
                            :maxlength="limit"
                            @keydown.ctrl.enter.prevent="sendReply"
                            @keydown.meta.enter.prevent="sendReply"
                            class="w-full resize-y rounded-xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        ></textarea>

                        <div
                            class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs"
                        >
                            <span
                                v-if="mode === 'ai'"
                                class="inline-flex items-center gap-1 text-purple-300"
                            >
                                <SparklesIcon class="h-3.5 w-3.5" />
                                {{
                                    edited
                                        ? "AI suggestion, edited by you"
                                        : "AI suggestion. You can edit it before sending"
                                }}
                            </span>
                            <span v-else class="text-slate-500"
                                >Ctrl + Enter to send</span
                            >

                            <span class="tabular-nums" :class="counterClass"
                                >{{ replyMessage.length }}/{{ limit }}</span
                            >
                        </div>
                    </div>

                    <!-- Inline error (the toast disappears; this stays until you retry) -->
                    <p
                        v-if="errorMessage"
                        class="flex items-start gap-2 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"
                        role="alert"
                    >
                        <ExclamationCircleIcon
                            class="mt-0.5 h-5 w-5 shrink-0 text-red-400"
                        />
                        {{ errorMessage }}
                    </p>
                </div>

                <!-- Footer (fixed) -->
                <div class="flex shrink-0 gap-3 border-t border-white/10 p-6">
                    <button
                        @click="close"
                        :disabled="sending"
                        class="flex-1 rounded-xl border border-white/10 bg-white/5 px-4 py-3 font-medium text-slate-200 transition-colors hover:bg-white/10 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="sendReply"
                        :disabled="!canSend"
                        class="flex-1 rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
                    >
                        <span
                            v-if="sending"
                            class="inline-flex items-center justify-center gap-2"
                        >
                            <svg
                                class="h-4 w-4 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            Sending...
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center justify-center gap-2"
                        >
                            <PaperAirplaneIcon class="h-4 w-4" /> Send Reply
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";
import {
    ArrowUturnLeftIcon,
    ExclamationCircleIcon,
    PaperAirplaneIcon,
    PencilSquareIcon,
    SparklesIcon,
    XMarkIcon,
} from "@heroicons/vue/24/outline";
import { toast } from "@/composables/useToast";

const props = defineProps({
    comment: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["close", "sent"]);

// ---------------------------------------------------------------- platform info
// Limits mirror what each endpoint validates on the server (CommentController 500,
// YouTube 1000, X 280, LinkedIn 1250); the old fixed 500 let you write an X reply the server then refused.
const PLATFORMS = {
    facebook: {
        label: "Facebook",
        dot: "bg-blue-500",
        border: "border-l-blue-500",
        limit: 500,
    },
    instagram: {
        label: "Instagram",
        dot: "bg-pink-500",
        border: "border-l-pink-500",
        limit: 500,
    },
    youtube: {
        label: "YouTube",
        dot: "bg-red-500",
        border: "border-l-red-500",
        limit: 1000,
    },
    twitter: {
        label: "X (Twitter)",
        dot: "bg-slate-300",
        border: "border-l-slate-300",
        limit: 280,
    },
    linkedin: {
        label: "LinkedIn",
        dot: "bg-sky-600",
        border: "border-l-sky-600",
        limit: 1250,
    },
};

const meta = computed(
    () =>
        PLATFORMS[props.comment.platform] ?? {
            label: props.comment.platform ?? "Unknown",
            dot: "bg-slate-500",
            border: "border-l-slate-500",
            limit: 500,
        },
);
const limit = computed(() => meta.value.limit);

const sentimentClass = computed(
    () =>
        ({
            positive: "bg-green-500/15 text-green-300",
            neutral: "bg-slate-500/20 text-slate-300",
            negative: "bg-red-500/15 text-red-300",
        })[props.comment.sentiment] ?? "bg-slate-500/20 text-slate-300",
);

// ---------------------------------------------------------------- state
const replyMessage = ref("");
const mode = ref("manual"); // 'manual' | 'ai'
const sending = ref(false);
const errorMessage = ref("");
const textarea = ref(null);
let manualDraft = ""; // what you had typed, restored when you switch back from the AI tab

const aiText = computed(() =>
    (props.comment.ai_response_text || "").slice(0, limit.value),
);
const edited = computed(
    () => replyMessage.value.trim() !== aiText.value.trim(),
);

const trimmedLength = computed(() => replyMessage.value.trim().length);
const canSend = computed(
    () =>
        trimmedLength.value > 0 &&
        replyMessage.value.length <= limit.value &&
        !sending.value,
);

const counterClass = computed(() => {
    const used = replyMessage.value.length / limit.value;

    return used >= 1
        ? "font-semibold text-red-400"
        : used >= 0.9
          ? "text-yellow-400"
          : "text-slate-500";
});

const tab = (active) => [
    "inline-flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors",
    active
        ? "bg-blue-600 text-white shadow"
        : "text-slate-300 hover:text-white",
];

// Switching tabs keeps your own draft. The old version wiped what you typed when you changed option.
const setMode = (next) => {
    if (next === mode.value) return;
    if (mode.value === "manual") manualDraft = replyMessage.value;

    mode.value = next;
    replyMessage.value = next === "ai" ? aiText.value : manualDraft;
    errorMessage.value = "";
    textarea.value?.focus();
};

const resetToSuggestion = () => {
    replyMessage.value = aiText.value;
    textarea.value?.focus();
};

// ---------------------------------------------------------------- dialog behaviour
const close = () => {
    if (!sending.value) emit("close");
};

const onKey = (e) => {
    if (e.key === "Escape") close();
};

onMounted(() => {
    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden"; // no scrolling the page behind the dialog
    textarea.value?.focus();
});

onBeforeUnmount(() => {
    document.removeEventListener("keydown", onKey);
    document.body.style.overflow = "";
});

// ---------------------------------------------------------------- sending
const getReplyUrl = (comment) => {
    const platform = comment.platform;

    switch (platform) {
        case "facebook":
        case "instagram":
            return `/inbox/${comment.id}/reply`;

        case "youtube":
            return `/youtube/comments/${comment.id}/reply`;

        case "twitter":
            return `/twitter/comments/${comment.id}/reply`;

        case "linkedin":
            return `/linkedin/comments/${comment.id}/reply`;

        default:
            throw new Error(`Unsupported platform: ${platform}`);
    }
};

// The endpoints answer with {error: "..."} for failures and {message, errors:{...}} for validation.
// The old code only read "message", so e.g. "Failed to publish reply" showed as a generic status text.
const explain = (error) => {
    const data = error.response?.data;

    return (
        data?.errors?.message?.[0] ||
        data?.error ||
        data?.message ||
        error.message ||
        "Reply failed"
    );
};

const sendReply = async () => {
    if (!canSend.value) return;

    const message = replyMessage.value.trim();

    sending.value = true;
    errorMessage.value = "";

    try {
        const url = getReplyUrl(props.comment);

        const { data } = await axios.post(url, {
            message,
            // true only when the AI text goes out as written. The old version always sent the stored
            // suggestion and silently ignored any edits made in the text box.
            is_ai_response:
                mode.value === "ai" && message === aiText.value.trim(),
        });

        toast.success("Reply sent successfully");
        emit("sent", data);
        emit("close");
    } catch (error) {
        errorMessage.value = explain(error);
        toast.error(errorMessage.value);
        console.error("Error sending reply:", error.response?.data ?? error);
    } finally {
        sending.value = false;
    }
};
</script>

<style scoped>
/* thin, dark-theme scrollbar */
.thin-scroll {
    scrollbar-width: thin;
    scrollbar-color: #475569 transparent;
}
.thin-scroll::-webkit-scrollbar {
    width: 6px;
}
.thin-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.thin-scroll::-webkit-scrollbar-thumb {
    background-color: #475569;
    border-radius: 9999px;
}
.thin-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #64748b;
}
</style>
