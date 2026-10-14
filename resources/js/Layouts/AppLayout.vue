<template>
    <div
        class="relative flex min-h-screen flex-col overflow-x-hidden bg-[#050816] text-white"
    >
        <!-- Soft background glow (static: the working screens stay easy to read) -->
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div
                class="absolute inset-0"
                style="
                    background: radial-gradient(
                        circle at 50% -10%,
                        rgba(37, 99, 235, 0.18),
                        transparent 45%
                    );
                "
            ></div>
            <div
                class="absolute -left-40 top-40 h-96 w-96 rounded-full bg-blue-600/10 blur-3xl"
            ></div>
            <div
                class="absolute -right-40 top-2/3 h-96 w-96 rounded-full bg-indigo-500/10 blur-3xl"
            ></div>
        </div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <!-- Impersonation banner (super admin viewing as an organization user) -->
            <div
                v-if="impersonating"
                class="flex items-center justify-between gap-3 bg-purple-700 px-6 py-2 text-sm text-white"
            >
                <span>
                    👁️ You are viewing the app as
                    <strong>{{ auth.user.name }}</strong> ({{
                        organization?.name
                    }}).
                </span>
                <button
                    @click="leaveImpersonation"
                    class="rounded bg-white/20 px-3 py-1 hover:bg-white/30"
                >
                    Back to admin
                </button>
            </div>

            <!-- Navbar -->
            <header
                class="sticky top-0 z-40 border-b border-white/10 bg-[#050816]/70 backdrop-blur-xl"
            >
                <nav
                    class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6"
                >
                    <!-- Logo -->
                    <Link
                        href="/dashboard"
                        class="flex shrink-0 items-center gap-3 !text-white"
                    >
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-lg shadow-lg shadow-blue-600/30"
                            >💬</span
                        >
                        <span
                            class="hidden text-base font-bold tracking-tight sm:block"
                        >
                            {{ site.name }}
                        </span>
                    </Link>

                    <!-- Desktop links -->
                    <div class="hidden items-center gap-1 lg:flex">
                        <Link
                            v-for="item in links"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                isActive(item.href)
                                    ? 'bg-white/10 !text-white'
                                    : '!text-slate-300 hover:bg-white/5 hover:!text-white',
                            ]"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.label }}
                        </Link>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- User menu -->
                        <div class="relative" ref="menuRoot">
                            <button
                                @click="menuOpen = !menuOpen"
                                :aria-expanded="menuOpen"
                                aria-haspopup="menu"
                                class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 py-1.5 pl-1.5 pr-3 transition-colors hover:bg-white/10"
                            >
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-bold"
                                >
                                    {{ initials }}
                                </span>
                                <span
                                    class="hidden max-w-[10rem] truncate text-sm sm:block"
                                    >{{ auth.user.name }}</span
                                >
                                <ChevronDownIcon
                                    class="h-4 w-4 text-slate-400 transition-transform"
                                    :class="menuOpen && 'rotate-180'"
                                />
                            </button>

                            <Transition
                                enter-active-class="transition duration-100 ease-out"
                                enter-from-class="scale-95 opacity-0"
                                enter-to-class="scale-100 opacity-100"
                                leave-active-class="transition duration-75 ease-in"
                                leave-from-class="scale-100 opacity-100"
                                leave-to-class="scale-95 opacity-0"
                            >
                                <div
                                    v-if="menuOpen"
                                    role="menu"
                                    class="absolute right-0 mt-2 w-64 origin-top-right overflow-hidden rounded-xl border border-white/10 bg-[#0b1230]/95 shadow-2xl shadow-black/50 backdrop-blur-xl"
                                >
                                    <div
                                        class="border-b border-white/10 px-4 py-3"
                                    >
                                        <p class="truncate font-medium">
                                            {{ auth.user.name }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-slate-400"
                                        >
                                            {{ auth.user.email }}
                                        </p>
                                        <p
                                            v-if="organization"
                                            class="mt-2 flex items-center gap-2 text-xs text-slate-300"
                                        >
                                            <BuildingOffice2Icon
                                                class="h-4 w-4 text-slate-500"
                                            />
                                            <span class="truncate">{{
                                                organization.name
                                            }}</span>
                                            <span
                                                class="rounded-full bg-blue-500/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-200"
                                            >
                                                {{ organization.plan }}
                                            </span>
                                        </p>
                                    </div>

                                    <div class="py-1">
                                        <Link
                                            v-for="item in menu"
                                            :key="item.href"
                                            :href="item.href"
                                            role="menuitem"
                                            class="flex items-center gap-3 px-4 py-2.5 text-sm !text-slate-300 transition-colors hover:bg-white/5 hover:!text-white"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-4 w-4 text-slate-500"
                                            />
                                            {{ item.label }}
                                        </Link>
                                    </div>

                                    <div class="border-t border-white/10 py-1">
                                        <button
                                            @click="logout"
                                            role="menuitem"
                                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                                        >
                                            <ArrowRightOnRectangleIcon
                                                class="h-4 w-4 text-slate-500"
                                            />
                                            Logout
                                        </button>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Mobile menu button -->
                        <button
                            @click="mobileOpen = !mobileOpen"
                            class="rounded-lg border border-white/10 bg-white/5 p-2 hover:bg-white/10 lg:hidden"
                            :aria-label="
                                mobileOpen ? 'Close menu' : 'Open menu'
                            "
                        >
                            <XMarkIcon v-if="mobileOpen" class="h-6 w-6" />
                            <Bars3Icon v-else class="h-6 w-6" />
                        </button>
                    </div>
                </nav>

                <!-- Mobile panel (the old layout had no navigation at all on phones) -->
                <div
                    v-if="mobileOpen"
                    class="border-t border-white/10 px-4 pb-4 pt-2 lg:hidden"
                >
                    <Link
                        v-for="item in links"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium',
                            isActive(item.href)
                                ? 'bg-white/10 !text-white'
                                : '!text-slate-300 hover:bg-white/5',
                        ]"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        {{ item.label }}
                    </Link>
                </div>
            </header>

            <ToastContainer />

            <!-- Content -->
            <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
                <slot />
            </main>

            <SiteFooter compact />
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    BookOpenIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    ChevronDownIcon,
    DocumentTextIcon,
    FunnelIcon,
    InboxIcon,
    LinkIcon,
    PuzzlePieceIcon,
    Squares2X2Icon,
    UserCircleIcon,
    UsersIcon,
    XMarkIcon,
} from "@heroicons/vue/24/outline";
import Link from "@/Components/Link.vue";
import ToastContainer from "@/Components/ToastContainer.vue";
import SiteFooter from "@/Components/SiteFooter.vue";
import { useFlashToasts } from "@/composables/useFlashToasts";
import { site } from "@/site";

useFlashToasts();

const page = usePage();
const auth = computed(() => page.props.auth);
const organization = computed(() => page.props.organization);
const impersonating = computed(() => page.props.impersonating);

const isAdmin = computed(() => auth.value.user.role === "admin");

const links = [
    { label: "Dashboard", href: "/dashboard", icon: Squares2X2Icon },
    { label: "Inbox", href: "/inbox", icon: InboxIcon },
    { label: "Posts", href: "/social-posts", icon: DocumentTextIcon },
    { label: "Knowledge", href: "/knowledge-sources", icon: BookOpenIcon },
    { label: "Analytics", href: "/analytics", icon: ChartBarIcon },
    { label: "Leads", href: "/leads", icon: FunnelIcon },
    { label: "Accounts", href: "/settings/social-accounts", icon: LinkIcon },
];

const menu = computed(() => [
    { label: "Profile", href: "/profile", icon: UserCircleIcon },
    { label: "Team", href: "/settings/team", icon: UsersIcon },
    {
        label: "Organization",
        href: "/settings/organization",
        icon: BuildingOffice2Icon,
    },
    ...(isAdmin.value && organization.value?.allow_custom_apps
        ? [
              {
                  label: "Integrations",
                  href: "/settings/integrations",
                  icon: PuzzlePieceIcon,
              },
          ]
        : []),
]);

const initials = computed(() =>
    (auth.value.user.name || "?")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join(""),
);

// highlight the link of the page we are on (/settings/... pages highlight "Accounts" only for that exact section)
const isActive = (href) =>
    page.url === href ||
    page.url.startsWith(href + "/") ||
    page.url.startsWith(href + "?");

// ---- menus: close on outside click, Escape and navigation
const menuOpen = ref(false);
const mobileOpen = ref(false);
const menuRoot = ref(null);

const onDocClick = (e) => {
    if (menuOpen.value && menuRoot.value && !menuRoot.value.contains(e.target))
        menuOpen.value = false;
};
const onKey = (e) => {
    if (e.key === "Escape") {
        menuOpen.value = false;
        mobileOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", onDocClick);
    document.addEventListener("keydown", onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener("click", onDocClick);
    document.removeEventListener("keydown", onKey);
});

watch(
    () => page.url,
    () => {
        menuOpen.value = false;
        mobileOpen.value = false;
    },
);

const logout = () => router.post("/logout");
const leaveImpersonation = () => router.post("/impersonation/leave");
</script>
