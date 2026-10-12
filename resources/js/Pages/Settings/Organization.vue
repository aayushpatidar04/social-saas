<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-bold text-white mb-8">Organization</h1>

            <div class="grid sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                    <p class="text-sm text-slate-400">Plan</p>
                    <p class="text-xl font-bold text-white capitalize">{{ organization.plan }}</p>
                    <p v-if="organization.plan_expires_at" class="text-xs text-slate-500 mt-1">until {{ new Date(organization.plan_expires_at).toLocaleDateString() }}</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                    <p class="text-sm text-slate-400">Users</p>
                    <p class="text-xl font-bold text-white">{{ usage.users.used }} <span class="text-slate-500 text-base">/ {{ usage.users.max ?? '∞' }}</span></p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                    <p class="text-sm text-slate-400">Connected accounts</p>
                    <p class="text-xl font-bold text-white">{{ usage.social_accounts.used }} <span class="text-slate-500 text-base">/ {{ usage.social_accounts.max ?? '∞' }}</span></p>
                </div>
            </div>
            <p class="text-sm text-slate-500 -mt-4 mb-8">Plan and limits are managed by the platform owner.</p>

            <form @submit.prevent="form.patch('/settings/organization', { preserveScroll: true })" class="bg-slate-800 border border-slate-700 rounded-lg p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Name</label>
                    <input v-model="form.name" required :disabled="!canEdit" :class="input" />
                    <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Website</label>
                    <input v-model="form.website" type="url" placeholder="https://" :disabled="!canEdit" :class="input" />
                    <p v-if="form.errors.website" class="text-red-400 text-sm mt-1">{{ form.errors.website }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                    <textarea v-model="form.description" rows="3" :disabled="!canEdit" :class="input" />
                </div>
                <button v-if="canEdit" :disabled="form.processing" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-600 text-white rounded-lg">Save</button>
                <p v-else class="text-sm text-slate-400">Only organization admins can edit these details.</p>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ organization: Object, usage: Object, canEdit: Boolean })

const form = useForm({
    name: props.organization.name ?? '',
    website: props.organization.website ?? '',
    description: props.organization.description ?? '',
})

const input = 'w-full px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 disabled:opacity-60'
</script>
