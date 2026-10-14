<template>
    <AppLayout>
        <Head title="Leads" />

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Leads</h1>
            <p class="mt-1 text-slate-400">
                Commenters identified as potential leads by AI
            </p>
        </div>

        <!-- Summary cards -->
        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div
                v-for="s in summary"
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Filters -->
            <div class="lg:col-span-1">
                <section :class="[card, 'space-y-4 lg:sticky lg:top-6']">
                    <h3 class="text-lg font-bold">Filters</h3>

                    <div>
                        <label :class="label">Status</label>
                        <select
                            v-model="filters.status"
                            @change="applyFilters"
                            :class="field"
                        >
                            <option value="">All Status</option>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>

                    <div>
                        <label :class="label">Type</label>
                        <select
                            v-model="filters.type"
                            @change="applyFilters"
                            :class="field"
                        >
                            <option value="">All Types</option>
                            <option value="sales">Sales</option>
                            <option value="support">Support</option>
                            <option value="partnership">Partnership</option>
                        </select>
                    </div>

                    <div>
                        <label :class="label">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
                            />
                            <input
                                v-model="filters.search"
                                @input="debouncedFilter"
                                type="text"
                                placeholder="Name, email, company..."
                                :class="[field, 'pl-9']"
                            />
                        </div>
                    </div>
                </section>
            </div>

            <!-- Leads List -->
            <div class="lg:col-span-3">
                <div v-if="loading" class="py-12 text-center">
                    <div class="inline-flex items-center gap-3 text-slate-400">
                        <ArrowPathIcon class="h-5 w-5 animate-spin" />
                        Loading leads...
                    </div>
                </div>

                <section
                    v-else-if="leads.data.length === 0"
                    :class="[card, 'py-12 text-center']"
                >
                    <p class="mb-1 text-slate-300">No leads found</p>
                    <p class="text-sm text-slate-500">
                        Leads are auto-created when AI detects sales intent or
                        high-value comments.
                    </p>
                </section>

                <div v-else class="space-y-3">
                    <article
                        v-for="lead in leads.data"
                        :key="lead.id"
                        class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:border-white/20 hover:bg-white/10"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-lg font-bold">
                                        {{ lead.author_name }}
                                    </h3>
                                    <span
                                        :class="getTypeColor(lead.lead_type)"
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                    >
                                        {{ lead.lead_type }}
                                    </span>
                                    <span
                                        :class="getStatusColor(lead.lead_status)"
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                    >
                                        {{ lead.lead_status }}
                                    </span>
                                </div>

                                <div
                                    class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-400"
                                >
                                    <span
                                        v-if="lead.company_name"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <BuildingOffice2Icon class="h-4 w-4" />
                                        {{ lead.company_name }}
                                    </span>
                                    <span
                                        v-if="lead.contact_email"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <EnvelopeIcon class="h-4 w-4" />
                                        {{ lead.contact_email }}
                                    </span>
                                    <span
                                        v-if="lead.contact_phone"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <PhoneIcon class="h-4 w-4" />
                                        {{ lead.contact_phone }}
                                    </span>
                                    <span
                                        v-if="lead.assignedTo"
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <UserIcon class="h-4 w-4" />
                                        {{ lead.assignedTo.name }}
                                    </span>
                                </div>

                                <!-- Lead score -->
                                <div
                                    class="mb-3 flex items-center gap-3 text-xs text-slate-400"
                                >
                                    <span>Lead score</span>
                                    <div
                                        class="h-1.5 w-24 overflow-hidden rounded-full bg-white/10"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-emerald-500"
                                            :style="{
                                                width: clamp(lead.lead_score) + '%',
                                            }"
                                        ></div>
                                    </div>
                                    <span
                                        class="font-semibold tabular-nums text-white"
                                        >{{ lead.lead_score ?? 0 }}</span
                                    >
                                </div>

                                <p class="mb-2 line-clamp-2 text-sm text-slate-300">
                                    {{ lead.initial_message }}
                                </p>

                                <div
                                    v-if="lead.socialComment"
                                    class="text-xs text-slate-500"
                                >
                                    <span
                                        class="mr-1 inline-block h-2 w-2 rounded-full"
                                        :class="
                                            platformColor(
                                                lead.socialComment.platform,
                                            )
                                        "
                                    ></span>
                                    <span class="capitalize">{{
                                        lead.socialComment.platform
                                    }}</span>
                                    comment
                                    <span v-if="lead.socialComment.socialAccount"
                                        >on
                                        {{
                                            lead.socialComment.socialAccount
                                                .platform_account_name
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <LoadingButton
                                    v-if="lead.lead_status === 'new'"
                                    :loading="loadingStatus[lead.id]"
                                    :loading-text="'Saving...'"
                                    :base-classes="btn.blue"
                                    @click="updateStatus(lead.id, 'contacted')"
                                >
                                    Mark Contacted
                                </LoadingButton>
                                <LoadingButton
                                    v-if="lead.lead_status === 'contacted'"
                                    :loading="loadingStatus[lead.id]"
                                    :loading-text="'Saving...'"
                                    :base-classes="btn.purple"
                                    @click="updateStatus(lead.id, 'qualified')"
                                >
                                    Mark Qualified
                                </LoadingButton>
                                <LoadingButton
                                    v-if="
                                        lead.lead_status !== 'converted' &&
                                        lead.lead_status !== 'lost'
                                    "
                                    :loading="loadingStatus[lead.id]"
                                    :loading-text="'Saving...'"
                                    :base-classes="btn.green"
                                    @click="updateStatus(lead.id, 'converted')"
                                >
                                    Mark Converted
                                </LoadingButton>
                                <LoadingButton
                                    v-if="lead.lead_status !== 'lost'"
                                    :loading="loadingStatus[lead.id]"
                                    :loading-text="'Saving...'"
                                    :base-classes="btn.red"
                                    @click="updateStatus(lead.id, 'lost')"
                                >
                                    Mark Lost
                                </LoadingButton>
                                <a
                                    :href="`/comments/${lead.socialComment?.id}`"
                                    v-if="lead.socialComment"
                                    class="text-xs text-blue-300 transition-colors hover:text-blue-200"
                                >
                                    View comment →
                                </a>
                            </div>
                        </div>
                    </article>

                    <!-- Pagination -->
                    <div
                        v-if="leads.last_page > 1"
                        class="mt-6 flex flex-wrap justify-center gap-2"
                    >
                        <button
                            v-for="page in leads.last_page"
                            :key="page"
                            @click="goToPage(page)"
                            :class="[
                                'rounded-lg border px-3 py-1.5 text-sm transition-colors',
                                page === leads.current_page
                                    ? 'border-blue-500/40 bg-blue-500/20 text-blue-200'
                                    : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10',
                            ]"
                        >
                            {{ page }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { Head, router } from "@inertiajs/vue3";
import axios from "axios";
import {
    ArrowPathIcon,
    BuildingOffice2Icon,
    CheckBadgeIcon,
    EnvelopeIcon,
    MagnifyingGlassIcon,
    PhoneIcon,
    StarIcon,
    UserIcon,
    UserPlusIcon,
} from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import { toast } from "@/composables/useToast";
import LoadingButton from "@/Components/LoadingButton.vue";

const props = defineProps({
    leads: {
        type: Object,
        default: () => ({ data: [] }),
    },
    stats: {
        type: Object,
        default: () => ({ new: 0, contacted: 0, converted: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const label = "mb-2 block text-xs font-medium text-slate-400";

const field =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none [&>option]:bg-[#0b1230]";

const btnBase =
    "rounded-lg border px-3 py-1.5 text-xs transition-colors";
const btn = {
    blue: `${btnBase} border-blue-500/30 bg-blue-500/20 text-blue-100 hover:bg-blue-500/30`,
    purple: `${btnBase} border-purple-500/30 bg-purple-500/20 text-purple-100 hover:bg-purple-500/30`,
    green: `${btnBase} border-emerald-500/30 bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25`,
    red: `${btnBase} border-red-500/30 bg-red-500/15 text-red-200 hover:bg-red-500/25`,
};

// ---------------------------------------------------------------- state
const loading = ref(false);
const loadingStatus = reactive({});
const filters = reactive({
    status: props.filters.status || "",
    type: props.filters.type || "",
    search: "",
});

const summary = computed(() => [
    {
        label: "Total leads",
        value: props.leads.total ?? props.leads.data?.length ?? 0,
        icon: UserPlusIcon,
        tone: "text-white",
        glow: "bg-slate-300",
    },
    {
        label: "New",
        value: props.stats.new ?? 0,
        icon: StarIcon,
        tone: "text-yellow-400",
        glow: "bg-yellow-400",
    },
    {
        label: "Contacted",
        value: props.stats.contacted ?? 0,
        icon: EnvelopeIcon,
        tone: "text-blue-400",
        glow: "bg-blue-400",
    },
    {
        label: "Converted",
        value: props.stats.converted ?? 0,
        icon: CheckBadgeIcon,
        tone: "text-green-400",
        glow: "bg-green-400",
    },
]);

let debounceTimer = null;

const debouncedFilter = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => applyFilters(), 300);
};

const applyFilters = () => {
    const params = {};
    if (filters.status) params.status = filters.status;
    if (filters.type) params.type = filters.type;
    if (filters.search) params.search = filters.search;

    router.get("/leads", params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const goToPage = (page) => {
    const params = { page };
    if (filters.status) params.status = filters.status;
    if (filters.type) params.type = filters.type;
    if (filters.search) params.search = filters.search;

    router.get("/leads", params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const updateStatus = async (leadId, status) => {
    loadingStatus[leadId] = true;
    try {
        await axios.post(`/leads/${leadId}/update-status`, { status });
        toast.success(`Lead marked as ${status}`);
        router.reload({ only: ["leads"] });
    } catch (error) {
        toast.error(
            error.response?.data?.message || "Failed to update lead status",
        );
        console.error(
            "Failed to update lead status:",
            error.response?.data || error.message,
        );
    } finally {
        loadingStatus[leadId] = false;
    }
};

// ---------------------------------------------------------------- colors
const clamp = (v) => Math.min(100, Math.max(0, Number(v ?? 0)));

const getStatusColor = (status) => {
    const colors = {
        new: "bg-yellow-500/15 text-yellow-300",
        contacted: "bg-blue-500/15 text-blue-300",
        qualified: "bg-purple-500/15 text-purple-300",
        converted: "bg-green-500/15 text-green-300",
        lost: "bg-red-500/15 text-red-300",
    };
    return colors[status] || "bg-slate-500/20 text-slate-300";
};

const getTypeColor = (type) => {
    const colors = {
        sales: "bg-blue-500/15 text-blue-300",
        support: "bg-purple-500/15 text-purple-300",
        partnership: "bg-emerald-500/15 text-emerald-300",
    };
    return colors[type] || "bg-slate-500/20 text-slate-300";
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

onMounted(() => {
    filters.status = props.filters.status || "";
    filters.type = props.filters.type || "";
});
</script>