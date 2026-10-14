<script setup>
import { ref } from "vue";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { Link } from "@inertiajs/vue3";

const showingNavigationDropdown = ref(false);

// add more entries here as the app grows
const navItems = [{ label: "Dashboard", route: "dashboard" }];

const linkClass = (active) => [
    "relative inline-flex items-center px-1 text-sm font-medium transition-colors",
    active
        ? "text-white after:absolute after:inset-x-0 after:-bottom-[1.05rem] after:h-0.5 after:rounded-full after:bg-blue-400"
        : "text-slate-400 hover:text-white",
];

const responsiveLinkClass = (active) => [
    "block rounded-lg border px-3 py-2 text-sm font-medium transition-colors",
    active
        ? "border-blue-500/30 bg-blue-500/15 text-blue-100"
        : "border-transparent text-slate-300 hover:bg-white/5 hover:text-white",
];

const initial = (name) => (name || "?").trim().charAt(0).toUpperCase();
</script>

<template>
    <div
        class="min-h-screen text-white"
        style="
            background: radial-gradient(
                ellipse at 50% 0%,
                #131c4a 0%,
                #0b1230 45%,
                #060a1f 100%
            );
        "
    >
        <nav
            class="sticky top-0 z-40 border-b border-white/10 bg-[#0b1230]/80 backdrop-blur-md"
        >
            <!-- Primary Navigation Menu -->
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo
                                    class="block h-9 w-auto fill-current text-white"
                                />
                            </Link>
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:ms-10 sm:flex">
                            <Link
                                v-for="item in navItems"
                                :key="item.route"
                                :href="route(item.route)"
                                :class="linkClass(route().current(item.route))"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>

                    <div class="hidden sm:ms-6 sm:flex sm:items-center">
                        <!-- Settings Dropdown -->
                        <div class="relative ms-3">
                            <Dropdown
                                align="right"
                                width="48"
                                :content-classes="[
                                    'py-1',
                                    'rounded-xl border border-white/10 bg-[#0b1230]/95 backdrop-blur-md',
                                ]"
                            >
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 py-1.5 pe-3 ps-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10 focus:outline-none"
                                    >
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-500/20 text-xs font-semibold text-blue-100"
                                        >
                                            {{ initial($page.props.auth.user.name) }}
                                        </span>
                                        {{ $page.props.auth.user.name }}

                                        <svg
                                            class="h-4 w-4 text-slate-400"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">
                                        Profile
                                    </DropdownLink>
                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                    >
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Hamburger -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            @click="
                                showingNavigationDropdown =
                                    !showingNavigationDropdown
                            "
                            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-400 transition-colors hover:bg-white/10 hover:text-white focus:outline-none"
                            aria-label="Toggle navigation"
                        >
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex':
                                            !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex':
                                            showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Responsive Navigation Menu -->
            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="border-t border-white/10 sm:hidden"
            >
                <div class="space-y-1 px-4 pb-3 pt-3">
                    <Link
                        v-for="item in navItems"
                        :key="item.route"
                        :href="route(item.route)"
                        :class="responsiveLinkClass(route().current(item.route))"
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <!-- Responsive Settings Options -->
                <div class="border-t border-white/10 px-4 pb-3 pt-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-500/20 font-semibold text-blue-100"
                        >
                            {{ initial($page.props.auth.user.name) }}
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-base font-medium text-white">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="truncate text-sm text-slate-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <Link
                            :href="route('profile.edit')"
                            :class="responsiveLinkClass(route().current('profile.edit'))"
                        >
                            Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="block w-full rounded-lg border border-transparent px-3 py-2 text-start text-sm font-medium text-red-300 transition-colors hover:bg-red-500/10"
                        >
                            Log Out
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header v-if="$slots.header">
            <div class="mx-auto max-w-7xl px-4 pb-2 pt-8 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>