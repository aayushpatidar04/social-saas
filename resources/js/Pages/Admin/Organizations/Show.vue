<template>
    <AdminLayout>
        <Head :title="organization.name" />

        <Link href="/admin/organizations" class="inline-flex items-center gap-1 text-sm">
            <ArrowLeftIcon class="h-4 w-4" /> Organizations
        </Link>

        <!-- Header -->
        <div class="mb-6 mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <span
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/40 to-indigo-500/40 text-2xl font-bold"
                >
                    {{ (organization.name || '?')[0].toUpperCase() }}
                </span>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">{{ organization.name }}</h1>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ organization.slug }} · created {{ new Date(organization.created_at).toLocaleDateString() }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-sm font-medium" :class="statusClass">{{ statusLabel }}</span>
                <button
                    v-if="organization.suspended_at || !organization.is_active"
                    @click="activate"
                    class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-600"
                >
                    Reactivate
                </button>
                <button
                    v-else
                    @click="showSuspend = true"
                    class="rounded-lg bg-yellow-700 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600"
                >
                    Suspend
                </button>
            </div>
        </div>

        <p
            v-if="organization.suspended_at"
            class="mb-6 flex items-start gap-3 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200"
        >
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
            <span>
                Suspended{{ organization.suspended_reason ? `: ${organization.suspended_reason}` : '' }}.
                Its users cannot sign in to the app, syncing is paused and incoming webhooks are ignored.
            </span>
        </p>

        <InviteLink :url="$page.props.flash.invite_url" class="mb-6" />

        <!-- Overview strip -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div :class="[card, '!p-5']">
                <p class="text-xs uppercase tracking-wide text-slate-400">Plan</p>
                <p class="mt-1 text-xl font-bold capitalize">{{ organization.plan }}</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ organization.plan_expires_at ? `until ${new Date(organization.plan_expires_at).toLocaleDateString()}` : 'no expiry' }}
                </p>
            </div>
            <div :class="[card, '!p-5']">
                <p class="text-xs uppercase tracking-wide text-slate-400">Users</p>
                <p class="mt-1 text-xl font-bold tabular-nums">
                    {{ organization.users_count }}
                    <span class="text-base font-normal text-slate-500">/ {{ organization.max_users_effective ?? '∞' }}</span>
                </p>
                <Meter :used="organization.users_count" :max="organization.max_users_effective" />
            </div>
            <div :class="[card, '!p-5']">
                <p class="text-xs uppercase tracking-wide text-slate-400">Accounts</p>
                <p class="mt-1 text-xl font-bold tabular-nums">
                    {{ organization.social_accounts_count }}
                    <span class="text-base font-normal text-slate-500">/ {{ organization.max_social_accounts_effective ?? '∞' }}</span>
                </p>
                <Meter :used="organization.social_accounts_count" :max="organization.max_social_accounts_effective" />
            </div>
            <div :class="[card, '!p-5']">
                <p class="text-xs uppercase tracking-wide text-slate-400">Own apps</p>
                <p class="mt-1 text-xl font-bold">{{ organization.allow_custom_apps ? 'Allowed' : 'Platform apps' }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ own_apps.length }} registered</p>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-3">
            <!-- Settings -->
            <form @submit.prevent="save" :class="[card, 'space-y-4 lg:sticky lg:top-24']">
                <h2 class="text-lg font-bold">Plan &amp; limits</h2>
                <Field label="Name" :error="form.errors.name"><input v-model="form.name" required :class="input" /></Field>
                <Field label="Plan" :error="form.errors.plan">
                    <select v-model="form.plan" :class="input">
                        <option v-for="p in plans" :key="p" :value="p" class="capitalize">{{ p }}</option>
                    </select>
                </Field>
                <Field label="Plan expires" :error="form.errors.plan_expires_at" hint="Empty = never">
                    <input v-model="form.plan_expires_at" type="date" :class="input" />
                </Field>
                <Field
                    label="Max users"
                    :error="form.errors.max_users"
                    :hint="`Effective: ${organization.max_users_effective ?? 'unlimited'} · empty = plan default`"
                >
                    <input v-model="form.max_users" type="number" min="1" :class="input" />
                </Field>
                <Field
                    label="Max connected accounts"
                    :error="form.errors.max_social_accounts"
                    :hint="`Effective: ${organization.max_social_accounts_effective ?? 'unlimited'} · empty = plan default`"
                >
                    <input v-model="form.max_social_accounts" type="number" min="1" :class="input" />
                </Field>
                <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-300">
                    <input type="checkbox" v-model="form.allow_custom_apps" class="mt-1 rounded border-slate-600 bg-slate-900 text-violet-500 focus:ring-violet-500" />
                    <span>May use its own Meta / Google / X / LinkedIn apps</span>
                </label>
                <button
                    :disabled="form.processing"
                    class="w-full rounded-lg bg-violet-600 px-4 py-2.5 font-semibold text-white hover:bg-violet-500 disabled:bg-slate-600"
                >
                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                </button>
            </form>

            <div class="space-y-6 lg:col-span-2">
                <!-- Users -->
                <section :class="[card, '!p-0']">
                    <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                        <h2 class="flex items-center gap-2 font-bold"><UsersIcon class="h-5 w-5 text-slate-400" /> Users ({{ users.length }})</h2>
                    </div>

                    <ul class="divide-y divide-white/5">
                        <li v-for="u in users" :key="u.id" class="flex flex-wrap items-center gap-4 px-6 py-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold">
                                {{ (u.name || '?')[0].toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ u.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ u.email }}</p>
                            </div>
                            <span class="rounded-full bg-violet-500/15 px-2.5 py-0.5 text-xs font-medium capitalize text-violet-200">
                                {{ u.role.replace('_', ' ') }}
                            </span>
                            <span class="hidden text-xs text-slate-500 sm:block">
                                {{ u.last_login_at ? `last seen ${new Date(u.last_login_at).toLocaleDateString()}` : 'never signed in' }}
                            </span>
                            <button
                                @click="impersonate(u)"
                                class="inline-flex items-center gap-1 text-sm text-violet-300 hover:text-violet-200"
                            >
                                <EyeIcon class="h-4 w-4" /> View as
                            </button>
                        </li>
                    </ul>

                    <div v-if="invitations.length" class="border-t border-white/10 px-6 py-4">
                        <p class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-slate-400">
                            <ClockIcon class="h-4 w-4" /> Pending invitations
                        </p>
                        <ul class="space-y-1.5 text-sm text-slate-300">
                            <li v-for="i in invitations" :key="i.id" class="flex flex-wrap items-center gap-2">
                                <EnvelopeIcon class="h-4 w-4 text-slate-500" />
                                {{ i.email }}
                                <span class="text-slate-500">as {{ i.role.replace('_', ' ') }} · expires {{ new Date(i.expires_at).toLocaleDateString() }}</span>
                            </li>
                        </ul>
                    </div>

                    <form @submit.prevent="invite" class="flex flex-wrap items-start gap-3 border-t border-white/10 px-6 py-4">
                        <div>
                            <input v-model="inviteForm.email" type="email" placeholder="Invite by email" required :class="input + ' min-w-[300px]'" />
                            <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-400">{{ inviteForm.errors.email }}</p>
                        </div>
                        <select v-model="inviteForm.role" :class="input + ' max-w-[300px]'">
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="team_member">Team member</option>
                            <option value="viewer">Viewer</option>
                        </select>
                        <button
                            :disabled="inviteForm.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-500 disabled:bg-slate-600"
                        >
                            <UserPlusIcon class="h-4 w-4" /> Send invitation
                        </button>
                    </form>
                </section>

                <!-- Accounts -->
                <section :class="[card, '!p-0']">
                    <div class="border-b border-white/10 px-6 py-4">
                        <h2 class="flex items-center gap-2 font-bold"><LinkIcon class="h-5 w-5 text-slate-400" /> Connected accounts ({{ accounts.length }})</h2>
                    </div>
                    <p v-if="!accounts.length" class="px-6 py-8 text-center text-sm text-slate-400">Nothing connected yet.</p>
                    <ul v-else class="divide-y divide-white/5">
                        <li v-for="a in accounts" :key="a.id" class="flex flex-wrap items-center gap-4 px-6 py-3.5">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="platformColor(a.platform)"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ a.platform_account_name }}</p>
                                <p class="text-xs capitalize text-slate-500">
                                    {{ a.platform }}<span v-if="a.auth_mode === 'instagram_login'"> · direct login</span>
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="accountStatusClass(a.status)">
                                {{ a.status }}
                            </span>
                            <span class="hidden text-xs text-slate-500 sm:block">
                                {{ a.token_expires_at ? `token until ${new Date(a.token_expires_at).toLocaleDateString()}` : '' }}
                            </span>
                        </li>
                    </ul>
                </section>

                <!-- Own apps -->
                <section v-if="own_apps.length" :class="card">
                    <h2 class="mb-3 flex items-center gap-2 font-bold"><PuzzlePieceIcon class="h-5 w-5 text-slate-400" /> Organization's own apps</h2>
                    <ul class="space-y-2 text-sm text-slate-300">
                        <li v-for="c in own_apps" :key="c.id" class="flex flex-wrap items-center gap-2">
                            <span class="capitalize">{{ c.provider }}</span>
                            <span :class="c.configured ? 'text-emerald-300' : 'text-yellow-300'">
                                {{ c.configured ? 'configured' : 'incomplete' }}
                            </span>
                            <span v-if="!c.is_active" class="text-slate-500">(inactive)</span>
                            <span v-if="!organization.allow_custom_apps" class="text-yellow-400">· not in use (not allowed)</span>
                        </li>
                    </ul>
                </section>

                <!-- Danger zone -->
                <section class="rounded-2xl border border-red-500/30 bg-red-500/5 p-6">
                    <h2 class="mb-2 flex items-center gap-2 font-bold text-red-300">
                        <ArchiveBoxIcon class="h-5 w-5" /> Archive organization
                    </h2>
                    <p class="mb-4 text-sm text-slate-400">Blocks all users, stops syncing and ignores webhooks. Data is retained.</p>
                    <button
                        @click="showArchive = true"
                        class="rounded-lg border border-red-500/50 px-4 py-2 text-sm text-red-300 transition-colors hover:bg-red-500/10"
                    >
                        Archive…
                    </button>
                </section>
            </div>
        </div>

        <Modal v-model="showSuspend" title="Suspend organization">
            <p class="mb-3 text-sm text-slate-300">Users will see this reason when they try to sign in.</p>
            <input v-model="reason" placeholder="Reason (optional), e.g. Unpaid invoice" :class="input" />
            <template #footer>
                <button @click="suspend" class="rounded-lg bg-yellow-700 px-4 py-2 text-sm text-white hover:bg-yellow-600">Suspend</button>
                <button @click="showSuspend = false" class="rounded-lg bg-slate-700 px-4 py-2 text-sm text-white">Cancel</button>
            </template>
        </Modal>

        <Modal v-model="showArchive" title="Archive organization">
            <p class="mb-3 text-sm text-slate-300">Type <code class="text-white">{{ organization.slug }}</code> to confirm.</p>
            <input v-model="confirmText" :class="input" />
            <p v-if="archiveError" class="mt-2 text-sm text-red-400">{{ archiveError }}</p>
            <template #footer>
                <button
                    @click="archive"
                    :disabled="confirmText !== organization.slug"
                    class="rounded-lg bg-red-700 px-4 py-2 text-sm text-white hover:bg-red-600 disabled:opacity-40"
                >
                    Archive
                </button>
                <button @click="showArchive = false" class="rounded-lg bg-slate-700 px-4 py-2 text-sm text-white">Cancel</button>
            </template>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import {
    ArchiveBoxIcon,
    ArrowLeftIcon,
    ClockIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    LinkIcon,
    PuzzlePieceIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'
import Modal from '@/Components/Modal.vue'
import InviteLink from '@/Components/InviteLink.vue'
import Field from '@/Components/AdminField.vue'

const props = defineProps({
    organization: Object, users: Array, accounts: Array, invitations: Array, own_apps: Array, plans: Array,
})

const card = 'rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md'
const input =
    'w-full rounded-lg border border-white/10 bg-slate-900/70 px-3 py-2.5 text-white placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500'
const base = `/admin/organizations/${props.organization.id}`

// usage bar: amber near the limit, red at it
const Meter = defineComponent({
    props: { used: { type: Number, default: 0 }, max: { type: Number, default: null } },
    setup: (p) => () => {
        if (!p.max) return h('div', { class: 'mt-2 h-1.5 rounded-full bg-white/10' })
        const pct = Math.min(100, Math.round((p.used / p.max) * 100))
        const color = pct >= 100 ? 'bg-red-500' : pct >= 80 ? 'bg-yellow-500' : 'bg-violet-500'

        return h('div', { class: 'mt-2 h-1.5 overflow-hidden rounded-full bg-white/10' }, [
            h('div', { class: `h-1.5 rounded-full ${color}`, style: { width: pct + '%' } }),
        ])
    },
})

const statusLabel = computed(() =>
    props.organization.suspended_at ? 'Suspended' : props.organization.usable ? 'Active' : 'Inactive'
)
const statusClass = computed(() =>
    props.organization.suspended_at
        ? 'bg-red-500/15 text-red-300'
        : props.organization.usable
          ? 'bg-emerald-500/15 text-emerald-300'
          : 'bg-slate-500/20 text-slate-300'
)

const accountStatusClass = (s) =>
    s === 'connected' ? 'bg-emerald-500/15 text-emerald-300' : s === 'disconnected' ? 'bg-slate-500/20 text-slate-300' : 'bg-red-500/15 text-red-300'

const platformColor = (p) =>
    ({ facebook: 'bg-blue-500', instagram: 'bg-pink-500', youtube: 'bg-red-500', twitter: 'bg-slate-300', linkedin: 'bg-sky-600' })[p] ?? 'bg-violet-500'

// ---- settings
const form = useForm({
    name: props.organization.name,
    plan: props.organization.plan,
    plan_expires_at: props.organization.plan_expires_at ? props.organization.plan_expires_at.substring(0, 10) : '',
    max_users: props.organization.max_users ?? '',
    max_social_accounts: props.organization.max_social_accounts ?? '',
    allow_custom_apps: !!props.organization.allow_custom_apps,
})
const save = () => form.patch(base, { preserveScroll: true })

// ---- invitations
const inviteForm = useForm({ email: '', role: 'admin' })
const invite = () => inviteForm.post(`${base}/invite`, { preserveScroll: true, onSuccess: () => inviteForm.reset('email') })

// ---- suspend / reactivate
const showSuspend = ref(false)
const reason = ref('')
const suspend = () => router.post(`${base}/suspend`, { reason: reason.value }, { onSuccess: () => (showSuspend.value = false) })
const activate = () => router.post(`${base}/activate`)

// ---- view as
const impersonate = (u) => {
    if (confirm(`Open the app as ${u.name}? Everything you do is recorded as this user.`)) router.post(`${base}/impersonate/${u.id}`)
}

// ---- archive
const showArchive = ref(false)
const confirmText = ref('')
const archiveError = ref('')
const archive = () => router.delete(base, { data: { confirm: confirmText.value }, onError: (e) => (archiveError.value = e.confirm) })
</script>