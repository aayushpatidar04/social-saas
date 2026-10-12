import { onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { toast } from '@/composables/useToast'

/**
 * Shows the server-side flash messages (->with('success'|'error', ...)) as toasts.
 * They arrive on redirects, including full page loads after an OAuth round-trip,
 * so the initial value is shown once the layout (and its ToastContainer) is mounted.
 */
export function useFlashToasts() {
    const page = usePage()

    const show = (flash) => {
        if (flash?.success) toast.success(flash.success, 6000)
        if (flash?.error) toast.error(flash.error, 10000)
    }

    onMounted(() => show(page.props.flash))
    watch(() => page.props.flash, show)
}
