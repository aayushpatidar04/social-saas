<template>
    <AppLayout>
        <Head title="Team" />

        <div class="mx-auto max-w-4xl">
            <!-- Header -->
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                        Team
                    </h1>
                    <p class="mt-1 text-slate-400">
                        Invite people and manage their roles
                    </p>
                </div>

                <!-- Plan usage -->
                <div class="w-48">
                    <div class="mb-1.5 flex justify-between text-xs text-slate-400">
                        <span>Users</span>
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

            <p v-if="!canManage" class="mb-6 text-sm text-slate-400">
                Only organization admins can invite or remove people.
            </p>

            <InviteLink :url="$page.props.flash.invite_url" class="mb-6" />

            <!-- Invite form -->
            <form
                v-if="canManage"
                @submit.prevent="invite"
                :class="[card, 'mb-8 flex flex-wrap items-start gap-3']"
            >
                <div class="min-w-[16rem] flex-1">
                    <input
                        v-model="inviteForm.email"
                        type="email"
                        required
                        placeholder="colleague@company.com"
                        :class="field"
                    />
                    <p
                        v-if="inviteForm.errors.email"
                        class="mt-1 text-sm text-red-300"
                    >
                        {{ inviteForm.errors.email }}
                    </p>
                </div>
                <select v-model="inviteForm.role" :class="[field, 'w-auto']">
                    <option v-for="r in roles" :key="r" :value="r">
                        {{ label(r) }}
                    </option>
                </select>
                <button
                    :disabled="inviteForm.processing"
                    class="rounded-lg border border-blue-500/30 bg-blue-500/20 px-4 py-2 text-sm text-blue-100 transition-colors hover:bg-blue-500/30 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ inviteForm.processing ? "Sending..." : "Send invitation" }}
                </button>
            </form>

            <!-- Members -->
            <section
                class="overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-md"
            >
                <table class="w-full text-sm">
                    <tbody>
                        <tr
                            v-for="m in teamMembers"
                            :key="m.id"
                            class="border-b border-white/10 transition-colors last:border-0 hover:bg-white/[0.04]"
                        >
                            <td class="px-6 py-4">
                                <p class="font-medium">
                                    {{ m.name }}
                                    <span
                                        v-if="m.id === currentUserId"
                                        class="ml-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-300"
                                        >you</span
                                    >
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ m.email }}
                                </p>
                            </td>
                            <td class="px-3 py-4">
                                <select
                                    v-if="canManage && m.id !== currentUserId"
                                    :value="m.role"
                                    @change="changeRole(m, $event.target.value)"
                                    class="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-sm text-slate-100 transition-colors focus:border-blue-500/50 focus:outline-none [&>option]:bg-[#0b1230]"
                                >
                                    <option v-for="r in roles" :key="r" :value="r">
                                        {{ label(r) }}
                                    </option>
                                </select>
                                <span
                                    v-else
                                    class="rounded-full bg-slate-500/20 px-2.5 py-0.5 text-xs font-medium text-slate-300"
                                    >{{ label(m.role) }}</span
                                >
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    v-if="canManage && m.id !== currentUserId"
                                    @click="remove(m)"
                                    class="text-sm text-red-300 transition-colors hover:text-red-200"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Pending invitations -->
            <section v-if="invitations.length" :class="[card, 'mt-8']">
                <h3 class="mb-4 text-lg font-bold">Pending invitations</h3>
                <div class="space-y-2">
                    <div
                        v-for="i in invitations"
                        :key="i.id"
                        class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm"
                    >
                        <span class="min-w-0 text-slate-200">
                            {{ i.email }}
                            <span class="text-slate-500"
                                >as {{ label(i.role) }} · expires
                                {{ new Date(i.expires_at).toLocaleDateString() }}</span
                            >
                        </span>
                        <button
                            v-if="canManage"
                            @click="cancel(i)"
                            class="shrink-0 rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-200 transition-colors hover:bg-white/10"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import InviteLink from "@/Components/InviteLink.vue";

const props = defineProps({
    teamMembers: Array,
    invitations: Array,
    currentUserId: Number,
    canManage: Boolean,
    roles: Array,
    limits: Object,
});

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const field =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none [&>option]:bg-[#0b1230]";

const usagePercent = computed(() => {
    const { used, max } = props.limits ?? {};
    if (!max) return 0;
    return Math.min(100, Math.round(((used ?? 0) / max) * 100));
});

// ---------------------------------------------------------------- actions
const label = (r) =>
    r.replace("_", " ").replace(/^./, (c) => c.toUpperCase());

const inviteForm = useForm({ email: "", role: "team_member" });
const invite = () =>
    inviteForm.post("/settings/team/invite", {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset("email"),
    });

const changeRole = (m, role) =>
    router.patch(
        `/settings/team/${m.id}/role`,
        { role },
        { preserveScroll: true },
    );
const remove = (m) => {
    if (confirm(`Remove ${m.name} from the team?`))
        router.delete(`/settings/team/${m.id}`, { preserveScroll: true });
};
const cancel = (i) =>
    router.delete(`/settings/team/invitations/${i.id}`, {
        preserveScroll: true,
    });
</script>