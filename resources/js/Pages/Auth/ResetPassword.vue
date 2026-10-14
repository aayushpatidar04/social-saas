<template>
    <Head title="Reset Password" />

    <AuthShell
        icon="🔒"
        title="Choose a new password"
        subtitle="Pick a strong password you haven't used before."
    >
        <form @submit.prevent="submit" class="space-y-5">
            <!-- Email (comes from the link, so it's locked) -->
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
                    readonly
                    autocomplete="username"
                    class="w-full cursor-not-allowed rounded-lg border border-white/10 bg-slate-900/40 px-4 py-2.5 text-slate-400 focus:outline-none"
                />
                <!-- An invalid or expired link is reported on this field by the server -->
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-400">
                    {{ form.errors.email }}
                    <Link
                        href="/forgot-password"
                        class="ml-1 underline hover:text-red-300"
                    >
                        Request a new link
                    </Link>
                </p>
            </div>

            <!-- New password -->
            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium text-slate-300"
                >
                    New Password
                </label>
                <div class="relative">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autofocus
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-white/10 bg-slate-900/70 py-2.5 pl-4 pr-11 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="At least 8 characters"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        :aria-label="
                            showPassword ? 'Hide password' : 'Show password'
                        "
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-white"
                    >
                        <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
                        <EyeIcon v-else class="h-5 w-5" />
                    </button>
                </div>
                <p
                    v-if="form.errors.password"
                    class="mt-1 text-sm text-red-400"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Confirm -->
            <div>
                <label
                    for="password_confirmation"
                    class="mb-2 block text-sm font-medium text-slate-300"
                >
                    Confirm Password
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-white/10 bg-slate-900/70 px-4 py-2.5 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="Repeat the password"
                />
                <p
                    v-if="form.errors.password_confirmation"
                    class="mt-1 text-sm text-red-400"
                >
                    {{ form.errors.password_confirmation }}
                </p>
                <p
                    v-else-if="
                        form.password_confirmation &&
                        form.password !== form.password_confirmation
                    "
                    class="mt-1 text-sm text-yellow-400"
                >
                    Passwords don't match yet.
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
            >
                <span v-if="!form.processing">Reset Password</span>
                <span v-else>Saving...</span>
            </button>

            <p class="text-center text-sm">
                <Link href="/login" class="text-blue-400 hover:text-blue-300"
                    >← Back to sign in</Link
                >
            </p>
        </form>
    </AuthShell>
</template>

<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";
import AuthShell from "@/Components/AuthShell.vue";
import Link from "@/Components/Link.vue";

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const showPassword = ref(false);

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post("/reset-password", {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>
