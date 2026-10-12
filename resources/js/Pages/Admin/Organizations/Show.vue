<template>
    <AdminLayout>
        <Link href="/admin/organizations" class="text-sm">← Organizations</Link>

        <div class="flex flex-wrap items-start justify-between gap-4 mt-2 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-white">{{ organization.name }}</h1>
                <p class="text-slate-500 text-sm mt-1">{{ organization.slug }} · created {{ new Date(organization.created_at).toLocaleDateString() }}</p>
            </div>
            <div class="flex items-center gap-3">
                <Badge :variant="organization.usable ? 'success' : 'danger'">
                    {{ organization.suspended_at ? 'Suspended' : organization.usable ? 'Active' : 'Inactive' }}
                </Badge>
                <button v-if="organization.suspended_at || !organization.is_active" @click="activate"
                    class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-sm rounded-lg">Reactivate</button>
                <button v-else @click="showSuspend = true"
                    class="px-4 py-2 bg-yellow-700 hover:bg-yellow-600 text-white text-sm rounded-lg">Suspend</button>
            </div>
        </div>

        <p v-if="organization.suspended_at" class="mb-6 p-4 bg-red-900/30 border border-red-700 rounded-lg text-red-200 text-sm">
            Suspended{{ organization.suspended_reason ? `: ${organization.suspended_reason}` : '' }}.
            Its users cannot sign in to the app, syncing is paused and incoming webhooks are ignored.
        </p>

        <InviteLink :url="$page.props.flash.invite_url" class="mb-6" />

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left: settings -->
            <form @submit.prevent="save" class="lg:col-span-1 bg-slate-800 border border-slate-700 rounded-lg p-6 space-y-4 h-fit">
                <h2 class="font-bold text-white">Plan &amp; limits</h2>
                <Field label="Name" :error="form.errors.name"><input v-model="form.name" required :class="input" /></Field>
                <Field label="Plan" :error="form.errors.plan">
                    <select v-model="form.plan" :class="input"><option v-for="p in plans" :key="p" :value="p">{{ p }}</option></select>
                </Field>
                <Field label="Plan expires" :error="form.errors.plan_expires_at" hint="Empty = never">
                    <input v-model="form.plan_expires_at" type="date" :class="input" />
                </Field>
                <Field label="Max users" :error="form.errors.max_users" :hint="`Effective: ${organization.max_users_effective ?? 'unlimited'} — empty = plan default`">
                    <input v-model="form.max_users" type="number" min="1" :class="input" />
                </Field>
                <Field label="Max connected accounts" :error="form.errors.max_social_accounts" :hint="`Effective: ${organization.max_social_accounts_effective ?? 'unlimited'} — empty = plan default`">
                    <input v-model="form.max_social_accounts" type="number" min="1" :class="input" />
                </Field>
                <label class="flex items-start gap-3 text-sm text-slate-300">
                    <input type="checkbox" v-model="form.allow_custom_apps" class="mt-1 rounded bg-slate-900 border-slate-600" />
                    <span>May use its own Meta / Google / X / LinkedIn apps</span>
                </label>
                <button :disabled="form.processing" class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-slate-600 text-white rounded-lg">Save</button>
            </form>

            <!-- Right -->
            <div class="lg:col-span-2 space-y-6">
                <section class="bg-slate-800 border border-slate-700 rounded-lg">
                    <div class="px-6 py-4 border-b border-slate-700 flex justify-between">
                        <h2 class="font-bold text-white">Users ({{ users.length }})</h2>
                    </div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="u in users" :key="u.id" class="border-b border-slate-700/60 last:border-0">
                                <td class="px-6 py-3"><div class="text-white">{{ u.name }}</div><div class="text-xs text-slate-500">{{ u.email }}</div></td>
                                <td class="px-3 py-3 text-slate-300 capitalize">{{ u.role.replace('_', ' ') }}</td>
                                <td class="px-3 py-3 text-slate-500 text-xs">{{ u.last_login_at ? new Date(u.last_login_at).toLocaleDateString() : 'never signed in' }}</td>
                                <td class="px-6 py-3 text-right">
                                    <button @click="impersonate(u)" class="text-sm text-purple-300 hover:text-purple-200">View as →</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="invitations.length" class="px-6 py-4 border-t border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-400 mb-2">Pending invitations</p>
                        <ul class="text-sm text-slate-300 space-y-1">
                            <li v-for="i in invitations" :key="i.id">{{ i.email }} <span class="text-slate-500">as {{ i.role }}, expires {{ new Date(i.expires_at).toLocaleDateString() }}</span></li>
                        </ul>
                    </div>

                    <form @submit.prevent="invite" class="px-6 py-4 border-t border-slate-700 flex flex-wrap gap-3 items-start">
                        <div>
                            <input v-model="inviteForm.email" type="email" placeholder="Invite by email" required :class="input + ' w-64'" />
                            <p v-if="inviteForm.errors.email" class="text-red-400 text-sm mt-1">{{ inviteForm.errors.email }}</p>
                        </div>
                        <select v-model="inviteForm.role" :class="input + ' w-40'">
                            <option value="admin">Admin</option><option value="manager">Manager</option>
                            <option value="team_member">Team member</option><option value="viewer">Viewer</option>
                        </select>
                        <button :disabled="inviteForm.processing" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Send invitation</button>
                    </form>
                </section>

                <section class="bg-slate-800 border border-slate-700 rounded-lg">
                    <div class="px-6 py-4 border-b border-slate-700"><h2 class="font-bold text-white">Connected accounts ({{ accounts.length }})</h2></div>
                    <p v-if="!accounts.length" class="px-6 py-6 text-slate-400 text-sm">Nothing connected yet.</p>
                    <table v-else class="w-full text-sm">
                        <tbody>
                            <tr v-for="a in accounts" :key="a.id" class="border-b border-slate-700/60 last:border-0">
                                <td class="px-6 py-3 text-white">{{ a.platform_account_name }}</td>
                                <td class="px-3 py-3 text-slate-300 capitalize">{{ a.platform }}
                                    <span v-if="a.auth_mode === 'instagram_login'" class="text-xs text-slate-500">(direct)</span></td>
                                <td class="px-3 py-3"><Badge :variant="a.status === 'connected' ? 'success' : a.status === 'disconnected' ? 'info' : 'danger'">{{ a.status }}</Badge></td>
                                <td class="px-6 py-3 text-xs text-slate-500">{{ a.token_expires_at ? `token until ${new Date(a.token_expires_at).toLocaleDateString()}` : '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section v-if="own_apps.length" class="bg-slate-800 border border-slate-700 rounded-lg p-6">
                    <h2 class="font-bold text-white mb-3">Organization's own apps</h2>
                    <ul class="text-sm text-slate-300 space-y-1">
                        <li v-for="c in own_apps" :key="c.id">{{ c.provider }} — {{ c.configured ? 'configured' : 'incomplete' }}{{ c.is_active ? '' : ' (inactive)' }}
                            <span v-if="!organization.allow_custom_apps" class="text-yellow-400">· not in use (not allowed)</span></li>
                    </ul>
                </section>

                <section class="bg-slate-800 border border-red-900/60 rounded-lg p-6">
                    <h2 class="font-bold text-red-300 mb-2">Archive organization</h2>
                    <p class="text-sm text-slate-400 mb-4">Blocks all users, stops syncing and ignores webhooks. Data is retained.</p>
                    <button @click="showArchive = true" class="px-4 py-2 border border-red-700 text-red-300 hover:bg-red-900/30 rounded-lg text-sm">Archive…</button>
                </section>
            </div>
        </div>

        <Modal v-model="showSuspend" title="Suspend organization">
            <p class="text-sm text-slate-300 mb-3">Users will see this reason when they try to sign in.</p>
            <input v-model="reason" placeholder="Reason (optional), e.g. Unpaid invoice" :class="input" />
            <template #footer>
                <button @click="suspend" class="px-4 py-2 bg-yellow-700 hover:bg-yellow-600 text-white rounded-lg text-sm">Suspend</button>
                <button @click="showSuspend = false" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            </template>
        </Modal>

        <Modal v-model="showArchive" title="Archive organization">
            <p class="text-sm text-slate-300 mb-3">Type <code class="text-white">{{ organization.slug }}</code> to confirm.</p>
            <input v-model="confirmText" :class="input" />
            <p v-if="archiveError" class="text-red-400 text-sm mt-2">{{ archiveError }}</p>
            <template #footer>
                <button @click="archive" :disabled="confirmText !== organization.slug" class="px-4 py-2 bg-red-700 hover:bg-red-600 disabled:opacity-40 text-white rounded-lg text-sm">Archive</button>
                <button @click="showArchive = false" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            </template>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'
import Badge from '@/Components/Badge.vue'
import Modal from '@/Components/Modal.vue'
import InviteLink from '@/Components/InviteLink.vue'
import Field from '@/Components/AdminField.vue'

const props = defineProps({
    organization: Object, users: Array, accounts: Array, invitations: Array, own_apps: Array, plans: Array,
})

const input = 'w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-purple-500'
const base = `/admin/organizations/${props.organization.id}`

const form = useForm({
    name: props.organization.name,
    plan: props.organization.plan,
    plan_expires_at: props.organization.plan_expires_at ? props.organization.plan_expires_at.substring(0, 10) : '',
    max_users: props.organization.max_users ?? '',
    max_social_accounts: props.organization.max_social_accounts ?? '',
    allow_custom_apps: !!props.organization.allow_custom_apps,
})
const save = () => form.patch(base, { preserveScroll: true })

const inviteForm = useForm({ email: '', role: 'admin' })
const invite = () => inviteForm.post(`${base}/invite`, { preserveScroll: true, onSuccess: () => inviteForm.reset('email') })

const showSuspend = ref(false)
const reason = ref('')
const suspend = () => router.post(`${base}/suspend`, { reason: reason.value }, { onSuccess: () => (showSuspend.value = false) })
const activate = () => router.post(`${base}/activate`)

const impersonate = (u) => {
    if (confirm(`Open the app as ${u.name}? Everything you do is recorded as this user.`)) router.post(`${base}/impersonate/${u.id}`)
}

const showArchive = ref(false)
const confirmText = ref('')
const archiveError = ref('')
const archive = () => router.delete(base, { data: { confirm: confirmText.value }, onError: (e) => (archiveError.value = e.confirm) })
</script>
