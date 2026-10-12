<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-2">
                <h1 class="text-3xl font-bold text-white">Team</h1>
                <p class="text-sm text-slate-400">{{ limits.used }} of {{ limits.max ?? "unlimited" }} users</p>
            </div>
            <p v-if="!canManage" class="mb-6 text-sm text-slate-400">Only organization admins can invite or remove people.</p>

            <InviteLink :url="$page.props.flash.invite_url" class="mb-6" />

            <form v-if="canManage" @submit.prevent="invite" class="mb-8 bg-slate-800 border border-slate-700 rounded-lg p-5 flex flex-wrap gap-3 items-start">
                <div class="flex-1 min-w-[16rem]">
                    <input v-model="inviteForm.email" type="email" required placeholder="colleague@company.com"
                        class="w-full px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                    <p v-if="inviteForm.errors.email" class="text-red-400 text-sm mt-1">{{ inviteForm.errors.email }}</p>
                </div>
                <select v-model="inviteForm.role" class="px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white">
                    <option v-for="r in roles" :key="r" :value="r">{{ label(r) }}</option>
                </select>
                <button :disabled="inviteForm.processing" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-600 text-white rounded-lg">
                    Send invitation
                </button>
            </form>

            <div class="bg-slate-800 border border-slate-700 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="m in teamMembers" :key="m.id" class="border-b border-slate-700/60 last:border-0">
                            <td class="px-6 py-4">
                                <p class="text-white font-medium">{{ m.name }} <span v-if="m.id === currentUserId" class="text-xs text-slate-500">(you)</span></p>
                                <p class="text-xs text-slate-500">{{ m.email }}</p>
                            </td>
                            <td class="px-3 py-4">
                                <select v-if="canManage && m.id !== currentUserId" :value="m.role" @change="changeRole(m, $event.target.value)"
                                    class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-sm">
                                    <option v-for="r in roles" :key="r" :value="r">{{ label(r) }}</option>
                                </select>
                                <span v-else class="text-slate-300">{{ label(m.role) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button v-if="canManage && m.id !== currentUserId" @click="remove(m)" class="text-red-400 hover:text-red-300 text-sm">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="invitations.length" class="mt-8">
                <h2 class="font-bold text-white mb-3">Pending invitations</h2>
                <div class="bg-slate-800 border border-slate-700 rounded-lg divide-y divide-slate-700/60">
                    <div v-for="i in invitations" :key="i.id" class="px-6 py-3 flex items-center justify-between text-sm">
                        <span class="text-slate-200">{{ i.email }} <span class="text-slate-500">as {{ label(i.role) }} · expires {{ new Date(i.expires_at).toLocaleDateString() }}</span></span>
                        <button v-if="canManage" @click="cancel(i)" class="text-slate-400 hover:text-white">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InviteLink from '@/Components/InviteLink.vue'

defineProps({ teamMembers: Array, invitations: Array, currentUserId: Number, canManage: Boolean, roles: Array, limits: Object })

const label = (r) => r.replace('_', ' ').replace(/^./, (c) => c.toUpperCase())

const inviteForm = useForm({ email: '', role: 'team_member' })
const invite = () => inviteForm.post('/settings/team/invite', { preserveScroll: true, onSuccess: () => inviteForm.reset('email') })

const changeRole = (m, role) => router.patch(`/settings/team/${m.id}/role`, { role }, { preserveScroll: true })
const remove = (m) => { if (confirm(`Remove ${m.name} from the team?`)) router.delete(`/settings/team/${m.id}`, { preserveScroll: true }) }
const cancel = (i) => router.delete(`/settings/team/invitations/${i.id}`, { preserveScroll: true })
</script>
