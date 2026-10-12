<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <nav class="bg-slate-800 border-b border-purple-900/60 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-8">
                    <Link href="/admin" class="text-xl font-bold text-white hover:text-purple-300 transition-colors">
                        🛡️ Platform Admin
                    </Link>
                    <div class="hidden md:flex items-center gap-6 text-sm">
                        <Link v-for="item in items" :key="item.href" :href="item.href"
                            :class="isActive(item) ? 'text-white font-semibold' : 'text-slate-300 hover:text-white'">
                            {{ item.label }}
                        </Link>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-400 hidden sm:block">{{ user.name }}</span>
                    <Link href="/profile" class="text-sm text-slate-300 hover:text-white">Profile</Link>
                    <button @click="logout" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-sm">Logout</button>
                </div>
            </div>
        </nav>

        <ToastContainer />

        <main class="max-w-7xl mx-auto px-6 py-8">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Link from '@/Components/Link.vue'
import ToastContainer from '@/Components/ToastContainer.vue'
import { useFlashToasts } from '@/composables/useFlashToasts'

useFlashToasts()

const page = usePage()
const user = computed(() => page.props.auth.user)

const items = [
    { label: 'Overview', href: '/admin', exact: true },
    { label: 'Organizations', href: '/admin/organizations' },
    { label: 'Integrations', href: '/admin/credentials' },
]

const isActive = (item) => item.exact ? page.url === item.href : page.url.startsWith(item.href)
const logout = () => router.post('/logout')
</script>
