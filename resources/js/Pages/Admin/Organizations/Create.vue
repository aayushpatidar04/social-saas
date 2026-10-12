<template>
    <AdminLayout>
        <Link href="/admin/organizations" class="text-sm">← Organizations</Link>
        <h1 class="text-3xl font-bold text-white mt-2 mb-8">New organization</h1>

        <form @submit.prevent="form.post('/admin/organizations')" class="max-w-3xl space-y-8">
            <section class="bg-slate-800 border border-slate-700 rounded-lg p-6 space-y-4">
                <h2 class="font-bold text-white">Organization</h2>
                <Field label="Name" :error="form.errors.name"><input v-model="form.name" required :class="input" /></Field>
                <Field label="Slug (optional)" :error="form.errors.slug" hint="Generated from the name when empty.">
                    <input v-model="form.slug" :class="input" placeholder="acme-retail" />
                </Field>
            </section>

            <section class="bg-slate-800 border border-slate-700 rounded-lg p-6 space-y-4">
                <h2 class="font-bold text-white">Plan &amp; limits</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <Field label="Plan" :error="form.errors.plan">
                        <select v-model="form.plan" :class="input">
                            <option v-for="p in plans" :key="p" :value="p" class="capitalize">{{ p }}</option>
                        </select>
                    </Field>
                    <Field label="Plan expires (optional)" :error="form.errors.plan_expires_at" hint="Access is blocked after this date.">
                        <input v-model="form.plan_expires_at" type="date" :class="input" />
                    </Field>
                    <Field label="Max users" :error="form.errors.max_users" :hint="`Plan default: ${describe(defaults.max_users)}`">
                        <input v-model="form.max_users" type="number" min="1" :class="input" :placeholder="describe(defaults.max_users)" />
                    </Field>
                    <Field label="Max connected accounts" :error="form.errors.max_social_accounts" :hint="`Plan default: ${describe(defaults.max_social_accounts)}`">
                        <input v-model="form.max_social_accounts" type="number" min="1" :class="input" :placeholder="describe(defaults.max_social_accounts)" />
                    </Field>
                </div>
                <label class="flex items-start gap-3 text-sm text-slate-300">
                    <input type="checkbox" v-model="form.allow_custom_apps" class="mt-1 rounded bg-slate-900 border-slate-600" />
                    <span>
                        Allow this organization to use <strong>its own</strong> Meta / Google / X / LinkedIn apps
                        <span class="block text-slate-500">Otherwise it connects through the platform apps configured under Integrations.</span>
                    </span>
                </label>
            </section>

            <section class="bg-slate-800 border border-slate-700 rounded-lg p-6 space-y-4">
                <h2 class="font-bold text-white">First administrator</h2>
                <p class="text-sm text-slate-400">They receive an email invitation to set their own password. You also get the link to share manually.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <Field label="Name" :error="form.errors.admin_name"><input v-model="form.admin_name" required :class="input" /></Field>
                    <Field label="Email" :error="form.errors.admin_email"><input v-model="form.admin_email" type="email" required :class="input" /></Field>
                </div>
            </section>

            <button :disabled="form.processing" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:bg-slate-600 text-white font-medium rounded-lg">
                {{ form.processing ? 'Creating…' : 'Create organization' }}
            </button>
        </form>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Link from '@/Components/Link.vue'
import Field from '@/Components/AdminField.vue'

const props = defineProps({ plans: Array, plan_defaults: Object })

const form = useForm({
    name: '', slug: '', plan: 'starter', plan_expires_at: '', max_users: '', max_social_accounts: '',
    allow_custom_apps: false, admin_name: '', admin_email: '',
})

const defaults = computed(() => props.plan_defaults[form.plan] ?? {})
const describe = (v) => (v === null || v === undefined ? 'unlimited' : v)

const input = 'w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-purple-500'
</script>
