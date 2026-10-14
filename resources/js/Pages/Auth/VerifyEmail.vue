<template>
    <Head title="Email Verification" />

    <AuthShell
        icon="✉️"
        title="Verify your email"
        subtitle="One quick step before you get started."
    >
        <p class="mb-6 text-sm leading-relaxed text-slate-300">
            We've sent a verification link to your email address. Click it to
            activate your account. If it hasn't arrived, check your spam folder,
            or we can send another one.
        </p>

        <div
            v-if="verificationLinkSent"
            class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-900/30 p-3 text-sm text-emerald-200"
            role="status"
        >
            A new verification link has been sent to your email address.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white shadow-lg shadow-blue-600/30 transition-all hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-600 disabled:shadow-none"
            >
                <span v-if="!form.processing">Resend Verification Email</span>
                <span v-else>Sending...</span>
            </button>

            <p class="text-center text-sm">
                <button
                    type="button"
                    @click="logout"
                    class="text-slate-400 underline transition-colors hover:text-white"
                >
                    Log Out
                </button>
            </p>
        </form>

        <template #below>
            Wrong address, or no email at all? Contact your administrator.
        </template>
    </AuthShell>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AuthShell from "@/Components/AuthShell.vue";

const props = defineProps({
    status: { type: String, default: "" },
});

const form = useForm({});

const submit = () => {
    form.post("/email/verification-notification");
};

const logout = () => router.post("/logout");

const verificationLinkSent = computed(
    () => props.status === "verification-link-sent",
);
</script>
