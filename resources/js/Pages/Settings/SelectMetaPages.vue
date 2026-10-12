<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-bold text-white mb-2">Choose what to connect</h1>
            <p class="text-slate-400 mb-6">
                Facebook returned {{ pages.length }} Page{{ pages.length === 1 ? "" : "s" }}. Tick the Pages and Instagram
                accounts you want to manage here. Nothing is connected until you confirm.
            </p>

            <div class="flex items-center justify-between mb-4 text-sm">
                <p :class="overLimit ? 'text-red-400' : 'text-slate-400'">
                    {{ chosenCount }} selected · {{ limits.used }} of {{ limits.max ?? "unlimited" }} accounts already used
                </p>
                <button type="button" @click="toggleAll" class="text-blue-400 hover:text-blue-300">
                    {{ allSelected ? "Clear selection" : "Select all available" }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-3">
                <div v-for="p in pages" :key="p.id" class="bg-slate-800 border border-slate-700 rounded-lg p-4">
                    <div class="flex items-center gap-4">
                        <img v-if="p.picture" :src="p.picture" alt="" class="w-12 h-12 rounded-full bg-slate-700" />
                        <div v-else class="w-12 h-12 rounded-full bg-slate-700 flex items-center justify-center text-xl">📄</div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-white truncate">{{ p.name }}</p>
                            <p class="text-xs text-slate-500">Facebook Page{{ p.category ? ` · ${p.category}` : "" }}</p>
                        </div>
                        <StateOrCheck :state="p.state" v-model="choice[p.id].page" label="Connect Page" />
                    </div>

                    <div v-if="p.instagram" class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-700/70 ml-6">
                        <img v-if="p.instagram.picture" :src="p.instagram.picture" alt="" class="w-10 h-10 rounded-full bg-slate-700" />
                        <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-600 to-orange-500 flex items-center justify-center">📷</div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-white truncate">@{{ p.instagram.username || p.instagram.id }}</p>
                            <p class="text-xs text-slate-500">Instagram account linked to this Page</p>
                        </div>
                        <StateOrCheck :state="p.instagram.state" v-model="choice[p.id].instagram" label="Connect Instagram" />
                    </div>
                    <p v-else class="text-xs text-slate-600 mt-3 ml-6">No Instagram professional account is linked to this Page.</p>
                </div>

                <div class="flex items-center gap-4 pt-4">
                    <button type="submit" :disabled="processing || !chosenCount"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-600 disabled:cursor-not-allowed text-white font-medium rounded-lg">
                        {{ processing ? "Connecting…" : `Connect ${chosenCount || ""} selected` }}
                    </button>
                    <Link href="/settings/social-accounts" class="text-slate-400">Cancel</Link>
                </div>
                <p v-if="overLimit" class="text-sm text-yellow-400">
                    You selected more than your plan allows; the first ones will connect and the rest will be skipped.
                </p>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Link from "@/Components/Link.vue";

const props = defineProps({ pending: String, pages: Array, limits: Object });

// small inline helper: a checkbox, or the reason it cannot be ticked
const StateOrCheck = defineComponent({
    props: { state: String, modelValue: Boolean, label: String },
    emits: ["update:modelValue"],
    setup(p, { emit }) {
        return () => {
            if (p.state === "connected") return h("span", { class: "text-sm text-green-400" }, "✓ Already connected");
            if (p.state === "other_organization")
                return h("span", { class: "text-sm text-yellow-400", title: "Each account can belong to one organization only" }, "In use by another organization");
            return h("label", { class: "flex items-center gap-2 text-sm text-slate-300 cursor-pointer" }, [
                h("input", {
                    type: "checkbox",
                    checked: p.modelValue,
                    class: "w-4 h-4 rounded bg-slate-900 border-slate-600 text-blue-500",
                    onChange: (e) => emit("update:modelValue", e.target.checked),
                }),
                p.label,
            ]);
        };
    },
});

const choice = reactive(Object.fromEntries(props.pages.map((p) => [p.id, { page: false, instagram: false }])));
const processing = ref(false);

const available = () => props.pages.flatMap((p) => [
    p.state === "available" ? { id: p.id, key: "page" } : null,
    p.instagram?.state === "available" ? { id: p.id, key: "instagram" } : null,
].filter(Boolean));

const chosenCount = computed(() => Object.values(choice).reduce((n, c) => n + (c.page ? 1 : 0) + (c.instagram ? 1 : 0), 0));
const allSelected = computed(() => available().length > 0 && available().every((a) => choice[a.id][a.key]));
const overLimit = computed(() => props.limits.max !== null && props.limits.used + chosenCount.value > props.limits.max);

const toggleAll = () => {
    const target = !allSelected.value;
    available().forEach((a) => (choice[a.id][a.key] = target));
};

const submit = () => {
    processing.value = true;
    router.post("/auth/facebook/select", {
        pending: props.pending,
        selections: Object.entries(choice).map(([page_id, c]) => ({ page_id, page: c.page, instagram: c.instagram })),
    }, { onFinish: () => (processing.value = false) });
};
</script>
