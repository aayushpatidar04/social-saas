<template>
    <AppLayout>
        <Head title="Choose what to connect" />

        <div class="mx-auto max-w-3xl">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Choose what to connect
                </h1>
                <p class="mt-1 text-slate-400">
                    Facebook returned {{ pages.length }} Page{{
                        pages.length === 1 ? "" : "s"
                    }}. Tick the Pages and Instagram accounts you want to
                    manage here. Nothing is connected until you confirm.
                </p>
            </div>

            <!-- Selection summary -->
            <section :class="[card, 'mb-4 !p-4']">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1.5 flex flex-wrap justify-between gap-x-4 text-xs">
                            <span :class="overLimit ? 'text-red-300' : 'text-slate-400'">
                                {{ chosenCount }} selected · {{ limits.used }} of
                                {{ limits.max ?? "unlimited" }} accounts already
                                used
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="h-1.5 rounded-full transition-all duration-500"
                                :class="overLimit ? 'bg-red-500' : 'bg-emerald-500'"
                                :style="{ width: usagePercent + '%' }"
                            ></div>
                        </div>
                    </div>
                    <button type="button" @click="toggleAll" :class="ghostBtn">
                        {{ allSelected ? "Clear selection" : "Select all available" }}
                    </button>
                </div>
            </section>

            <form @submit.prevent="submit" class="space-y-3">
                <article
                    v-for="p in pages"
                    :key="p.id"
                    class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md transition-all hover:border-white/20 hover:bg-white/10"
                >
                    <div class="flex items-center gap-4">
                        <img
                            v-if="p.picture"
                            :src="p.picture"
                            alt=""
                            class="h-12 w-12 rounded-full border border-white/10 bg-white/5 object-cover"
                        />
                        <div
                            v-else
                            class="flex h-12 w-12 items-center justify-center rounded-full border border-blue-500/30 bg-blue-500/15"
                        >
                            <DocumentTextIcon class="h-6 w-6 text-blue-300" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ p.name }}</p>
                            <p class="text-xs text-slate-500">
                                <span
                                    class="mr-1 inline-block h-2 w-2 rounded-full bg-blue-500"
                                ></span>
                                Facebook Page{{ p.category ? ` · ${p.category}` : "" }}
                            </p>
                        </div>
                        <StateOrCheck
                            :state="p.state"
                            v-model="choice[p.id].page"
                            label="Connect Page"
                        />
                    </div>

                    <div
                        v-if="p.instagram"
                        class="mt-3 ml-6 flex items-center gap-4 border-t border-white/10 pt-3"
                    >
                        <img
                            v-if="p.instagram.picture"
                            :src="p.instagram.picture"
                            alt=""
                            class="h-10 w-10 rounded-full border border-white/10 bg-white/5 object-cover"
                        />
                        <div
                            v-else
                            class="flex h-10 w-10 items-center justify-center rounded-full border border-pink-500/30 bg-gradient-to-br from-pink-500/25 to-orange-500/25"
                        >
                            <CameraIcon class="h-5 w-5 text-pink-200" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                @{{ p.instagram.username || p.instagram.id }}
                            </p>
                            <p class="text-xs text-slate-500">
                                <span
                                    class="mr-1 inline-block h-2 w-2 rounded-full bg-pink-500"
                                ></span>
                                Instagram account linked to this Page
                            </p>
                        </div>
                        <StateOrCheck
                            :state="p.instagram.state"
                            v-model="choice[p.id].instagram"
                            label="Connect Instagram"
                        />
                    </div>
                    <p v-else class="ml-6 mt-3 text-xs text-slate-600">
                        No Instagram professional account is linked to this Page.
                    </p>
                </article>

                <p
                    v-if="overLimit"
                    class="rounded-2xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-200 backdrop-blur-md"
                >
                    You selected more than your plan allows; the first ones will
                    connect and the rest will be skipped.
                </p>

                <div class="flex items-center gap-4 pt-4">
                    <button
                        type="submit"
                        :disabled="processing || !chosenCount"
                        class="rounded-lg border border-blue-500/30 bg-blue-500/20 px-6 py-2.5 text-sm font-medium text-blue-100 transition-colors hover:bg-blue-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            processing
                                ? "Connecting…"
                                : `Connect ${chosenCount || ""} selected`
                        }}
                    </button>
                    <Link
                        href="/settings/social-accounts"
                        class="text-sm text-slate-400 transition-colors hover:text-slate-200"
                        >Cancel</Link
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { CameraIcon, DocumentTextIcon } from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import Link from "@/Components/Link.vue";

const props = defineProps({ pending: String, pages: Array, limits: Object });

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const ghostBtn =
    "rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10";

// small inline helper: a checkbox, or the reason it cannot be ticked
const StateOrCheck = defineComponent({
    props: { state: String, modelValue: Boolean, label: String },
    emits: ["update:modelValue"],
    setup(p, { emit }) {
        return () => {
            if (p.state === "connected")
                return h(
                    "span",
                    {
                        class: "shrink-0 rounded-full bg-green-500/15 px-2.5 py-0.5 text-xs font-medium text-green-300",
                    },
                    "✓ Already connected",
                );
            if (p.state === "other_organization")
                return h(
                    "span",
                    {
                        class: "shrink-0 rounded-full bg-yellow-500/15 px-2.5 py-0.5 text-xs font-medium text-yellow-300",
                        title: "Each account can belong to one organization only",
                    },
                    "In use by another organization",
                );
            return h(
                "label",
                {
                    class: "flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10",
                },
                [
                    h("input", {
                        type: "checkbox",
                        checked: p.modelValue,
                        class: "h-4 w-4 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40",
                        onChange: (e) =>
                            emit("update:modelValue", e.target.checked),
                    }),
                    p.label,
                ],
            );
        };
    },
});

// ---------------------------------------------------------------- state
const choice = reactive(
    Object.fromEntries(
        props.pages.map((p) => [p.id, { page: false, instagram: false }]),
    ),
);
const processing = ref(false);

const available = () =>
    props.pages.flatMap((p) =>
        [
            p.state === "available" ? { id: p.id, key: "page" } : null,
            p.instagram?.state === "available"
                ? { id: p.id, key: "instagram" }
                : null,
        ].filter(Boolean),
    );

const chosenCount = computed(() =>
    Object.values(choice).reduce(
        (n, c) => n + (c.page ? 1 : 0) + (c.instagram ? 1 : 0),
        0,
    ),
);
const allSelected = computed(
    () =>
        available().length > 0 &&
        available().every((a) => choice[a.id][a.key]),
);
const overLimit = computed(
    () =>
        props.limits.max !== null &&
        props.limits.used + chosenCount.value > props.limits.max,
);

// bar fills with already-used + currently selected accounts; empty when the plan is unlimited
const usagePercent = computed(() => {
    const max = props.limits.max;
    if (!max) return 0;
    return Math.min(
        100,
        Math.round(((props.limits.used + chosenCount.value) / max) * 100),
    );
});

const toggleAll = () => {
    const target = !allSelected.value;
    available().forEach((a) => (choice[a.id][a.key] = target));
};

const submit = () => {
    processing.value = true;
    router.post(
        "/auth/facebook/select",
        {
            pending: props.pending,
            selections: Object.entries(choice).map(([page_id, c]) => ({
                page_id,
                page: c.page,
                instagram: c.instagram,
            })),
        },
        { onFinish: () => (processing.value = false) },
    );
};
</script>