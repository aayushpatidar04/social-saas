<template>
    <AppLayout>
        <Head :title="lead.author_name || 'Lead'" />

        <div class="max-w-4xl">
            <a
                href="/leads"
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-blue-300 transition-colors hover:text-blue-200"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Back to Leads
            </a>

            <section :class="[card, 'p-8']">
                <!-- Header -->
                <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">
                            {{ lead.author_name }}
                        </h1>
                        <p class="mt-1 text-slate-400">Lead details</p>
                    </div>

                    <div class="flex items-center gap-3 text-sm text-slate-400">
                        <span>Lead score</span>
                        <div
                            class="h-1.5 w-24 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-1.5 rounded-full bg-emerald-500"
                                :style="{ width: clamp(lead.lead_score) + '%' }"
                            ></div>
                        </div>
                        <span class="text-lg font-bold tabular-nums text-white">{{
                            lead.lead_score ?? 0
                        }}</span>
                    </div>
                </div>

                <div class="grid gap-8 md:grid-cols-2">
                    <!-- Details -->
                    <div class="space-y-4">
                        <div>
                            <label :class="label">Email</label>
                            <p :class="value">
                                {{ lead.contact_email || "N/A" }}
                            </p>
                        </div>
                        <div>
                            <label :class="label">Phone</label>
                            <p :class="value">
                                {{ lead.contact_phone || "N/A" }}
                            </p>
                        </div>
                        <div>
                            <label :class="label">Company</label>
                            <p :class="value">
                                {{ lead.company_name || "N/A" }}
                            </p>
                        </div>
                        <div>
                            <label :class="label">Status</label>
                            <select :class="[field, 'capitalize']">
                                <option :value="lead.lead_status" selected>
                                    {{ lead.lead_status }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Message -->
                    <div>
                        <label :class="label">Initial message</label>
                        <div
                            :class="[
                                'min-h-40 whitespace-pre-line rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-slate-300',
                            ]"
                        >
                            {{ lead.initial_message }}
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-8">
                    <label :class="label">Notes</label>
                    <textarea
                        rows="6"
                        :value="lead.notes"
                        placeholder="Add notes about this lead..."
                        :class="field"
                    ></textarea>
                </div>

                <button
                    class="mt-6 rounded-lg border border-blue-500/30 bg-blue-500/20 px-5 py-2 text-sm font-medium text-blue-100 transition-colors hover:bg-blue-500/30"
                >
                    Update Lead
                </button>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";

defineProps({
    lead: Object,
});

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const label = "mb-1.5 block text-xs font-medium text-slate-400";

const value =
    "rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-100";

const field =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none [&>option]:bg-[#0b1230]";

const clamp = (v) => Math.min(100, Math.max(0, Number(v ?? 0)));
</script>