<template>
    <Head :title="`Join ${organization}`" />

    <AuthShell
        icon="🎉"
        :title="`Join ${organization}`"
        subtitle="Create your account to get started."
    >
        <form @submit.prevent="submit" class="space-y-5">
            <!-- Role badge -->
            <div
                class="rounded-lg border border-blue-400/20 bg-blue-500/10 p-3 text-center text-sm text-blue-100"
            >
                You've been invited as
                <strong class="capitalize text-white">{{ roleLabel }}</strong>
            </div>

            <!-- Email (fixed: the invitation is tied to this address) -->
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-300"
                    >Email</label
                >
                <input
                    :value="email"
                    disabled
                    class="w-full cursor-not-allowed rounded-lg border border-white/10 bg-slate-900/40 px-4 py-2.5 text-slate-400"
                />
            </div>

            <!-- Name -->
            <div>
                <label
                    for="name"
                    class="mb-2 block text-sm font-medium text-slate-300"
                    >Your name</label
                >
                <input
                    id="name"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full rounded-lg border border-white/10 bg-slate-900/70 px-4 py-2.5 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="Full name"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-400">
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Password -->
            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium text-slate-300"
                    >Password</label
                >
                <div class="relative">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
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
                    Confirm password
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
                    v-if="
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
                {{ form.processing ? "Creating account…" : "Create account" }}
            </button>
        </form>

        <template #below>
            By creating an account you join
            <span class="text-slate-300">{{ organization }}</span
            >'s workspace.
        </template>
    </AuthShell>
</template>

<script setup>
import { computed, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";
import AuthShell from "@/Components/AuthShell.vue";

const props = defineProps({
    token: String,
    email: String,
    organization: String,
    role: String,
});

const showPassword = ref(false);

// "team_member" -> "team member"
const roleLabel = computed(() => props.role.replaceAll("_", " "));

const form = useForm({ name: "", password: "", password_confirmation: "" });

const submit = () => form.post(`/invitations/${props.token}`);
</script>
