<template>
    <AdminLayout>
        <Head title="Platform overview" />

        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Platform overview</h1>
                <p class="mt-1 text-slate-400">Every organization on this installation.</p>
            </div>
            <Link
                href="/admin/organizations/create"
                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 font-semibold !text-white shadow-lg shadow-violet-600/30 transition-all hover:-translate-y-0.5 hover:bg-violet-500"
            >
                <PlusIcon class="h-5 w-5" />
                New organization
            </Link>
        </div>

        <!-- Things that need the owner's attention -->
        <div v-if="alerts.length" class="mb-8 space-y-3">
            <div
                v-for="a in alerts"
                :key="a.text"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-100"
            >
                <span class="flex items-center gap-2">
                    <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-yellow-400" />
                    {{ a.text }}
                </span>
                <Link v-if="a.href" :href="a.href" class="font-medium">{{ a.action }} →</Link>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div
                v-for="c in cards"
                :key="c.label"
                class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:-translate-y-0.5 hover:border-white/20 hover:bg-white/10"
            >
                <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full opacity-20 blur-2xl" :class="c.glow"></div>
                <component :is="c.icon" class="mb-3 h-6 w-6" :class="c.tone" />
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ c.label }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums" :class="c.tone">{{ c.value }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent organizations -->
            <section :class="[card, 'p-0 lg:col-span-2']">
                <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                    <h2 class="font-bold">Recent organizations</h2>
                    <Link href="/admin/organizations" class="text-sm">View all →</Link>
                </div>

                <div v-if="!recent_organizations.length" class="px-6 py-12 text-center">
                    <BuildingOffice2Icon class="mx-auto mb-3 h-10 w-10 text-slate-600" />
                    <p class="text-slate-400">No organizations yet.</p>
                    <Link href="/admin/organizations/create" class="mt-2 inline-block text-sm">Create the first one →</Link>
                </div>

                <ul v-else class="divide-y divide-white/5">
                    <li v-for="o in recent_organizations" :key="o.id">
                        <Link
                            :href="`/admin/organizations/${o.id}`"
                            class="flex items-center gap-4 px-6 py-4 !text-white transition-colors hover:bg-white/5"
                        >
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/30 to-indigo-500/30 text-sm font-bold"
                            >
                                {{ (o.name || '?')[0].toUpperCase() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ o.name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ o.slug }}</span>
                            </span>
                            <span
                                class="hidden rounded-full bg-violet-500/15 px-2.5 py-0.5 text-xs font-medium capitalize text-violet-200 sm:inline"
                            >
                                {{ o.plan }}
                            </span>
                            <span class="hidden text-sm text-slate-400 sm:block">
                                {{ o.users_count }} users · {{ o.social_accounts_count }} accounts
                            </span>
                            <ArrowRightIcon class="h-4 w-4 shrink-0 text-slate-500" />
                        </Link>
                    </li>
                </ul>
            </section>

            <div class="space-y-6">
                <!-- Platform apps -->
                <section :class="card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-bold">Platform apps</h2>
                        <span class="text-xs text-slate-400">{{ configuredCount }} of {{ providers.length }} set up</span>
                    </div>

                    <div class="mb-5 h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-1.5 rounded-full bg-gradient-to-r from-violet-500 to-emerald-400 transition-all duration-500"
                            :style="{ width: setupPercent + '%' }"
                        ></div>
                    </div>

                    <ul class="space-y-3">
                        <li v-for="p in providers" :key="p.key" class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-slate-300">
                                <CheckCircleIcon v-if="p.configured" class="h-5 w-5 text-emerald-400" />
                                <span v-else class="h-5 w-5 rounded-full border-2 border-dashed border-slate-600"></span>
                                {{ p.label }}
                            </span>
                            <Badge :variant="p.configured ? 'success' : 'info'">
                                {{ p.configured ? 'Configured' : 'Not set' }}
                            </Badge>
                        </li>
                    </ul>
                    <Link href="/admin/credentials" class="mt-5 inline-block text-sm">Manage integrations →</Link>
                </section>

                <!-- Accounts by platform -->
                <section :class="card">
                    <h2 class="mb-4 font-bold">Connected accounts</h2>
                    <p v-if="!platformRows.length" class="text-sm text-slate-400">None yet.</p>
                    <div v-else class="space-y-4">
                        <div v-for="row in platformRows" :key="row.key">
                            <div class="mb-1.5 flex justify-between text-sm">
                                <span class="capitalize text-slate-300">{{ row.key }}</span>
                                <span class="font-semibold tabular-nums">{{ row.count }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-white/10">
                                <div
                                    class="h-2 rounded-full transition-all duration-500"
                                    :class="platformColor(row.key)"
                                    :style="{ width: row.percent + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
    ArrowRightIcon,
    BuildingOffice2Icon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    LinkIcon,
    PauseCircleIcon,
    PlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'
import Badge from '@/Components/Badge.vue'

const props = defineProps({
    stats: Object,
    accounts_by_platform: Object,
    recent_organizations: Array,
    providers: Array,
})

const card = 'rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md'

const n = (v) => Number(v ?? 0).toLocaleString()

const cards = computed(() => {
    const s = props.stats ?? {}
    const warn = (value, ok) => (value > 0 ? 'text-yellow-400' : ok)

    return [
        { label: 'Organizations', value: n(s.organizations), icon: BuildingOffice2Icon, tone: 'text-white', glow: 'bg-slate-300' },
        { label: 'Active', value: n(s.active_organizations), icon: CheckCircleIcon, tone: 'text-emerald-400', glow: 'bg-emerald-400' },
        { label: 'Suspended', value: n(s.suspended_organizations), icon: PauseCircleIcon, tone: warn(s.suspended_organizations, 'text-slate-300'), glow: 'bg-yellow-400' },
        { label: 'Users', value: n(s.users), icon: UsersIcon, tone: 'text-blue-400', glow: 'bg-blue-400' },
        { label: 'Connected accounts', value: n(s.social_accounts), icon: LinkIcon, tone: 'text-violet-300', glow: 'bg-violet-400' },
        { label: 'Need attention', value: n(s.accounts_needing_attention), icon: ExclamationTriangleIcon, tone: warn(s.accounts_needing_attention, 'text-slate-300'), glow: 'bg-yellow-400' },
        { label: 'Comments (7 days)', value: n(s.comments_7d), icon: ChatBubbleLeftRightIcon, tone: 'text-sky-300', glow: 'bg-sky-400' },
    ]
})

// ---- what needs the owner's attention (derived from the same props: no backend change)
const providers = computed(() => props.providers ?? [])
const recent_organizations = computed(() => props.recent_organizations ?? [])
const configuredCount = computed(() => providers.value.filter((p) => p.configured).length)
const setupPercent = computed(() => (providers.value.length ? Math.round((configuredCount.value / providers.value.length) * 100) : 0))

const alerts = computed(() => {
    const out = []
    const s = props.stats ?? {}

    if (!providers.value.find((p) => p.key === 'meta')?.configured) {
        out.push({
            text: 'The Meta app (Facebook & Instagram) is not configured: no organization can connect those accounts yet.',
            href: '/admin/credentials',
            action: 'Set it up',
        })
    }
    if (s.accounts_needing_attention > 0) {
        out.push({
            text: `${s.accounts_needing_attention} connected account(s) have an expired or failing connection.`,
            href: '/admin/organizations',
            action: 'Review organizations',
        })
    }
    if (s.suspended_organizations > 0) {
        out.push({
            text: `${s.suspended_organizations} organization(s) are suspended.`,
            href: '/admin/organizations?status=suspended',
            action: 'View suspended',
        })
    }

    return out
})

// ---- accounts per platform
const platformRows = computed(() => {
    const entries = Object.entries(props.accounts_by_platform ?? {})
    const total = entries.reduce((sum, [, count]) => sum + count, 0)

    return entries.map(([key, count]) => ({ key, count, percent: total ? Math.round((count / total) * 100) : 0 }))
})

const platformColor = (p) =>
    ({ facebook: 'bg-blue-500', instagram: 'bg-pink-500', youtube: 'bg-red-500', twitter: 'bg-slate-300', linkedin: 'bg-sky-600' })[p] ?? 'bg-violet-500'
</script>