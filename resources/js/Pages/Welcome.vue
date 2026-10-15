<template>
    <div class="relative min-h-screen overflow-x-hidden bg-[#050816] text-white">
        <!-- ================= Background (fixed, behind everything) ================= -->
        <VideoBackground />

        <!-- ================= Content ================= -->
        <div class="relative z-10 flex min-h-screen flex-col">
            <!-- Navigation -->
            <header class="sticky top-0 z-30 border-b border-white/10 bg-[#050816]/60 backdrop-blur-md">
                <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <Link href="/" class="flex items-center gap-2 text-white hover:text-blue-400 transition-colors">
                            <img 
                                src="/assets/brand/logo-horizontal.svg" 
                                alt="InTouch Connect Automation Logo" 
                                width={150} 
                                height={40} 
                                priority
                                class="h-8 w-auto object-contain"
                            />
                            <span class="sr-only">InTouch Connect Automation</span>
                        </Link>
                    </div>

                    <div class="flex items-center gap-6">
                        <a href="#features" class="hidden text-sm text-slate-300 transition-colors hover:text-white md:block">Features</a>
                        <a href="#how" class="hidden text-sm text-slate-300 transition-colors hover:text-white md:block">How it works</a>
                        <Link
                            href="/login"
                            class="rounded-lg border border-white/15 bg-white/10 px-5 py-2 text-sm font-medium !text-white transition-colors hover:bg-white/20"
                        >
                            Sign In
                        </Link>
                    </div>
                </nav>
            </header>

            <main class="flex-1">
                <!-- Hero -->
                <section class="mx-auto max-w-4xl px-6 pb-20 pt-24 text-center md:pt-32">
                    <span
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-blue-400/30 bg-blue-500/10 px-4 py-1.5 text-sm text-blue-200"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                        AI-powered social engagement
                    </span>

                    <h2 class="mb-6 text-5xl font-extrabold leading-tight tracking-tight md:text-7xl">
                        Automate Your
                        <span class="bg-gradient-to-r from-blue-400 via-indigo-400 to-violet-400 bg-clip-text text-transparent">
                            Social Media
                        </span>
                    </h2>

                    <p class="mx-auto mb-10 max-w-2xl text-lg text-slate-300 md:text-xl">
                        Analyze every comment, detect hot leads and reply instantly, across Facebook, Instagram, YouTube,
                        X and LinkedIn, from one place.
                    </p>

                    <div class="flex flex-wrap items-center justify-center gap-4">
                        <Link
                            href="/login"
                            class="inline-block rounded-xl bg-blue-600 px-8 py-3.5 font-semibold !text-white shadow-lg shadow-blue-600/30 transition-all hover:-translate-y-0.5 hover:bg-blue-500"
                        >
                            Sign In
                        </Link>
                        <a
                            href="#features"
                            class="inline-block rounded-xl border border-white/20 px-8 py-3.5 font-semibold text-slate-200 transition-colors hover:bg-white/10"
                        >
                            See what it does
                        </a>
                    </div>

                    <!-- Platforms -->
                    <div class="mt-14 flex flex-wrap items-center justify-center gap-3 text-sm text-slate-300">
                        <span
                            v-for="p in platforms"
                            :key="p"
                            class="rounded-full border border-white/10 bg-white/5 px-4 py-1.5 backdrop-blur"
                        >
                            {{ p }}
                        </span>
                    </div>
                </section>

                <!-- Features -->
                <section id="features" class="mx-auto max-w-6xl scroll-mt-24 px-6 py-16">
                    <h3 class="mb-3 text-center text-3xl font-bold md:text-4xl">Everything your comments need</h3>
                    <p class="mx-auto mb-12 max-w-2xl text-center text-slate-400">
                        From the moment a comment lands to the moment it is answered, without anyone watching a notification feed.
                    </p>

                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="f in features"
                            :key="f.title"
                            class="group rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md transition-all hover:-translate-y-1 hover:border-blue-400/40 hover:bg-white/10"
                        >
                            <div
                                class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500/30 to-indigo-500/30 text-2xl"
                            >
                                {{ f.icon }}
                            </div>
                            <h4 class="mb-2 text-xl font-bold">{{ f.title }}</h4>
                            <p class="text-slate-400">{{ f.text }}</p>
                        </article>
                    </div>
                </section>

                <!-- How it works -->
                <section id="how" class="mx-auto max-w-6xl scroll-mt-24 px-6 py-16">
                    <h3 class="mb-12 text-center text-3xl font-bold md:text-4xl">How it works</h3>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div
                            v-for="(s, i) in steps"
                            :key="s.title"
                            class="relative rounded-2xl border border-white/10 bg-[#0b1230]/70 p-6 backdrop-blur-md"
                        >
                            <span
                                class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 font-bold shadow-lg shadow-blue-600/30"
                            >
                                {{ i + 1 }}
                            </span>
                            <h4 class="mb-2 text-lg font-bold">{{ s.title }}</h4>
                            <p class="text-slate-400">{{ s.text }}</p>
                        </div>
                    </div>
                </section>

                <!-- Call to action -->
                <section class="mx-auto max-w-4xl px-6 pb-24 pt-8">
                    <div
                        class="rounded-3xl border border-blue-400/20 bg-gradient-to-br from-blue-600/20 to-indigo-600/10 p-10 text-center backdrop-blur-md"
                    >
                        <h3 class="mb-3 text-3xl font-bold">Ready to stop missing comments?</h3>
                        <p class="mb-8 text-slate-300">Sign in to your organization's workspace.</p>
                        <Link
                            href="/login"
                            class="inline-block rounded-xl bg-blue-600 px-8 py-3.5 font-semibold !text-white shadow-lg shadow-blue-600/30 transition-all hover:-translate-y-0.5 hover:bg-blue-500"
                        >
                            Sign In
                        </Link>
                    </div>
                </section>
            </main>

            <!-- ================= Footer ================= -->
            <SiteFooter />
        </div>
    </div>
</template>

<script setup>
import Link from '@/Components/Link.vue'
import SiteFooter from '@/Components/SiteFooter.vue'
import VideoBackground from '@/Components/VideoBackground.vue'

const year = new Date().getFullYear()

const platforms = ['Facebook', 'Instagram', 'YouTube', 'X (Twitter)', 'LinkedIn']

const features = [
    { icon: '🤖', title: 'AI comment analysis', text: 'Every comment is scored for sentiment and intent, so complaints, questions and buyers are told apart automatically.' },
    { icon: '🎯', title: 'Lead detection', text: 'Spot purchase interest the moment it appears and keep a lead list your team can follow up on.' },
    { icon: '💬', title: 'Automated replies', text: 'Natural, on-brand answers posted back to the comment, grounded in your own knowledge base.' },
    { icon: '🔗', title: 'Multi-platform', text: 'Facebook Pages, Instagram (with or without a Facebook Page), YouTube, X and LinkedIn in one inbox.' },
    { icon: '⚡', title: 'Real-time where possible', text: 'Instant webhooks for Facebook and Instagram, scheduled sync for the platforms that have no push option.' },
    { icon: '👥', title: 'Teams & organizations', text: 'Separate workspaces, roles for admins, managers and viewers, and per-account on/off control for AI replies.' },
]

const steps = [
    { title: 'Connect your accounts', text: 'Sign in with Facebook, Instagram, YouTube, X or LinkedIn and choose which pages and channels to manage.' },
    { title: 'Comments are analyzed', text: 'New comments arrive by webhook or sync and are classified for sentiment, intent and lead potential.' },
    { title: 'Replies go out', text: 'The AI drafts and posts a reply, and your team can step into any conversation at any time.' },
]
</script>