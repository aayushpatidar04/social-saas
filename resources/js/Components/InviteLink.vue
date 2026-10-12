<template>
    <div v-if="url" class="p-4 bg-blue-900/30 border border-blue-700 rounded-lg">
        <p class="text-sm text-blue-200 mb-2">
            Invitation link (valid 7 days). An email was sent if mail is configured; you can also share this link directly:
        </p>
        <div class="flex gap-2">
            <input :value="url" readonly @focus="$event.target.select()"
                class="flex-1 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-sm text-slate-200 font-mono" />
            <button type="button" @click="copy"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg">
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({ url: { type: String, default: null } })
const copied = ref(false)

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.url)
        copied.value = true
        setTimeout(() => (copied.value = false), 2000)
    } catch (e) { /* the field is selectable as a fallback */ }
}
</script>
