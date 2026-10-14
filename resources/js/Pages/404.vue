<template>
    <Head title="404 · Lost in space" />

    <div
        class="stage relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-16 text-white"
        @mousemove="onMove"
    >
        <!-- Nebula glow -->
        <div class="nebula pointer-events-none absolute inset-0"></div>

        <!-- Stars (far layer) -->
        <div
            class="pointer-events-none absolute inset-0 transition-transform duration-200 ease-out"
            :style="layer(0.3)"
        >
            <span
                v-for="(s, i) in stars"
                :key="i"
                class="star absolute rounded-full bg-white"
                :style="{
                    left: s.x + '%',
                    top: s.y + '%',
                    width: s.s + 'px',
                    height: s.s + 'px',
                    animationDelay: s.d + 's',
                    animationDuration: s.t + 's',
                }"
            ></span>
        </div>

        <!-- Shooting stars -->
        <span class="shoot shoot-1"></span>
        <span class="shoot shoot-2"></span>
        <span class="shoot shoot-3"></span>

        <!-- UFO with a curious little alien -->
        <div class="ufo pointer-events-none absolute left-0 top-[14%] z-10 w-24 md:w-32">
            <svg viewBox="0 0 120 80" class="ufo-bob w-full" aria-hidden="true">
                <defs>
                    <linearGradient id="beam" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#7dd3fc" stop-opacity="0.55" />
                        <stop offset="1" stop-color="#7dd3fc" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="saucer" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#cbd5e1" />
                        <stop offset="1" stop-color="#64748b" />
                    </linearGradient>
                </defs>
                <polygon class="beam" points="42,46 78,46 104,80 16,80" fill="url(#beam)" />
                <!-- dome + alien -->
                <path d="M38 34 Q60 -4 82 34 Z" fill="#bae6fd" fill-opacity="0.35" stroke="#e0f2fe" stroke-opacity="0.6" />
                <circle cx="60" cy="26" r="11" fill="#4ade80" />
                <line x1="60" y1="15" x2="60" y2="8" stroke="#4ade80" stroke-width="2" />
                <circle class="blink-fast" cx="60" cy="7" r="3" fill="#facc15" />
                <g class="blink">
                    <ellipse cx="55.5" cy="26" rx="2.4" ry="3.6" fill="#052e16" />
                    <ellipse cx="64.5" cy="26" rx="2.4" ry="3.6" fill="#052e16" />
                </g>
                <!-- saucer -->
                <ellipse cx="60" cy="36" rx="46" ry="12" fill="url(#saucer)" />
                <ellipse cx="60" cy="33" rx="46" ry="9" fill="#e2e8f0" fill-opacity="0.25" />
                <circle class="light l1" cx="28" cy="38" r="2.6" fill="#f472b6" />
                <circle class="light l2" cx="44" cy="42" r="2.6" fill="#facc15" />
                <circle class="light l3" cx="60" cy="44" r="2.6" fill="#7dd3fc" />
                <circle class="light l2" cx="76" cy="42" r="2.6" fill="#facc15" />
                <circle class="light l1" cx="92" cy="38" r="2.6" fill="#f472b6" />
            </svg>
        </div>

        <!-- Main content -->
        <main class="relative z-20 flex w-full max-w-3xl flex-col items-center text-center">
            <!-- 4 [planet] 4 -->
            <div
                class="digits relative flex items-center justify-center transition-transform duration-200 ease-out"
                :style="[{ fontSize: 'clamp(5.5rem, 21vw, 13rem)' }, layer(0.8)]"
                aria-label="404"
            >
                <span class="digit glitch font-black leading-none" data-text="4">4</span>

                <!-- ringed planet standing in for the zero -->
                <div class="planet relative mx-[0.02em]" style="width: 0.95em; height: 0.78em">
                    <svg viewBox="0 0 240 200" class="planet-spin h-full w-full overflow-visible" aria-hidden="true">
                        <defs>
                            <radialGradient id="pl" cx="0.35" cy="0.3" r="0.9">
                                <stop offset="0" stop-color="#a5b4fc" />
                                <stop offset="0.55" stop-color="#6366f1" />
                                <stop offset="1" stop-color="#1e1b4b" />
                            </radialGradient>
                            <clipPath id="ringFront" clipPathUnits="userSpaceOnUse">
                                <rect x="0" y="100" width="240" height="100" />
                            </clipPath>
                        </defs>
                        <!-- ring (back half) -->
                        <g transform="rotate(-18 120 100)">
                            <ellipse cx="120" cy="100" rx="112" ry="28" fill="none" stroke="#c7d2fe" stroke-opacity="0.55" stroke-width="7" />
                            <ellipse cx="120" cy="100" rx="96" ry="22" fill="none" stroke="#818cf8" stroke-opacity="0.45" stroke-width="3" />
                        </g>
                        <!-- planet -->
                        <circle cx="120" cy="100" r="62" fill="url(#pl)" />
                        <g fill="#312e81" fill-opacity="0.35">
                            <ellipse cx="96" cy="78" rx="12" ry="9" />
                            <ellipse cx="140" cy="118" rx="16" ry="11" />
                            <ellipse cx="104" cy="128" rx="7" ry="5" />
                            <ellipse cx="150" cy="80" rx="6" ry="4" />
                        </g>
                        <path d="M70 96 Q120 80 170 100" stroke="#e0e7ff" stroke-opacity="0.25" stroke-width="5" fill="none" />
                        <!-- ring (front half) -->
                        <g transform="rotate(-18 120 100)">
                            <g clip-path="url(#ringFront)">
                                <ellipse cx="120" cy="100" rx="112" ry="28" fill="none" stroke="#c7d2fe" stroke-opacity="0.85" stroke-width="7" />
                                <ellipse cx="120" cy="100" rx="96" ry="22" fill="none" stroke="#818cf8" stroke-opacity="0.7" stroke-width="3" />
                            </g>
                        </g>
                    </svg>

                    <!-- tiny moon orbiting -->
                    <div class="orbit pointer-events-none absolute inset-[-12%]">
                        <span class="moon absolute left-1/2 top-0 h-[0.1em] w-[0.1em] -translate-x-1/2 rounded-full bg-slate-200"></span>
                    </div>
                </div>

                <span class="digit glitch font-black leading-none" data-text="4">4</span>
            </div>

            <!-- Astronaut -->
            <div
                class="astro absolute -bottom-6 right-[2%] z-30 w-28 cursor-pointer transition-transform duration-200 ease-out sm:w-36 md:-bottom-10 md:right-[4%] md:w-44"
                :style="layer(1.4)"
                @click="spin"
            >
                <!-- speech bubble -->
                <div
                    :key="phraseIndex"
                    class="bubble absolute -top-9 left-1/2 whitespace-nowrap rounded-2xl border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-medium text-slate-100 backdrop-blur-md md:-top-11 md:text-sm"
                >
                    {{ phrases[phraseIndex] }}
                </div>

                <div :class="['astro-float', spinning && 'astro-spin']">
                    <svg viewBox="-20 0 200 230" class="w-full overflow-visible" aria-label="Lost astronaut">
                        <defs>
                            <linearGradient id="visor" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#1e3a8a" />
                                <stop offset="1" stop-color="#0b1230" />
                            </linearGradient>
                        </defs>

                        <!-- tether -->
                        <path class="tether" d="M52 120 C20 125 24 160 -14 170" fill="none" stroke="#94a3b8" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 5" />

                        <!-- backpack -->
                        <rect x="44" y="84" width="34" height="64" rx="10" fill="#94a3b8" />
                        <rect x="50" y="92" width="22" height="8" rx="3" fill="#64748b" />

                        <!-- legs -->
                        <g class="leg-a">
                            <rect x="70" y="150" width="22" height="42" rx="11" fill="#e2e8f0" />
                            <rect x="68" y="182" width="26" height="16" rx="8" fill="#64748b" />
                        </g>
                        <g class="leg-b">
                            <rect x="98" y="150" width="22" height="42" rx="11" fill="#f1f5f9" />
                            <rect x="96" y="182" width="26" height="16" rx="8" fill="#64748b" />
                        </g>

                        <!-- left arm (relaxed) -->
                        <g class="arm-l">
                            <rect x="52" y="100" width="18" height="44" rx="9" fill="#e2e8f0" />
                            <circle cx="61" cy="146" r="8" fill="#94a3b8" />
                        </g>

                        <!-- body -->
                        <rect x="64" y="88" width="62" height="70" rx="26" fill="#f1f5f9" />
                        <rect x="82" y="108" width="28" height="18" rx="5" fill="#1e293b" />
                        <circle class="light l1" cx="89" cy="117" r="3" fill="#4ade80" />
                        <circle class="light l2" cx="97" cy="117" r="3" fill="#facc15" />
                        <circle class="light l3" cx="105" cy="117" r="3" fill="#f87171" />

                        <!-- waving arm (calling for help) -->
                        <g class="arm-r">
                            <rect x="118" y="82" width="18" height="46" rx="9" fill="#f8fafc" />
                            <circle cx="127" cy="80" r="9" fill="#94a3b8" />
                        </g>

                        <!-- helmet -->
                        <circle cx="95" cy="62" r="42" fill="#f8fafc" />
                        <circle cx="95" cy="62" r="42" fill="none" stroke="#cbd5e1" stroke-width="3" />
                        <ellipse cx="98" cy="64" rx="31" ry="27" fill="url(#visor)" />
                        <ellipse cx="86" cy="52" rx="10" ry="5" fill="#fff" fill-opacity="0.35" transform="rotate(-25 86 52)" />
                        <!-- worried face -->
                        <g class="blink">
                            <circle cx="88" cy="66" r="4.5" fill="#7dd3fc" />
                            <circle cx="108" cy="66" r="4.5" fill="#7dd3fc" />
                        </g>
                        <path d="M91 80 Q98 74 105 80" fill="none" stroke="#7dd3fc" stroke-width="2.5" stroke-linecap="round" />

                        <!-- antenna -->
                        <line x1="124" y1="30" x2="134" y2="14" stroke="#94a3b8" stroke-width="3" stroke-linecap="round" />
                        <circle class="blink-fast" cx="135" cy="12" r="4" fill="#f87171" />
                    </svg>
                </div>
            </div>

            <!-- floating question marks -->
            <span class="qm qm-1 absolute left-[6%] top-[8%] text-2xl text-indigo-300/70">?</span>
            <span class="qm qm-2 absolute right-[14%] top-[-4%] text-xl text-sky-300/60">?</span>
            <span class="qm qm-3 absolute left-[22%] bottom-[12%] text-3xl text-pink-300/50">?</span>

            <h1 class="rise mt-8 text-3xl font-bold tracking-tight md:text-5xl" style="animation-delay: 0.15s">
                Lost in space
            </h1>
            <p class="rise mt-3 max-w-md text-slate-400" style="animation-delay: 0.3s">
                The page you're looking for drifted out of orbit. Let's get you
                back to base.
            </p>

            <div
                v-if="path"
                class="rise mt-5 max-w-full truncate rounded-full border border-white/10 bg-white/5 px-4 py-1.5 font-mono text-xs text-slate-400 backdrop-blur-md"
                style="animation-delay: 0.45s"
            >
                {{ path }}
            </div>

            <div class="rise mt-8 flex flex-wrap justify-center gap-3" style="animation-delay: 0.6s">
                <a
                    href="/"
                    class="rounded-lg border border-blue-500/30 bg-blue-500/20 px-6 py-2.5 text-sm font-medium text-blue-100 transition-colors hover:bg-blue-500/30"
                >
                    Take me home
                </a>
                <button
                    type="button"
                    @click="goBack"
                    class="rounded-lg border border-white/10 bg-white/5 px-6 py-2.5 text-sm text-slate-200 transition-colors hover:bg-white/10"
                >
                    Go back
                </button>
            </div>
        </main>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";

// ---------------------------------------------------------------- stars (seeded so the layout is stable)
let seed = 7;
const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
const stars = Array.from({ length: 80 }, () => ({
    x: +(rnd() * 100).toFixed(2),
    y: +(rnd() * 100).toFixed(2),
    s: +(1 + rnd() * 2.2).toFixed(2),
    d: +(rnd() * 4).toFixed(2),
    t: +(2 + rnd() * 3).toFixed(2),
}));

// ---------------------------------------------------------------- mouse parallax
const mouse = reactive({ x: 0, y: 0 });
const onMove = (e) => {
    mouse.x = (e.clientX / window.innerWidth - 0.5) * 2;
    mouse.y = (e.clientY / window.innerHeight - 0.5) * 2;
};
const layer = (depth) => ({
    transform: `translate3d(${(-mouse.x * depth * 18).toFixed(1)}px, ${(-mouse.y * depth * 18).toFixed(1)}px, 0)`,
});

// ---------------------------------------------------------------- astronaut speech + click spin
const phrases = ["Help!", "Where am I?", "Houston…?", "Is this a 404?", "Send coordinates!"];
const phraseIndex = ref(0);
const spinning = ref(false);
let phraseTimer = null;

const spin = () => {
    if (spinning.value) return;
    spinning.value = true;
    phraseIndex.value = 4;
    setTimeout(() => (spinning.value = false), 1200);
};

// ---------------------------------------------------------------- misc
const path = ref("");
const goBack = () => (window.history.length > 1 ? window.history.back() : (window.location.href = "/"));

onMounted(() => {
    path.value = window.location.pathname;
    phraseTimer = setInterval(() => {
        phraseIndex.value = (phraseIndex.value + 1) % phrases.length;
    }, 2800);
});
onBeforeUnmount(() => clearInterval(phraseTimer));
</script>

<style scoped>
.stage {
    background: radial-gradient(ellipse at 50% 0%, #131c4a 0%, #0b1230 45%, #060a1f 100%);
}
.nebula {
    background:
        radial-gradient(40rem 26rem at 15% 20%, rgba(99, 102, 241, 0.18), transparent 60%),
        radial-gradient(36rem 26rem at 85% 75%, rgba(236, 72, 153, 0.12), transparent 60%),
        radial-gradient(30rem 20rem at 60% 10%, rgba(56, 189, 248, 0.1), transparent 60%);
    animation: nebula 14s ease-in-out infinite alternate;
}

/* ---- digits ---- */
.digit {
    background: linear-gradient(to bottom, #ffffff, #64748b);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    animation: float 6s ease-in-out infinite;
}
.digit:last-child {
    animation-delay: -3s;
}
.glitch {
    position: relative;
}
.glitch::before,
.glitch::after {
    content: attr(data-text);
    position: absolute;
    inset: 0;
    -webkit-text-fill-color: currentColor;
    background: none;
    opacity: 0;
}
.glitch::before {
    color: #22d3ee;
    animation: glitch-a 5s infinite;
}
.glitch::after {
    color: #f472b6;
    animation: glitch-b 5s infinite;
}

/* ---- planet ---- */
.planet {
    animation: float 7s ease-in-out infinite -1.5s;
}
.planet-spin {
    animation: sway 9s ease-in-out infinite alternate;
}
.orbit {
    animation: spin 12s linear infinite;
}
.moon {
    box-shadow: 0 0 12px rgba(226, 232, 240, 0.7);
}

/* ---- astronaut ---- */
.astro-float {
    animation: astro-drift 7s ease-in-out infinite;
    transform-origin: 50% 50%;
}
.astro-spin {
    animation: astro-flip 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.arm-r {
    transform-origin: 127px 96px;
    animation: wave 1.1s ease-in-out infinite alternate;
}
.arm-l {
    transform-origin: 61px 106px;
    animation: arm-sway 3s ease-in-out infinite alternate;
}
.leg-a {
    transform-origin: 81px 152px;
    animation: kick 3.2s ease-in-out infinite alternate;
}
.leg-b {
    transform-origin: 109px 152px;
    animation: kick 3.2s ease-in-out infinite alternate-reverse;
}
.tether {
    animation: dash 2s linear infinite;
}
.bubble {
    transform: translateX(-50%);
    animation: pop 2.8s ease-in-out both;
}

/* ---- shared bits ---- */
.blink {
    transform-box: fill-box;
    transform-origin: center;
    animation: blink 4.5s infinite;
}
.blink-fast {
    animation: pulse 1s ease-in-out infinite;
}
.light {
    animation: pulse 1.4s ease-in-out infinite;
}
.l2 {
    animation-delay: 0.35s;
}
.l3 {
    animation-delay: 0.7s;
}
.star {
    opacity: 0.2;
    animation: twinkle 3s ease-in-out infinite;
}
.qm {
    font-weight: 700;
    animation: qm 5s ease-in-out infinite;
}
.qm-2 {
    animation-delay: -1.6s;
}
.qm-3 {
    animation-delay: -3.2s;
}
.rise {
    opacity: 0;
    animation: rise 0.7s ease-out forwards;
}

/* ---- ufo ---- */
.ufo {
    animation: ufo-fly 26s linear infinite;
}
.ufo-bob {
    animation: float 2.4s ease-in-out infinite;
}
.beam {
    animation: pulse 1.6s ease-in-out infinite;
}

/* ---- shooting stars ---- */
.shoot {
    position: absolute;
    height: 2px;
    width: 120px;
    border-radius: 9999px;
    background: linear-gradient(to right, rgba(255, 255, 255, 0), #fff);
    opacity: 0;
    transform: rotate(-35deg);
}
.shoot-1 {
    top: 12%;
    left: 70%;
    animation: shoot 7s ease-in infinite 1s;
}
.shoot-2 {
    top: 40%;
    left: 90%;
    animation: shoot 9s ease-in infinite 4s;
}
.shoot-3 {
    top: 6%;
    left: 40%;
    animation: shoot 11s ease-in infinite 7s;
}

/* ---------------------------------------------------------------- keyframes */
@keyframes twinkle {
    0%, 100% { opacity: 0.15; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.6); }
}
@keyframes nebula {
    from { transform: scale(1) translateX(0); }
    to { transform: scale(1.08) translateX(-12px); }
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-14px); }
}
@keyframes sway {
    from { transform: rotate(-4deg); }
    to { transform: rotate(4deg); }
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
@keyframes astro-drift {
    0% { transform: translate(0, 0) rotate(-6deg); }
    33% { transform: translate(-8px, -16px) rotate(4deg); }
    66% { transform: translate(6px, -6px) rotate(-2deg); }
    100% { transform: translate(0, 0) rotate(-6deg); }
}
@keyframes astro-flip {
    to { transform: rotate(720deg) scale(1.08); }
}
@keyframes wave {
    from { transform: rotate(-35deg); }
    to { transform: rotate(15deg); }
}
@keyframes arm-sway {
    from { transform: rotate(8deg); }
    to { transform: rotate(-10deg); }
}
@keyframes kick {
    from { transform: rotate(10deg); }
    to { transform: rotate(-12deg); }
}
@keyframes dash {
    to { stroke-dashoffset: -14; }
}
@keyframes blink {
    0%, 92%, 100% { transform: scaleY(1); }
    95% { transform: scaleY(0.1); }
}
@keyframes pulse {
    0%, 100% { opacity: 0.35; }
    50% { opacity: 1; }
}
@keyframes pop {
    0% { opacity: 0; transform: translateX(-50%) translateY(6px) scale(0.9); }
    12%, 82% { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
    100% { opacity: 0; transform: translateX(-50%) translateY(-4px) scale(0.98); }
}
@keyframes qm {
    0%, 100% { transform: translateY(0) rotate(-8deg); opacity: 0.5; }
    50% { transform: translateY(-18px) rotate(10deg); opacity: 1; }
}
@keyframes rise {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes ufo-fly {
    0% { transform: translateX(-20vw) translateY(0); }
    50% { transform: translateX(55vw) translateY(40px); }
    100% { transform: translateX(120vw) translateY(-10px); }
}
@keyframes shoot {
    0% { opacity: 0; transform: rotate(-35deg) translateX(0); }
    4% { opacity: 1; }
    14% { opacity: 0; transform: rotate(-35deg) translateX(-420px); }
    100% { opacity: 0; transform: rotate(-35deg) translateX(-420px); }
}
@keyframes glitch-a {
    0%, 90%, 100% { opacity: 0; transform: translate(0); }
    92% { opacity: 0.8; transform: translate(-4px, 1px); clip-path: inset(10% 0 55% 0); }
    95% { opacity: 0.8; transform: translate(3px, -2px); clip-path: inset(60% 0 8% 0); }
}
@keyframes glitch-b {
    0%, 91%, 100% { opacity: 0; transform: translate(0); }
    93% { opacity: 0.7; transform: translate(4px, -1px); clip-path: inset(45% 0 25% 0); }
    96% { opacity: 0.7; transform: translate(-3px, 2px); clip-path: inset(5% 0 70% 0); }
}

/* be kind to people who ask for less motion */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.001ms !important;
        animation-iteration-count: 1 !important;
    }
    .rise {
        opacity: 1;
    }
    .ufo,
    .shoot {
        display: none;
    }
}
</style>