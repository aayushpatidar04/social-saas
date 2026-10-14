<script setup>
import { useForm } from "@inertiajs/vue3";
import { nextTick, ref } from "vue";

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: "",
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route("profile.destroy"), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const field =
    "block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-red-500/50 focus:bg-white/10 focus:outline-none";

const btnBase = "rounded-lg border px-4 py-2 text-sm transition-colors";
const ghostBtn = `${btnBase} border-white/10 bg-white/5 text-slate-200 hover:bg-white/10`;
const dangerBtn = `${btnBase} border-red-500/30 bg-red-600 font-medium text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50`;
</script>

<template>
    <section class="space-y-5">
        <header>
            <h3 class="text-lg font-bold">Delete account</h3>

            <p class="mt-1 text-sm text-slate-400">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <button type="button" :class="dangerBtn" @click="confirmUserDeletion">
            Delete account
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="confirmingUserDeletion"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                    @click.self="closeModal"
                >
                    <div
                        class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#0b1230]/95 p-6 backdrop-blur-md"
                        role="dialog"
                        aria-modal="true"
                    >
                        <h3 class="text-lg font-bold text-red-500">
                            Are you sure you want to delete your account?
                        </h3>
                        <p class="mt-1 text-sm text-slate-400">
                            Once your account is deleted, all of its resources
                            and data will be permanently deleted. Please enter
                            your password to confirm you would like to
                            permanently delete your account.
                        </p>
                        <div class="mt-6">
                            <label for="password" class="sr-only"
                                >Password</label
                            >
                            <input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                :class="[field, 'sm:w-3/4']"
                                placeholder="Password"
                                @keyup.enter="deleteUser"
                            />
                            <p
                                v-if="form.errors.password"
                                class="mt-1.5 text-sm text-red-300"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                :class="ghostBtn"
                                @click="closeModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                :class="dangerBtn"
                                :disabled="form.processing"
                                @click="deleteUser"
                            >
                                {{
                                    form.processing
                                        ? "Deleting…"
                                        : "Delete account"
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>
