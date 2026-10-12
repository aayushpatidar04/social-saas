<template>
    <AdminLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Organizations</h1>
            <Link href="/admin/organizations/create" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 !text-white rounded-lg font-medium">
                + New organization
            </Link>
        </div>

        <form @submit.prevent="apply" class="flex flex-wrap gap-3 mb-6">
            <input v-model="search" placeholder="Search name or slug…"
                class="px-4 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500 w-72 focus:outline-none focus:border-purple-500" />
            <select v-model="status" @change="apply"
                class="px-4 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
            <button class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-white">Search</button>
        </form>

        <div class="bg-slate-800 border border-slate-700 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b border-slate-700">
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
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400">No organizations found.</td>
                    </tr>
                    <tr v-for="o in organizations.data" :key="o.id" class="border-b border-slate-700/60 last:border-0 hover:bg-slate-700/30">
                        <td class="px-6 py-3">
                            <Link :href="`/admin/organizations/${o.id}`" class="font-medium">{{ o.name }}</Link>
                            <div class="text-xs text-slate-500">{{ o.slug }}</div>
                        </td>
                        <td class="px-3 py-3 text-slate-300 capitalize">{{ o.plan }}</td>
                        <td class="px-3 py-3 text-slate-300">{{ o.users_count }}<span class="text-slate-500"> / {{ o.max_users_effective ?? '∞' }}</span></td>
                        <td class="px-3 py-3 text-slate-300">{{ o.social_accounts_count }}<span class="text-slate-500"> / {{ o.max_social_accounts_effective ?? '∞' }}</span></td>
                        <td class="px-3 py-3 text-slate-300">{{ o.allow_custom_apps ? 'Allowed' : '—' }}</td>
                        <td class="px-6 py-3">
                            <Badge :variant="o.usable ? 'success' : 'danger'">{{ o.suspended_at ? 'Suspended' : o.usable ? 'Active' : 'Inactive' }}</Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="organizations.last_page > 1" class="flex flex-wrap gap-2 mt-6">
            <component v-for="(l, i) in organizations.links" :key="i" :is="l.url ? Link : 'span'" :href="l.url"
                v-html="l.label"
                :class="['px-3 py-1.5 rounded text-sm', l.active ? 'bg-purple-600 !text-white' : 'bg-slate-800 border border-slate-700', !l.url && 'opacity-40']" />
        </nav>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'
import Badge from '@/Components/Badge.vue'

const props = defineProps({ organizations: Object, filters: Object, plans: Array })

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')

const apply = () => router.get('/admin/organizations', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true })
</script>
