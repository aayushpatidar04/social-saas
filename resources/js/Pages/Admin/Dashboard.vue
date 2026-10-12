<template>
    <AdminLayout>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white mb-1">Platform overview</h1>
                <p class="text-slate-400">Every organization on this installation.</p>
            </div>
            <Link href="/admin/organizations/create"
                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 !text-white rounded-lg font-medium">
                + New organization
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div v-for="card in cards" :key="card.label" class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <p class="text-sm text-slate-400">{{ card.label }}</p>
                <p class="text-3xl font-bold mt-1" :class="card.warn && card.value > 0 ? 'text-yellow-400' : 'text-white'">{{ card.value }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-lg">
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center">
                    <h2 class="font-bold text-white">Recent organizations</h2>
                    <Link href="/admin/organizations" class="text-sm">View all</Link>
                </div>
                <div v-if="!recent_organizations.length" class="p-6 text-slate-400">No organizations yet.</div>
                <table v-else class="w-full text-sm">
                    <tbody>
                        <tr v-for="o in recent_organizations" :key="o.id" class="border-b border-slate-700/60 last:border-0">
                            <td class="px-6 py-3">
                                <Link :href="`/admin/organizations/${o.id}`">{{ o.name }}</Link>
                                <span class="text-slate-500 ml-2">{{ o.slug }}</span>
                            </td>
                            <td class="px-3 py-3 text-slate-300 capitalize">{{ o.plan }}</td>
                            <td class="px-3 py-3 text-slate-400">{{ o.users_count }} users</td>
                            <td class="px-6 py-3 text-slate-400">{{ o.social_accounts_count }} accounts</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-6">
                    <h2 class="font-bold text-white mb-4">Platform apps</h2>
                    <ul class="space-y-3">
                        <li v-for="p in providers" :key="p.key" class="flex items-center justify-between text-sm">
                            <span class="text-slate-300">{{ p.label }}</span>
                            <Badge :variant="p.configured ? 'success' : 'info'">{{ p.configured ? 'Configured' : 'Not set' }}</Badge>
                        </li>
                    </ul>
                    <Link href="/admin/credentials" class="inline-block mt-4 text-sm">Manage integrations →</Link>
                </div>

                <div class="bg-slate-800 border border-slate-700 rounded-lg p-6">
                    <h2 class="font-bold text-white mb-4">Connected accounts</h2>
                    <p v-if="!Object.keys(accounts_by_platform).length" class="text-slate-400 text-sm">None yet.</p>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="(count, platform) in accounts_by_platform" :key="platform" class="flex justify-between">
                            <span class="text-slate-300 capitalize">{{ platform }}</span>
                            <span class="text-white font-medium">{{ count }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'
import Badge from '@/Components/Badge.vue'

const props = defineProps({
    stats: Object,
    accounts_by_platform: Object,
    recent_organizations: Array,
    providers: Array,
})

const cards = computed(() => [
    { label: 'Organizations', value: props.stats.organizations },
    { label: 'Active', value: props.stats.active_organizations },
    { label: 'Suspended', value: props.stats.suspended_organizations, warn: true },
    { label: 'Users', value: props.stats.users },
    { label: 'Connected accounts', value: props.stats.social_accounts },
    { label: 'Need attention', value: props.stats.accounts_needing_attention, warn: true },
    { label: 'Comments (7 days)', value: props.stats.comments_7d },
])
</script>
