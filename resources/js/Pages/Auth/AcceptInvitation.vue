<template>
    <AuthLayout :title="`Join ${organization}`">
        <form @submit.prevent="submit" class="space-y-5">
            <p class="text-sm text-slate-300">
                You have been invited as <strong class="text-white">{{ roleLabel }}</strong>.
                Choose your name and a password to finish.
            </p>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                <input :value="email" disabled
                    class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-400" />
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-slate-300 mb-2">Your name</label>
                <input id="name" v-model="form.name" required autocomplete="name"
                    class="w-full px-4 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white focus:outline-none focus:border-blue-500" />
                <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                <input id="password" v-model="form.password" type="password" required autocomplete="new-password"
                    class="w-full px-4 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white focus:outline-none focus:border-blue-500" />
                <p v-if="form.errors.password" class="text-red-400 text-sm mt-1">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-2">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password"
                    class="w-full px-4 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white focus:outline-none focus:border-blue-500" />
            </div>

            <button type="submit" :disabled="form.processing"
                class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-600 text-white font-medium rounded-lg transition-colors">
                {{ form.processing ? 'Creating account…' : 'Create account' }}
            </button>
        </form>
    </AuthLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

const props = defineProps({
    token: String,
    email: String,
    organization: String,
    role: String,
})

const roleLabel = computed(() => props.role.replace('_', ' '))

const form = useForm({ name: '', password: '', password_confirmation: '' })
const submit = () => form.post(`/invitations/${props.token}`)
</script>
