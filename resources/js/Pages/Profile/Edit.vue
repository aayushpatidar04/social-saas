<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DeleteUserForm from "./Partials/DeleteUserForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

// shared card token (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md sm:p-8";
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                Profile
            </h1>
            <p class="mt-1 text-slate-400">
                Manage your account details, password and security
            </p>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div :class="card">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div :class="card">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <!-- danger zone -->
                <div
                    class="rounded-2xl border border-red-500/20 bg-red-500/[0.04] p-5 backdrop-blur-md sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>