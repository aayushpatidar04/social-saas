<template>
    <AdminLayout>
        <Head title="New organization" />

        <Link href="/admin/organizations" class="inline-flex items-center gap-1 text-sm">
            <ArrowLeftIcon class="h-4 w-4" /> Organizations
        </Link>
        <h1 class="mb-8 mt-2 text-3xl font-bold tracking-tight md:text-4xl">New organization</h1>

        <form @submit.prevent="form.post('/admin/organizations')" class="grid items-start gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- 1. Organization -->
                <section :class="card">
                    <Step n="1" title="Organization" />
                    <div class="space-y-4">
                        <Field label="Name" :error="form.errors.name">
                            <input v-model="form.name" required autofocus :class="input" placeholder="Acme Retail" />
                        </Field>
                        <Field label="Slug (optional)" :error="form.errors.slug" hint="Generated from the name when empty.">
                            <input v-model="form.slug" :class="input" placeholder="acme-retail" />
                        </Field>
                    </div>
                </section>

                <!-- 2. Plan -->
                <section :class="card">
                    <Step n="2" title="Plan & limits" />

                    <div class="mb-5 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="p in plans"
                            :key="p"
                            :class="[
                                'cursor-pointer rounded-xl border p-4 transition-all',
                                form.plan === p
                                    ? 'border-violet-500 bg-violet-500/10 ring-1 ring-violet-500'
                                    : 'border-white/10 bg-white/[0.03] hover:border-white/20',
                            ]"
                        >
                            <input type="radio" v-model="form.plan" :value="p" class="sr-only" />
                            <div class="flex items-center justify-between">
                                <span class="font-semibold capitalize">{{ p }}</span>
                                <CheckCircleIcon v-if="form.plan === p" class="h-5 w-5 text-violet-300" />
                            </div>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ describe(plan_defaults[p]?.max_users) }} users ·
                                {{ describe(plan_defaults[p]?.max_social_accounts) }} accounts
                            </p>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <Field label="Max users" :error="form.errors.max_users" :hint="`Plan default: ${describe(defaults.max_users)}`">
                            <input v-model="form.max_users" type="number" min="1" :class="input" :placeholder="String(describe(defaults.max_users))" />
                        </Field>
                        <Field label="Max connected accounts" :error="form.errors.max_social_accounts" :hint="`Plan default: ${describe(defaults.max_social_accounts)}`">
                            <input v-model="form.max_social_accounts" type="number" min="1" :class="input" :placeholder="String(describe(defaults.max_social_accounts))" />
                        </Field>
                        <Field label="Plan expires (optional)" :error="form.errors.plan_expires_at" hint="Access is blocked after this date.">
                            <input v-model="form.plan_expires_at" type="date" :class="input" />
                        </Field>
                    </div>

                    <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-slate-300">
                        <input type="checkbox" v-model="form.allow_custom_apps" class="mt-1 rounded border-slate-600 bg-slate-900 text-violet-500 focus:ring-violet-500" />
                        <span>
                            Allow this organization to use <strong class="text-white">its own</strong> Meta / Google / X / LinkedIn apps
                            <span class="mt-0.5 block text-slate-500">
                                Otherwise it connects through the platform apps configured under Integrations.
                            </span>
                        </span>
                    </label>
                </section>

                <!-- 3. Administrator -->
                <section :class="card">
                    <Step n="3" title="First administrator" />
                    <p class="mb-4 text-sm text-slate-400">
                        They receive an email invitation to set their own password. You also get the link to share manually.
                    </p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field label="Name" :error="form.errors.admin_name">
                            <input v-model="form.admin_name" required :class="input" placeholder="Full name" />
                        </Field>
                        <Field label="Email" :error="form.errors.admin_email">
                            <input v-model="form.admin_email" type="email" required :class="input" placeholder="admin@company.com" />
                        </Field>
                    </div>
                </section>
            </div>

            <!-- Live summary -->
            <aside class="lg:sticky lg:top-24">
                <div :class="card">
                    <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Summary</h2>

                    <div class="mb-5 flex items-center gap-3">
                        <span
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/40 to-indigo-500/40 text-lg font-bold"
                        >
                            {{ (form.name || '?')[0].toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ form.name || 'Organization name' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ slug || 'slug' }}</p>
                        </div>
                    </div>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Plan</dt>
                            <dd class="font-medium capitalize">{{ form.plan }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Users</dt>
                            <dd class="font-medium">{{ effective(form.max_users, defaults.max_users) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Accounts</dt>
                            <dd class="font-medium">{{ effective(form.max_social_accounts, defaults.max_social_accounts) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Own apps</dt>
                            <dd class="font-medium">{{ form.allow_custom_apps ? 'Allowed' : 'Platform apps' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-400">Expires</dt>
                            <dd class="font-medium">{{ form.plan_expires_at || 'Never' }}</dd>
                        </div>
                        <div class="border-t border-white/10 pt-3">
                            <dt class="mb-1 text-slate-400">Invitation goes to</dt>
                            <dd class="truncate font-medium">{{ form.admin_email || '—' }}</dd>
                        </div>
                    </dl>

                    <button
                        :disabled="form.processing"
                        class="mt-6 w-full rounded-xl bg-violet-600 px-4 py-3 font-semibold text-white shadow-lg shadow-violet-600/30 transition-all hover:bg-violet-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
                    >
                        {{ form.processing ? 'Creating…' : 'Create organization' }}
                    </button>
                </div>
            </aside>
        </form>
    </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
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
const effective = (override, fallback) => (override !== '' && override !== null ? override : describe(fallback))

// preview only: the server generates the real, unique slug
const slug = computed(
    () => form.slug || form.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
)

// numbered section heading
const Step = defineComponent({
    props: { n: String, title: String },
    setup: (p) => () =>
        h('div', { class: 'mb-5 flex items-center gap-3' }, [
            h('span', { class: 'flex h-7 w-7 items-center justify-center rounded-full bg-violet-600 text-sm font-bold' }, p.n),
            h('h2', { class: 'text-lg font-bold' }, p.title),
        ]),
})

const card = 'rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md'
const input =
    'w-full rounded-lg border border-white/10 bg-slate-900/70 px-3 py-2.5 text-white placeholder-slate-500 transition focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500'
</script>