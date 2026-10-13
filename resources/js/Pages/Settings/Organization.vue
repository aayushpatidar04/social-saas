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
                <div class="border-t border-slate-700 pt-5">
                    <h2 class="text-xl font-semibold text-white">AI prompts</h2>
                    <p class="text-sm text-slate-400 mt-1">
                        Customize how AI analyzes comments and writes replies for this organization.
                        Use the listed placeholders to insert comment and conversation data.
                    </p>
                </div>
                <div v-for="prompt in promptFields" :key="prompt.key">
                    <label class="block text-sm font-medium text-slate-300 mb-2">{{ prompt.label }}</label>
                    <p class="text-xs text-slate-500 mb-2">Available placeholders: {{ prompt.placeholders.join(', ') }}</p>
                    <textarea
                        v-model="form.ai_prompts[prompt.key]"
                        rows="7"
                        maxlength="10000"
                        :disabled="!canEdit"
                        :class="input"
                    />
                    <p v-if="form.errors[`ai_prompts.${prompt.key}`]" class="text-red-400 text-sm mt-1">
                        {{ form.errors[`ai_prompts.${prompt.key}`] }}
                    </p>
                </div>
                <button v-if="canEdit" :disabled="form.processing" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-600 text-white rounded-lg">Save</button>
                <p v-else class="text-sm text-slate-400">Only organization admins can edit these details and prompts.</p>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ organization: Object, usage: Object, aiPrompts: Object, canEdit: Boolean })

const form = useForm({
    name: props.organization.name ?? '',
    website: props.organization.website ?? '',
    description: props.organization.description ?? '',
    ai_prompts: { ...props.aiPrompts },
})

const promptFields = [
    { key: 'analysis', label: 'Comment analysis', placeholders: ['{comment}'] },
    { key: 'response', label: 'AI reply', placeholders: ['{comment}', '{author_name}', '{knowledge}', '{conversation_history}'] },
    { key: 'intent', label: 'Intent classification', placeholders: ['{comment}'] },
    { key: 'sentiment', label: 'Sentiment analysis', placeholders: ['{comment}'] },
    { key: 'lead_detection', label: 'Lead detection', placeholders: ['{comment}'] },
]

const input = 'w-full px-4 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 disabled:opacity-60'
</script>
