<template>
    <AdminLayout>
        <Head title="Organizations" />

        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Organizations</h1>
                <p class="mt-1 text-slate-400">{{ organizations.total ?? organizations.data.length }} in total</p>
            </div>
            <Link
                href="/admin/organizations/create"
                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 font-semibold !text-white shadow-lg shadow-violet-600/30 transition-all hover:-translate-y-0.5 hover:bg-violet-500"
            >
                <PlusIcon class="h-5 w-5" />
                New organization
            </Link>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-80">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                <input
                    v-model="search"
                    @keydown.enter="apply"
                    placeholder="Search name or slug…"
                    class="w-full rounded-lg border border-white/10 bg-slate-900/70 py-2.5 pl-10 pr-4 text-white placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                />
            </div>

            <!-- Status tabs -->
            <div class="inline-flex rounded-lg border border-white/10 bg-white/5 p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    @click="setStatus(tab.value)"
                    :class="[
                        'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                        status === tab.value ? 'bg-violet-600 text-white shadow' : 'text-slate-300 hover:text-white',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-md">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-white/10 text-left text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-6 py-3 font-medium">Organization</th>
                            <th class="px-3 py-3 font-medium">Plan</th>
                            <th class="px-3 py-3 font-medium">Users</th>
                            <th class="px-3 py-3 font-medium">Accounts</th>
                            <th class="px-3 py-3 font-medium">Own apps</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!organizations.data.length">
                            <td colspan="6" class="px-6 py-16 text-center">
                                <BuildingOffice2Icon class="mx-auto mb-3 h-10 w-10 text-slate-600" />
                                <p class="text-slate-400">No organizations found.</p>
                                <Link v-if="!filters.search && !filters.status" href="/admin/organizations/create" class="mt-2 inline-block text-sm">
                                    Create the first one →
                                </Link>
                            </td>
                        </tr>

                        <tr
                            v-for="o in organizations.data"
                            :key="o.id"
                            @click="router.visit(`/admin/organizations/${o.id}`)"
                            class="cursor-pointer border-b border-white/5 transition-colors last:border-0 hover:bg-white/5"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/30 to-indigo-500/30 text-sm font-bold"
                                    >
                                        {{ (o.name || '?')[0].toUpperCase() }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ o.name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ o.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <span class="rounded-full bg-violet-500/15 px-2.5 py-0.5 text-xs font-medium capitalize text-violet-200">
                                    {{ o.plan }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <Usage :used="o.users_count" :max="o.max_users_effective" />
                            </td>
                            <td class="px-3 py-4">
                                <Usage :used="o.social_accounts_count" :max="o.max_social_accounts_effective" />
                            </td>
                            <td class="px-3 py-4">
                                <span v-if="o.allow_custom_apps" class="inline-flex items-center gap-1 text-xs text-emerald-300">
                                    <CheckIcon class="h-4 w-4" /> Allowed
                                </span>
                                <span v-else class="text-slate-600">—</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(o)">
                                    {{ statusLabel(o) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="organizations.last_page > 1" class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-400">
                Showing {{ organizations.from }}–{{ organizations.to }} of {{ organizations.total }}
            </p>
            <nav class="flex flex-wrap gap-2">
                <component
                    v-for="(l, i) in organizations.links"
                    :key="i"
                    :is="l.url ? Link : 'span'"
                    :href="l.url"
                    v-html="l.label"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-sm',
                        l.active ? 'bg-violet-600 !text-white' : 'border border-white/10 bg-white/5 !text-slate-300',
                        !l.url && 'opacity-40',
                    ]"
                />
            </nav>
        </div>
    </AdminLayout>
</template>

<script setup>
import { defineComponent, h, onBeforeUnmount, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { BuildingOffice2Icon, CheckIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'

const props = defineProps({ organizations: Object, filters: Object, plans: Array })

// "3 / 5" with a small bar; turns amber near the limit and red at it
const Usage = defineComponent({
    props: { used: { type: Number, default: 0 }, max: { type: Number, default: null } },
    setup(p) {
        return () => {
            const pct = p.max ? Math.min(100, Math.round((p.used / p.max) * 100)) : 0
            const color = !p.max ? 'bg-slate-500' : pct >= 100 ? 'bg-red-500' : pct >= 80 ? 'bg-yellow-500' : 'bg-violet-500'

            return h('div', { class: 'w-28' }, [
                h('p', { class: 'text-slate-200 tabular-nums' }, [
                    String(p.used),
                    h('span', { class: 'text-slate-500' }, ` / ${p.max ?? '∞'}`),
                ]),
                p.max ? h('div', { class: 'mt-1 h-1 overflow-hidden rounded-full bg-white/10' }, [
                    h('div', { class: `h-1 rounded-full ${color}`, style: { width: pct + '%' } }),
                ]) : null,
            ])
        }
    },
})

const tabs = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
]

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')

const apply = () =>
    router.get('/admin/organizations', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true })

const setStatus = (value) => {
    status.value = value
    apply()
}

// search as you type (waits until you stop typing)
let timer = null
watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(apply, 350)
})
onBeforeUnmount(() => clearTimeout(timer))

const statusLabel = (o) => (o.suspended_at ? 'Suspended' : o.usable ? 'Active' : 'Inactive')
const statusClass = (o) =>
    o.suspended_at
        ? 'bg-red-500/15 text-red-300'
        : o.usable
          ? 'bg-emerald-500/15 text-emerald-300'
          : 'bg-slate-500/20 text-slate-300'
</script>