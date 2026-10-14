<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="modelValue" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                @click.self="$emit('update:modelValue', false)"
            >
                <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#0b1230]/95 p-6 backdrop-blur-md transition-all" role="dialog" aria-modal="true">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/5 pb-4">
                        <h2 class="text-xl font-bold text-white">{{ title }}</h2>
                        <button 
                            @click="$emit('update:modelValue', false)" 
                            class="text-slate-400 hover:text-white transition-colors rounded-lg p-1 hover:bg-white/5"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="mt-4 text-sm text-slate-400">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        default: '',
    },
})

defineEmits(['update:modelValue'])
</script>
