<template>
    <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
        <!-- Hidden for users who prefer reduced motion; the dark colour behind it remains -->
        <video
            autoplay
            muted
            loop
            playsinline
            preload="metadata"
            class="absolute inset-0 h-full w-full object-cover"
        >
            <source src="/assets/videos/event-bg.mp4" type="video/mp4" />
        </video>

        <div class="absolute inset-0 bg-[#050816]/75"></div>

        <div
            class="absolute inset-0"
            style="background: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.22), transparent 42%)"
        ></div>
        <div
            class="absolute inset-0"
            style="background: linear-gradient(180deg, rgba(5, 8, 22, 0.35) 0%, rgba(5, 8, 22, 0.55) 55%, #050816 100%)"
        ></div>

        <div class="absolute -left-32 top-20 h-96 w-96 animate-pulse rounded-full bg-blue-600/20 blur-3xl"></div>
        <div
            class="absolute -right-32 top-1/3 h-96 w-96 animate-pulse rounded-full bg-indigo-500/20 blur-3xl"
            style="animation-delay: 1.5s"
        ></div>
    </div>
</template>