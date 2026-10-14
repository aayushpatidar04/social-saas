<template>
    <div
        class="relative min-h-screen overflow-x-hidden bg-[#050816] text-white"
    >
        <VideoBackground />

        <div class="relative z-10 flex min-h-screen flex-col">
            <!-- Brand -->
            <header
                class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-5"
            >
                <Link href="/" class="flex items-center gap-3 !text-white">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-lg shadow-lg shadow-blue-600/30"
                        >💬</span
                    >
                    <span class="text-lg font-bold tracking-tight">{{
                        site.name
                    }}</span>
                </Link>
                <Link
                    href="/"
                    class="text-sm text-slate-300 transition-colors hover:text-white"
                    >← Back to home</Link
                >
            </header>

            <!-- Card -->
            <main class="flex flex-1 items-center justify-center px-4 py-10">
                <div class="w-full max-w-md">
                    <div
                        class="rounded-2xl border border-white/10 bg-[#0b1230]/70 p-8 shadow-2xl shadow-black/40 backdrop-blur-xl"
                    >
                        <div class="mb-8 text-center">
                            <h1 class="text-3xl font-bold tracking-tight">
                                Welcome back
                            </h1>
                            <p class="mt-2 text-sm text-slate-400">
                                Sign in to your organization's workspace
                            </p>
                        </div>

                        <!-- Status (e.g. "Your password has been reset") -->
                        <div
                            v-if="status"
                            class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-900/30 p-3 text-sm text-emerald-200"
                        >
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Email -->
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
                                <p
                                    v-if="form.errors.email"
                                    class="mt-1 text-sm text-red-400"
                                >
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <!-- Password -->
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
                                        :type="
                                            showPassword ? 'text' : 'password'
                                        "
                                        required
                                        autocomplete="current-password"
                                        class="w-full rounded-lg border border-white/10 bg-slate-900/70 py-2.5 pl-4 pr-11 text-white placeholder-slate-500 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="••••••••"
                                    />
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        :aria-label="
                                            showPassword
                                                ? 'Hide password'
                                                : 'Show password'
                                        "
                                        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-white"
                                    >
                                        <EyeSlashIcon
                                            v-if="showPassword"
                                            class="h-5 w-5"
                                        />
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

                            <!-- Remember + forgot -->
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <label
                                    for="remember"
                                    class="flex cursor-pointer items-center text-slate-300"
                                >
                                    <input
                                        id="remember"
                                        v-model="form.remember"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500"
                                    />
                                    <span class="ml-2">Remember me</span>
                                </label>
                                <Link
                                    href="/forgot-password"
                                    class="text-blue-400 hover:text-blue-300"
                                >
                                    Forgot password?
                                </Link>
                            </div>

                            <!-- Error Message -->
                            <div
                                v-if="form.errors.login"
                                class="rounded-lg border border-red-700 bg-red-900/30 p-4 text-red-200"
                            >
                                {{ form.errors.login }}
                            </div>

                            <!-- Submit -->
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
                            >
                                <span v-if="!form.processing">Sign In</span>
                                <span v-else>Signing In...</span>
                            </button>
                        </form>
                    </div>

                    <p class="mt-6 text-center text-xs text-slate-500">
                        Accounts are created by invitation. Need access? Contact
                        your administrator.
                    </p>
                </div>
            </main>

            <SiteFooter compact />
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import { EyeIcon, EyeSlashIcon } from "@heroicons/vue/24/outline";
import Link from "@/Components/Link.vue";
import VideoBackground from "@/Components/VideoBackground.vue";
import SiteFooter from "@/Components/SiteFooter.vue";
import { site } from "@/site";

defineProps({
    status: { type: String, default: "" },
});

const showPassword = ref(false);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post("/login", {
        onFinish: () => form.reset("password"),
    });
};
</script>
