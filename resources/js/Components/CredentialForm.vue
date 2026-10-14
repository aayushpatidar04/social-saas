<template>
    <section :class="card">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">{{ provider.label }}</h3>
                <p
                    v-if="provider.description"
                    class="mt-1 max-w-3xl text-sm text-slate-400"
                >
                    {{ provider.description }}
                </p>
            </div>
            <Badge :variant="status.variant">{{ status.label }}</Badge>
        </div>

        <!-- Values the owner must paste into the provider's dashboard -->
        <div v-if="registerUrls.length" :class="[inner, 'mb-5 space-y-3 p-4']">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                Register these URLs in the provider dashboard
            </p>
            <div
                v-for="u in registerUrls"
                :key="u.label"
                class="flex flex-wrap items-center gap-2 md:flex-nowrap"
            >
                <span class="w-48 shrink-0 text-xs text-slate-400">{{
                    u.label
                }}</span>
                <input
                    :value="u.url"
                    readonly
                    @focus="$event.target.select()"
                    class="min-w-0 flex-1 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 font-mono text-xs text-slate-200 focus:border-blue-500/50 focus:outline-none"
                />
                <button
                    type="button"
                    @click="copy(u.url)"
                    :class="ghostBtn"
                >
                    Copy
                </button>
            </div>
            <p
                v-if="provider.has_webhook && !provider.credential"
                class="text-xs text-slate-500"
            >
                The webhook URL appears here after the first save.
            </p>
        </div>

        <form @submit.prevent="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="field in provider.fields"
                    :key="field.key"
                    :class="field.key.endsWith('scopes') ? 'md:col-span-2' : ''"
                >
                    <label
                        :for="`${provider.key}-${field.key}`"
                        class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-300"
                    >
                        {{ field.label }}
                        <span
                            v-if="field.secret"
                            class="inline-flex items-center gap-1 text-xs font-normal text-slate-500"
                        >
                            <LockClosedIcon class="h-3.5 w-3.5" />
                            stored encrypted
                        </span>
                    </label>
                    <input
                        :id="`${provider.key}-${field.key}`"
                        v-model="form.fields[field.key]"
                        :type="field.secret ? 'password' : 'text'"
                        autocomplete="off"
                        :placeholder="placeholder(field)"
                        :class="input"
                    />
                    <p v-if="field.help" class="mt-1 text-xs text-slate-500">
                        {{ field.help }}
                    </p>
                    <p
                        v-if="form.errors[`fields.${field.key}`]"
                        class="mt-1 text-sm text-red-300"
                    >
                        {{ form.errors[`fields.${field.key}`] }}
                    </p>
                </div>
            </div>

            <p v-if="form.errors.fields" class="text-sm text-red-300">
                {{ form.errors.fields }}
            </p>

            <label
                v-if="provider.credential"
                class="flex items-center gap-2 text-sm text-slate-300"
            >
                <input
                    type="checkbox"
                    v-model="form.is_active"
                    class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40"
                />
                Active (uncheck to stop using these credentials without
                deleting them)
            </label>

            <div class="flex flex-wrap items-center gap-3 border-t border-white/10 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    :class="[primaryBtn, 'disabled:cursor-not-allowed disabled:opacity-50']"
                >
                    {{
                        form.processing
                            ? "Saving…"
                            : provider.credential
                              ? "Save changes"
                              : "Save credentials"
                    }}
                </button>
                <button
                    v-if="provider.credential"
                    type="button"
                    @click="test"
                    :disabled="testing"
                    :class="[ghostBtn, 'disabled:cursor-not-allowed disabled:opacity-50']"
                >
                    {{ testing ? "Testing…" : "Test connection" }}
                </button>
                <button
                    v-if="provider.credential"
                    type="button"
                    @click="remove"
                    :class="[dangerBtn, 'ml-auto']"
                >
                    Remove
                </button>
            </div>

            <div class="space-y-1">
                <p
                    v-if="provider.credential?.last_verified_at"
                    class="text-xs text-slate-500"
                >
                    Last verified
                    {{ new Date(provider.credential.last_verified_at).toLocaleString() }}
                </p>
                <p
                    v-if="provider.credential?.last_webhook_at"
                    class="text-xs text-slate-500"
                >
                    Last webhook received
                    {{ new Date(provider.credential.last_webhook_at).toLocaleString() }}
                </p>
            </div>
        </form>
    </section>
</template>

<script setup>
import { computed, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { LockClosedIcon } from "@heroicons/vue/24/outline";
import Badge from "@/Components/Badge.vue";
import { toast } from "@/composables/useToast";

const props = defineProps({
    provider: { type: Object, required: true },
    // route helpers: { update: '/x/meta', test: '/x/meta/test', destroy: '/x/meta' }
    urls: { type: Object, required: true },
});

// ---------------------------------------------------------------- shared style tokens (same as the other pages)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const inner = "rounded-xl border border-white/10 bg-white/[0.03]";

const input =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none";

const btnBase = "rounded-lg border px-4 py-2 text-sm transition-colors";
const primaryBtn = `${btnBase} border-blue-500/30 bg-blue-500/20 font-medium text-blue-100 hover:bg-blue-500/30`;
const ghostBtn = `${btnBase} border-white/10 bg-white/5 text-slate-200 hover:bg-white/10`;
const dangerBtn = `${btnBase} border-red-500/30 bg-red-500/15 text-red-200 hover:bg-red-500/25`;

// ---------------------------------------------------------------- state
const initial = () => {
    const out = {};
    for (const f of props.provider.fields) {
        const stored = props.provider.credential?.fields?.[f.key];
        out[f.key] = f.secret ? "" : (stored?.value ?? "");
    }
    return out;
};

const form = useForm({
    label: props.provider.credential?.label ?? null,
    is_active: props.provider.credential?.is_active ?? true,
    fields: initial(),
});

const testing = ref(false);

const placeholder = (field) => {
    if (field.secret && props.provider.credential?.fields?.[field.key]?.set)
        return "•••••••• saved — leave blank to keep";
    return field.default ? `default: ${field.default}` : "";
};

const status = computed(() => {
    const c = props.provider.credential;
    if (!c) return { label: "Not configured", variant: "info" };
    if (!c.is_active) return { label: "Inactive", variant: "warning" };
    return c.configured
        ? { label: "Configured", variant: "success" }
        : { label: "Incomplete", variant: "warning" };
});

const registerUrls = computed(() => {
    const out = [];
    const labels = {
        facebook: "Facebook Login redirect URI",
        instagram: "Instagram Login redirect URI",
    };
    const uris =
        props.provider.credential?.redirect_uris ??
        props.provider.callback_urls ??
        {};
    for (const [platform, url] of Object.entries(uris)) {
        out.push({ label: labels[platform] ?? "OAuth redirect URI", url });
    }
    if (props.provider.credential?.webhook_url) {
        out.push({
            label: "Webhook callback URL",
            url: props.provider.credential.webhook_url,
        });
    }
    return out;
});

// ---------------------------------------------------------------- actions
const save = () =>
    form.put(props.urls.update, {
        preserveScroll: true,
        onSuccess: () => {
            for (const f of props.provider.fields)
                if (f.secret) form.fields[f.key] = "";
        },
    });

const test = () => {
    testing.value = true;
    router.post(
        props.urls.test,
        {},
        { preserveScroll: true, onFinish: () => (testing.value = false) },
    );
};

const remove = () => {
    if (
        !confirm(
            `Remove the ${props.provider.label} credentials? Accounts connected through them keep working until their tokens expire.`,
        )
    )
        return;
    router.delete(props.urls.destroy, { preserveScroll: true });
};

const copy = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        toast.success("Copied");
    } catch (e) {
        /* select-and-copy still works */
    }
};
</script>