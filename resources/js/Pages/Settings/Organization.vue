<template>
    <AppLayout>
        <Head title="Organization" />

        <div class="mx-auto max-w-3xl">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Organization
                </h1>
                <p class="mt-1 text-slate-400">
                    Plan, usage and how AI works for your team
                </p>
            </div>

            <!-- Summary cards -->
            <div class="mb-3 grid gap-4 sm:grid-cols-3">
                <div :class="statCard">
                    <div :class="[glow, 'bg-purple-400']"></div>
                    <SparklesIcon class="mb-3 h-6 w-6 text-purple-400" />
                    <p :class="statLabel">Plan</p>
                    <p class="mt-1 text-3xl font-bold capitalize text-purple-400">
                        {{ organization.plan }}
                    </p>
                    <p
                        v-if="organization.plan_expires_at"
                        class="mt-1 text-xs text-slate-500"
                    >
                        until
                        {{ new Date(organization.plan_expires_at).toLocaleDateString() }}
                    </p>
                </div>

                <div :class="statCard">
                    <div :class="[glow, 'bg-blue-400']"></div>
                    <UsersIcon class="mb-3 h-6 w-6 text-blue-400" />
                    <p :class="statLabel">Users</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-blue-400">
                        {{ usage.users.used }}
                        <span class="text-base font-normal text-slate-500"
                            >/ {{ usage.users.max ?? "∞" }}</span
                        >
                    </p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-1.5 rounded-full bg-blue-500 transition-all duration-500"
                            :style="{ width: percent(usage.users) + '%' }"
                        ></div>
                    </div>
                </div>

                <div :class="statCard">
                    <div :class="[glow, 'bg-emerald-400']"></div>
                    <LinkIcon class="mb-3 h-6 w-6 text-emerald-400" />
                    <p :class="statLabel">Connected accounts</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-emerald-400">
                        {{ usage.social_accounts.used }}
                        <span class="text-base font-normal text-slate-500"
                            >/ {{ usage.social_accounts.max ?? "∞" }}</span
                        >
                    </p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-1.5 rounded-full bg-emerald-500 transition-all duration-500"
                            :style="{ width: percent(usage.social_accounts) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>
            <p class="mb-8 text-sm text-slate-500">
                Plan and limits are managed by the platform owner.
            </p>

            <form
                @submit.prevent="
                    form.patch('/settings/organization', { preserveScroll: true })
                "
                class="space-y-6"
            >
                <!-- Details -->
                <section :class="[card, 'space-y-5']">
                    <h3 class="text-lg font-bold">Details</h3>

                    <div>
                        <label :class="label">Name</label>
                        <input
                            v-model="form.name"
                            required
                            :disabled="!canEdit"
                            :class="input"
                        />
                        <p v-if="form.errors.name" :class="errorText">
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div>
                        <label :class="label">Website</label>
                        <input
                            v-model="form.website"
                            type="url"
                            placeholder="https://"
                            :disabled="!canEdit"
                            :class="input"
                        />
                        <p v-if="form.errors.website" :class="errorText">
                            {{ form.errors.website }}
                        </p>
                    </div>
                    <div>
                        <label :class="label">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            :disabled="!canEdit"
                            :class="input"
                        />
                    </div>
                </section>

                <!-- AI prompts -->
                <section :class="[card, 'space-y-5']">
                    <div>
                        <h3 class="text-lg font-bold">AI prompts</h3>
                        <p class="mt-1 text-sm text-slate-400">
                            Customize how AI analyzes comments and writes
                            replies for this organization. Use the listed
                            placeholders to insert comment and conversation
                            data.
                        </p>
                    </div>

                    <div v-for="prompt in promptFields" :key="prompt.key">
                        <label :class="label">{{ prompt.label }}</label>
                        <div class="mb-2 flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-slate-500">Placeholders:</span>
                            <code
                                v-for="ph in prompt.placeholders"
                                :key="ph"
                                class="rounded-md border border-white/10 bg-white/5 px-1.5 py-0.5 text-xs text-blue-300"
                                >{{ ph }}</code
                            >
                        </div>
                        <textarea
                            v-model="form.ai_prompts[prompt.key]"
                            rows="7"
                            maxlength="10000"
                            :disabled="!canEdit"
                            :class="[input, 'thin-scroll font-mono text-[13px] leading-relaxed']"
                        />
                        <p
                            v-if="form.errors[`ai_prompts.${prompt.key}`]"
                            :class="errorText"
                        >
                            {{ form.errors[`ai_prompts.${prompt.key}`] }}
                        </p>
                    </div>
                </section>

                <div>
                    <button
                        v-if="canEdit"
                        :disabled="form.processing"
                        class="rounded-lg border border-blue-500/30 bg-blue-500/20 px-6 py-2 text-sm font-medium text-blue-100 transition-colors hover:bg-blue-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ form.processing ? "Saving..." : "Save" }}
                    </button>
                    <p v-else class="text-sm text-slate-400">
                        Only organization admins can edit these details and
                        prompts.
                    </p>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { LinkIcon, SparklesIcon, UsersIcon } from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    organization: Object,
    usage: Object,
    aiPrompts: Object,
    canEdit: Boolean,
});

const form = useForm({
    name: props.organization.name ?? "",
    website: props.organization.website ?? "",
    description: props.organization.description ?? "",
    ai_prompts: { ...props.aiPrompts },
});

const promptFields = [
    { key: "analysis", label: "Comment analysis", placeholders: ["{comment}"] },
    {
        key: "response",
        label: "AI reply",
        placeholders: [
            "{comment}",
            "{author_name}",
            "{knowledge}",
            "{conversation_history}",
        ],
    },
    {
        key: "intent",
        label: "Intent classification",
        placeholders: ["{comment}"],
    },
    {
        key: "sentiment",
        label: "Sentiment analysis",
        placeholders: ["{comment}"],
    },
    {
        key: "lead_detection",
        label: "Lead detection",
        placeholders: ["{comment}"],
    },
];

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const statCard =
    "group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:-translate-y-0.5 hover:border-white/20 hover:bg-white/10";

const glow =
    "absolute -right-6 -top-6 h-20 w-20 rounded-full opacity-20 blur-2xl";

const statLabel = "text-xs font-medium uppercase tracking-wide text-slate-400";

const label = "mb-2 block text-sm font-medium text-slate-300";

const errorText = "mt-1 text-sm text-red-300";

const input =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60";

// usage bar: empty when the plan has no limit
const percent = (u) =>
    u?.max ? Math.min(100, Math.round(((u.used ?? 0) / u.max) * 100)) : 0;
</script>

<style scoped>
/* thin, dark-theme scrollbar */
.thin-scroll {
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
.thin-scroll::-webkit-scrollbar {
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