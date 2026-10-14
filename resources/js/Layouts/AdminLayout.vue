<template>
    <div class="relative flex min-h-screen flex-col overflow-x-hidden bg-[#050816] text-white">
        <!-- Soft violet glow: a different tint from the tenant app, so admin is recognisable -->
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div
                class="absolute inset-0"
                style="background: radial-gradient(circle at 50% -10%, rgba(124, 58, 237, 0.2), transparent 45%)"
            ></div>
            <div class="absolute -left-40 top-40 h-96 w-96 rounded-full bg-violet-600/10 blur-3xl"></div>
            <div class="absolute -right-40 top-2/3 h-96 w-96 rounded-full bg-indigo-500/10 blur-3xl"></div>
        </div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="sticky top-0 z-40 border-b border-violet-500/20 bg-[#050816]/70 backdrop-blur-xl">
                <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-6">
                        <Link href="/admin" class="flex shrink-0 items-center gap-3 !text-white">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-indigo-600 shadow-lg shadow-violet-600/30"
                            >
                                <ShieldCheckIcon class="h-5 w-5" />
                            </span>
                            <span class="leading-tight">
                                <span class="block text-base font-bold tracking-tight">Platform Admin</span>
                                <span class="hidden text-[10px] uppercase tracking-widest text-violet-300 sm:block">
                                    {{ site.name }}
                                </span>
                            </span>
                        </Link>

                        <!-- Desktop links -->
                        <div class="hidden items-center gap-1 md:flex">
                            <Link
                                v-for="item in items"
                                :key="item.href"
                                :href="item.href"
                                :class="[
                                    'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    isActive(item)
                                        ? 'bg-violet-500/15 !text-white'
                                        : '!text-slate-300 hover:bg-white/5 hover:!text-white',
                                ]"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.label }}
                            </Link>
                        </div>
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
                                    class="flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-violet-500 to-indigo-600 text-sm font-bold"
                                >
                                    {{ initials }}
                                </span>
                                <span class="hidden max-w-[10rem] truncate text-sm sm:block">{{ user.name }}</span>
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
                                    class="absolute right-0 mt-2 w-60 origin-top-right overflow-hidden rounded-xl border border-white/10 bg-[#0b1230]/95 shadow-2xl shadow-black/50 backdrop-blur-xl"
                                >
                                    <div class="border-b border-white/10 px-4 py-3">
                                        <p class="truncate font-medium">{{ user.name }}</p>
                                        <p class="truncate text-xs text-slate-400">{{ user.email }}</p>
                                        <span
                                            class="mt-2 inline-block rounded-full bg-violet-500/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-200"
                                        >
                                            Super admin
                                        </span>
                                    </div>

                                    <div class="py-1">
                                        <Link
                                            href="/profile"
                                            role="menuitem"
                                            class="flex items-center gap-3 px-4 py-2.5 text-sm !text-slate-300 transition-colors hover:bg-white/5 hover:!text-white"
                                        >
                                            <UserCircleIcon class="h-4 w-4 text-slate-500" />
                                            Profile
                                        </Link>
                                    </div>

                                    <div class="border-t border-white/10 py-1">
                                        <button
                                            @click="logout"
                                            role="menuitem"
                                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                                        >
                                            <ArrowRightOnRectangleIcon class="h-4 w-4 text-slate-500" />
                                            Logout
                                        </button>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Mobile menu button (the old admin nav had no navigation on phones) -->
                        <button
                            @click="mobileOpen = !mobileOpen"
                            class="rounded-lg border border-white/10 bg-white/5 p-2 hover:bg-white/10 md:hidden"
                            :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
                        >
                            <XMarkIcon v-if="mobileOpen" class="h-6 w-6" />
                            <Bars3Icon v-else class="h-6 w-6" />
                        </button>
                    </div>
                </nav>

                <div v-if="mobileOpen" class="border-t border-white/10 px-4 pb-4 pt-2 md:hidden">
                    <Link
                        v-for="item in items"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium',
                            isActive(item) ? 'bg-violet-500/15 !text-white' : '!text-slate-300 hover:bg-white/5',
                        ]"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        {{ item.label }}
                    </Link>
                </div>
            </header>

            <ToastContainer />

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
                <slot />
            </main>

            <SiteFooter compact />
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    BuildingOffice2Icon,
    ChevronDownIcon,
    PuzzlePieceIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    UserCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import Link from '@/Components/Link.vue'
import ToastContainer from '@/Components/ToastContainer.vue'
import SiteFooter from '@/Components/SiteFooter.vue'
import { useFlashToasts } from '@/composables/useFlashToasts'
import { site } from '@/site'

useFlashToasts()

const page = usePage()
const user = computed(() => page.props.auth.user)

const items = [
    { label: 'Overview', href: '/admin', exact: true, icon: Squares2X2Icon },
    { label: 'Organizations', href: '/admin/organizations', icon: BuildingOffice2Icon },
    { label: 'Integrations', href: '/admin/credentials', icon: PuzzlePieceIcon },
]

const initials = computed(() =>
    (user.value.name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('')
)

// ignore ?search=… when deciding which link is active
const isActive = (item) => {
    const path = page.url.split('?')[0]

    return item.exact ? path === item.href : path.startsWith(item.href)
}

const menuOpen = ref(false)
const mobileOpen = ref(false)
const menuRoot = ref(null)

const onDocClick = (e) => {
    if (menuOpen.value && menuRoot.value && !menuRoot.value.contains(e.target)) menuOpen.value = false
}
const onKey = (e) => {
    if (e.key === 'Escape') {
        menuOpen.value = false
        mobileOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', onDocClick)
    document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick)
    document.removeEventListener('keydown', onKey)
})

watch(() => page.url, () => {
    menuOpen.value = false
    mobileOpen.value = false
})

const logout = () => router.post('/logout')
</script>