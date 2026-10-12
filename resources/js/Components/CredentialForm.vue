<template>
    <div class="bg-slate-800 rounded-lg border border-slate-700 p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h3 class="text-lg font-bold text-white">{{ provider.label }}</h3>
                <p v-if="provider.description" class="text-sm text-slate-400 mt-1 max-w-3xl">{{ provider.description }}</p>
            </div>
            <Badge :variant="status.variant">{{ status.label }}</Badge>
        </div>

        <!-- Values the owner must paste into the provider's dashboard -->
        <div v-if="registerUrls.length" class="mb-5 p-4 bg-slate-900/60 border border-slate-700 rounded-lg space-y-3">
            <p class="text-xs uppercase tracking-wide text-slate-400">Register these URLs in the provider dashboard</p>
            <div v-for="u in registerUrls" :key="u.label" class="flex items-center gap-2">
                <span class="w-48 shrink-0 text-xs text-slate-400">{{ u.label }}</span>
                <input :value="u.url" readonly @focus="$event.target.select()"
                    class="flex-1 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs font-mono text-slate-200" />
                <button type="button" @click="copy(u.url)" class="text-xs text-blue-400 hover:text-blue-300">Copy</button>
            </div>
            <p v-if="provider.has_webhook && !provider.credential" class="text-xs text-slate-500">
                The webhook URL appears here after the first save.
            </p>
        </div>

        <form @submit.prevent="save" class="space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div v-for="field in provider.fields" :key="field.key" :class="field.key.endsWith('scopes') ? 'md:col-span-2' : ''">
                    <label :for="`${provider.key}-${field.key}`" class="block text-sm font-medium text-slate-300 mb-1">
                        {{ field.label }}
                        <span v-if="field.secret" class="ml-1 text-xs text-slate-500">🔒 stored encrypted</span>
                    </label>
                    <input :id="`${provider.key}-${field.key}`"
                        v-model="form.fields[field.key]"
                        :type="field.secret ? 'password' : 'text'"
                        autocomplete="off"
                        :placeholder="placeholder(field)"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-blue-500" />
                    <p v-if="field.help" class="text-xs text-slate-500 mt-1">{{ field.help }}</p>
                    <p v-if="form.errors[`fields.${field.key}`]" class="text-red-400 text-sm mt-1">{{ form.errors[`fields.${field.key}`] }}</p>
                </div>
            </div>

            <p v-if="form.errors.fields" class="text-red-400 text-sm">{{ form.errors.fields }}</p>

            <label v-if="provider.credential" class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" v-model="form.is_active" class="rounded bg-slate-900 border-slate-600" />
                Active (uncheck to stop using these credentials without deleting them)
            </label>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="submit" :disabled="form.processing"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-600 text-white text-sm font-medium rounded-lg">
                    {{ form.processing ? 'Saving…' : provider.credential ? 'Save changes' : 'Save credentials' }}
                </button>
                <button v-if="provider.credential" type="button" @click="test" :disabled="testing"
                    class="px-4 py-2 bg-slate-700 hover:bg-slate-600 disabled:opacity-60 text-white text-sm rounded-lg">
                    {{ testing ? 'Testing…' : 'Test connection' }}
                </button>
                <button v-if="provider.credential" type="button" @click="remove"
                    class="px-4 py-2 text-red-400 hover:text-red-300 text-sm ml-auto">
                    Remove
                </button>
            </div>
            <p v-if="provider.credential?.last_verified_at" class="text-xs text-slate-500">
                Last verified {{ new Date(provider.credential.last_verified_at).toLocaleString() }}
            </p>
            <p v-if="provider.credential?.last_webhook_at" class="text-xs text-slate-500">
                Last webhook received {{ new Date(provider.credential.last_webhook_at).toLocaleString() }}
            </p>
        </form>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Badge from '@/Components/Badge.vue'
import { toast } from '@/composables/useToast'

const props = defineProps({
    provider: { type: Object, required: true },
    // route helpers: { update: '/x/meta', test: '/x/meta/test', destroy: '/x/meta' }
    urls: { type: Object, required: true },
})

const initial = () => {
    const out = {}
    for (const f of props.provider.fields) {
        const stored = props.provider.credential?.fields?.[f.key]
        out[f.key] = f.secret ? '' : (stored?.value ?? '')
    }
    return out
}

const form = useForm({
    label: props.provider.credential?.label ?? null,
    is_active: props.provider.credential?.is_active ?? true,
    fields: initial(),
})

const testing = ref(false)

const placeholder = (field) => {
    if (field.secret && props.provider.credential?.fields?.[field.key]?.set) return '•••••••• saved — leave blank to keep'
    return field.default ? `default: ${field.default}` : ''
}

const status = computed(() => {
    const c = props.provider.credential
    if (!c) return { label: 'Not configured', variant: 'info' }
    if (!c.is_active) return { label: 'Inactive', variant: 'warning' }
    return c.configured ? { label: 'Configured', variant: 'success' } : { label: 'Incomplete', variant: 'warning' }
})

const registerUrls = computed(() => {
    const out = []
    const labels = { facebook: 'Facebook Login redirect URI', instagram: 'Instagram Login redirect URI' }
    const uris = props.provider.credential?.redirect_uris ?? props.provider.callback_urls ?? {}
    for (const [platform, url] of Object.entries(uris)) {
        out.push({ label: labels[platform] ?? 'OAuth redirect URI', url })
    }
    if (props.provider.credential?.webhook_url) {
        out.push({ label: 'Webhook callback URL', url: props.provider.credential.webhook_url })
    }
    return out
})

const save = () => form.put(props.urls.update, {
    preserveScroll: true,
    onSuccess: () => {
        for (const f of props.provider.fields) if (f.secret) form.fields[f.key] = ''
    },
})

const test = () => {
    testing.value = true
    router.post(props.urls.test, {}, { preserveScroll: true, onFinish: () => (testing.value = false) })
}

const remove = () => {
    if (!confirm(`Remove the ${props.provider.label} credentials? Accounts connected through them keep working until their tokens expire.`)) return
    router.delete(props.urls.destroy, { preserveScroll: true })
}

const copy = async (text) => {
    try { await navigator.clipboard.writeText(text); toast.success('Copied') } catch (e) { /* select-and-copy still works */ }
}
</script>
