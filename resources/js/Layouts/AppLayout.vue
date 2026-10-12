<template>
    <!-- resources/js/Layouts/AppLayout.vue -->
    <div class="min-h-screen bg-slate-900 text-white">
        <!-- Impersonation banner (super admin viewing as an organization user) -->
        <div v-if="impersonating" class="bg-purple-700 text-white text-sm px-6 py-2 flex items-center justify-between">
            <span>👁️ You are viewing the app as <strong>{{ auth.user.name }}</strong> ({{ organization?.name }}).</span>
            <button @click="leaveImpersonation" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded">Back to admin</button>
        </div>

        <!-- Navbar -->
        <nav class="bg-slate-800 border-b border-slate-700 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex justify-between items-center">
                    <!-- Logo -->
                    <Link
                        href="/dashboard"
                        class="text-xl font-bold text-white hover:text-blue-400 transition-colors"
                    >
                        📊 Social Automation
                    </Link>

                    <!-- Nav Links -->
                    <div class="hidden md:flex items-center gap-6">
                        <Link
                            href="/dashboard"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Dashboard
                        </Link>

                        <Link
                            href="/inbox"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Inbox
                        </Link>

                        <Link
                            href="/social-posts"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Posts
                        </Link>

                        <Link
                            href="/knowledge-sources"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Knowledge
                        </Link>

                        <Link
                            href="/analytics"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Analytics
                        </Link>

                        <Link
                            href="/leads"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Leads
                        </Link>

                        <Link
                            href="/settings/social-accounts"
                            class="text-slate-300 hover:text-white transition-colors"
                        >
                            Accounts
                        </Link>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center gap-4">
                        <div class="relative group">
                            <button
                                class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 transition-colors"
                            >
                                {{ auth.user.name }}
                            </button>
                            <div
                                class="absolute right-0 mt-2 w-48 bg-slate-800 rounded-lg border border-slate-700 shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all"
                            >
                                <Link
                                    v-for="item in menu"
                                    :key="item.href"
                                    :href="item.href"
                                    class="block px-4 py-2 text-slate-300 hover:text-white border-b border-slate-700"
                                >
                                    {{ item.label }}
                                </Link>
                                <form @submit.prevent="logout" class="w-full">
                                    <button
                                        type="submit"
                                        class="w-full text-left px-4 py-2 text-slate-300 hover:text-white"
                                    >
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Toast Notifications -->
        <ToastContainer />

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-6 py-8">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import Link from "@/Components/Link.vue";
import ToastContainer from "@/Components/ToastContainer.vue";
import { useFlashToasts } from "@/composables/useFlashToasts";

useFlashToasts();

const page = usePage();
const auth = computed(() => page.props.auth);
const organization = computed(() => page.props.organization);
const impersonating = computed(() => page.props.impersonating);

const isAdmin = computed(() => auth.value.user.role === "admin");

const menu = computed(() => [
    { label: "Profile", href: "/profile" },
    { label: "Team", href: "/settings/team" },
    { label: "Organization", href: "/settings/organization" },
    ...(isAdmin.value && organization.value?.allow_custom_apps
        ? [{ label: "Integrations", href: "/settings/integrations" }]
        : []),
]);

const logout = () => {
    router.post("/logout");
};

const leaveImpersonation = () => {
    router.post("/impersonation/leave");
};
</script>
