<template>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1">{{ label }}</label>
        <slot />
        <p v-if="hint && !error" class="text-xs text-slate-500 mt-1">{{ hint }}</p>
        <p v-if="error" class="text-red-400 text-sm mt-1">{{ error }}</p>
    </div>
</template>

<script setup>
defineProps({ label: String, error: String, hint: String })
</script>
