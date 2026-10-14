<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const updatePassword = () => {
    form.put(route("password.update"), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset("password", "password_confirmation");
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset("current_password");
                currentPasswordInput.value.focus();
            }
        },
    });
};

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const label = "mb-1.5 block text-sm font-medium text-slate-300";

const field =
    "block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none";

const errorText = "mt-1.5 text-sm text-red-300";

const primaryBtn =
    "rounded-lg border border-blue-500/30 bg-blue-500/20 px-5 py-2 text-sm font-medium text-blue-100 transition-colors hover:bg-blue-500/30 disabled:cursor-not-allowed disabled:opacity-50";
</script>

<template>
    <section>
        <header>
            <h3 class="text-lg font-bold">Update password</h3>

            <p class="mt-1 text-sm text-slate-400">
                Ensure your account is using a long, random password to stay
                secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-5">
            <div>
                <label for="current_password" :class="label"
                    >Current password</label
                >

                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    :class="field"
                    autocomplete="current-password"
                />

                <p v-if="form.errors.current_password" :class="errorText">
                    {{ form.errors.current_password }}
                </p>
            </div>

            <div>
                <label for="password" :class="label">New password</label>

                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    :class="field"
                    autocomplete="new-password"
                />

                <p v-if="form.errors.password" :class="errorText">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation" :class="label"
                    >Confirm password</label
                >

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    :class="field"
                    autocomplete="new-password"
                />

                <p v-if="form.errors.password_confirmation" :class="errorText">
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

            <div class="flex items-center gap-4">
                <button :disabled="form.processing" :class="primaryBtn">
                    {{ form.processing ? "Saving…" : "Save" }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-emerald-300"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>