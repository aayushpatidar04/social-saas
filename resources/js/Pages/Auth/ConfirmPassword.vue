<template>
    <Head title="Confirm Password" />

    <AuthShell
        icon="🛡️"
        title="Confirm your password"
        subtitle="This is a secure area. Please confirm your password before continuing."
    >
        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label
                    for="password"
                    class="mb-2 block text-sm font-medium text-slate-300"
                >
                    Password
                </label>
                <div class="relative">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autofocus
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-white/10 bg-slate-900/70 py-2.5 pl-4 pr-11 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="••••••••"
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

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
            >
                <span v-if="!form.processing">Confirm</span>
                <span v-else>Confirming...</span>
            </button>

            <p class="text-center text-sm">
                <button
                    type="button"
                    @click="back"
                    class="text-slate-400 underline transition-colors hover:text-white"
                >
                    ← Go back
                </button>
            </p>
        </form>
    </AuthShell>
</template>

<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";
import AuthShell from "@/Components/AuthShell.vue";

const showPassword = ref(false);

const form = useForm({
    password: "",
});

const submit = () => {
    form.post("/confirm-password", {
        onFinish: () => form.reset(),
    });
};

// "Go back" returns to the page that asked for confirmation
const back = () => window.history.back();
</script>
