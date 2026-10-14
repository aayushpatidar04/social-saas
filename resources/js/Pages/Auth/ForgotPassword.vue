<template>
    <Head title="Forgot Password" />

    <AuthShell
        title="Forgot your password?"
        subtitle="Enter your email and we'll send you a link to choose a new one."
    >
        <!-- Success message from the server -->
        <div
            v-if="status"
            class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-900/30 p-3 text-sm text-emerald-200"
            role="status"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label
                    for="email"
                    class="mb-2 block text-sm font-medium text-slate-300"
                >
                    Email Address
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-lg border border-white/10 bg-slate-900/70 px-4 py-2.5 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="you@example.com"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-400">
                    {{ form.errors.email }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
            >
                <span v-if="!form.processing">Email Password Reset Link</span>
                <span v-else>Sending...</span>
            </button>

            <p class="text-center text-sm">
                <Link href="/login" class="text-blue-400 hover:text-blue-300"
                    >← Back to sign in</Link
                >
            </p>
        </form>

        <template #below>
            Didn't get the email? Check your spam folder, or contact your
            administrator.
        </template>
    </AuthShell>
</template>

<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";
import Link from "@/Components/Link.vue";

defineProps({
    status: { type: String, default: "" },
});

const form = useForm({
    email: "",
});

const submit = () => {
    form.post("/forgot-password");
};
</script>
