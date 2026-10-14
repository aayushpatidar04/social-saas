<script setup>
import { onMounted, ref } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const label = "mb-1.5 block text-sm font-medium text-slate-300";

const field =
    "block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none";

const errorText = "mt-1.5 text-sm text-red-300";

const primaryBtn =
    "rounded-lg border border-blue-500/30 bg-blue-500/20 px-5 py-2 text-sm font-medium text-blue-100 transition-colors hover:bg-blue-500/30 disabled:cursor-not-allowed disabled:opacity-50";

// keep the old TextInput behaviour: focus the name field on mount
const nameInput = ref(null);
onMounted(() => nameInput.value?.focus());
</script>

<template>
    <section>
        <header>
            <h3 class="text-lg font-bold">Profile information</h3>

            <p class="mt-1 text-sm text-slate-400">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-5"
        >
            <div>
                <label for="name" :class="label">Name</label>

                <input
                    id="name"
                    ref="nameInput"
                    type="text"
                    :class="field"
                    v-model="form.name"
                    required
                    autocomplete="name"
                />

                <p v-if="form.errors.name" :class="errorText">
                    {{ form.errors.name }}
                </p>
            </div>

            <div>
                <label for="email" :class="label">Email</label>

                <input
                    id="email"
                    type="email"
                    :class="field"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <p v-if="form.errors.email" :class="errorText">
                    {{ form.errors.email }}
                </p>
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="rounded-xl border border-yellow-500/30 bg-yellow-500/10 p-4"
            >
                <p class="text-sm text-yellow-200">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-yellow-100 underline underline-offset-2 transition-colors hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-300"
                >
                    A new verification link has been sent to your email address.
                </div>
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