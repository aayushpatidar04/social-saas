<template>
    <AppLayout>
        <Head title="Choose YouTube channels" />

        <div class="mx-auto max-w-3xl">
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Choose YouTube channels
                </h1>
                <p class="mt-1 text-slate-400">
                    Google returned {{ channels.length }} YouTube channel{{
                        channels.length === 1 ? "" : "s"
                    }}. Select the channel{{
                        channels.length === 1 ? "" : "s"
                    }} you want to connect. Nothing is connected until you confirm.
                </p>
            </div>

            <section class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span :class="overLimit ? 'text-red-300' : 'text-slate-400'">
                        {{ chosenCount }} selected · {{ limits.used }} of
                        {{ limits.max ?? "unlimited" }} accounts already used
                    </span>
                    <button
                        type="button"
                        @click="toggleAll"
                        class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10"
                    >
                        {{ allSelected ? "Clear selection" : "Select all available" }}
                    </button>
                </div>
            </section>

            <form @submit.prevent="submit" class="space-y-3">
                <article
                    v-for="channel in channels"
                    :key="channel.id"
                    class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md"
                >
                    <img
                        v-if="channel.picture"
                        :src="channel.picture"
                        alt=""
                        class="h-12 w-12 rounded-full border border-white/10 bg-white/5 object-cover"
                    />
                    <div
                        v-else
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-red-500/30 bg-red-500/15 font-semibold text-red-200"
                    >
                        YT
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ channel.name }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ channel.handle || `Channel ID: ${channel.id}` }}
                        </p>
                    </div>
                    <label
                        v-if="channel.state === 'available'"
                        class="flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10"
                    >
                        <input
                            v-model="choice[channel.id]"
                            type="checkbox"
                            class="h-4 w-4 rounded border-white/20 bg-white/5 text-red-500 focus:ring-red-500/40"
                        />
                        Connect
                    </label>
                    <span
                        v-else-if="channel.state === 'connected'"
                        class="shrink-0 rounded-full bg-green-500/15 px-2.5 py-0.5 text-xs font-medium text-green-300"
                    >
                        Already connected
                    </span>
                    <span
                        v-else
                        class="shrink-0 rounded-full bg-yellow-500/15 px-2.5 py-0.5 text-xs font-medium text-yellow-300"
                        title="Each account can belong to one organization only"
                    >
                        In use by another organization
                    </span>
                </article>

                <p
                    v-if="overLimit"
                    class="rounded-2xl border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-200 backdrop-blur-md"
                >
                    Your selection exceeds your plan's account limit. Reduce your
                    selection or ask your administrator to raise the limit.
                </p>

                <div class="flex items-center gap-4 pt-4">
                    <button
                        type="submit"
                        :disabled="processing || !chosenCount"
                        class="rounded-lg border border-red-500/30 bg-red-500/20 px-6 py-2.5 text-sm font-medium text-red-100 transition-colors hover:bg-red-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ processing ? "Connecting…" : `Connect ${chosenCount || ""} selected` }}
                    </button>
                    <Link
                        href="/settings/social-accounts"
                        class="text-sm text-slate-400 transition-colors hover:text-slate-200"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Link from "@/Components/Link.vue";

const props = defineProps({
    pending: String,
    channels: Array,
    limits: Object,
});

const choice = ref(
    Object.fromEntries(props.channels.map((channel) => [channel.id, false])),
);
const processing = ref(false);
const available = computed(() =>
    props.channels.filter((channel) => channel.state === "available"),
);
const chosenCount = computed(
    () => Object.values(choice.value).filter(Boolean).length,
);
const allSelected = computed(
    () =>
        available.value.length > 0 &&
        available.value.every((channel) => choice.value[channel.id]),
);
const overLimit = computed(
    () =>
        props.limits.max !== null &&
        props.limits.used + chosenCount.value > props.limits.max,
);

const toggleAll = () => {
    const target = !allSelected.value;
    available.value.forEach((channel) => {
        choice.value[channel.id] = target;
    });
};

const submit = () => {
    processing.value = true;
    router.post(
        "/auth/youtube/select",
        {
            pending: props.pending,
            channel_ids: Object.entries(choice.value)
                .filter(([, selected]) => selected)
                .map(([id]) => id),
        },
        { onFinish: () => (processing.value = false) },
    );
};
</script>
