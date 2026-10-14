<template>
    <AppLayout>
        <Head title="Knowledge Sources" />

        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Knowledge Sources
                </h1>
                <p class="mt-1 text-slate-400">
                    Upload and link knowledge sources for AI replies
                </p>
            </div>

            <LoadingButton
                :loading="loading.upload"
                :loading-text="'Opening...'"
                :base-classes="primaryBtn"
                @click="showUploadModal = true"
            >
                Upload Source
            </LoadingButton>
        </div>

        <!-- Filters -->
        <section :class="[card, 'mb-6']">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="relative">
                    <MagnifyingGlassIcon
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
                    />
                    <input
                        v-model="filters.search"
                        @input="applyFilters"
                        placeholder="Search sources..."
                        :class="[field, 'pl-9']"
                    />
                </div>

                <select
                    v-model="filters.type"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Types</option>
                    <option value="pdf">PDF</option>
                    <option value="docx">DOCX</option>
                    <option value="faq">FAQ</option>
                    <option value="script">Script</option>
                    <option value="policy">Policy</option>
                    <option value="template">Template</option>
                    <option value="brochure">Brochure</option>
                </select>

                <select
                    v-model="filters.scope"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Scope</option>
                    <option value="global">Global</option>
                    <option value="post_specific">Post Specific</option>
                </select>

                <select
                    v-model="filters.indexed"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Status</option>
                    <option value="1">Indexed</option>
                    <option value="0">Not Indexed</option>
                </select>
            </div>
        </section>

        <!-- Sources -->
        <section
            v-if="sources.length === 0"
            :class="[card, 'py-12 text-center']"
        >
            <p class="text-slate-400">No knowledge sources found.</p>
        </section>

        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <article
                v-for="source in sources"
                :key="source.id"
                class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:border-white/20 hover:bg-white/10"
            >
                <div class="flex justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="truncate text-lg font-bold">
                            {{ source.name }}
                        </h3>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-400">
                            {{ source.description || "No description" }}
                        </p>
                    </div>

                    <span
                        :class="
                            source.is_indexed
                                ? 'bg-green-500/15 text-green-300'
                                : 'bg-yellow-500/15 text-yellow-300'
                        "
                        class="h-fit shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                    >
                        {{ source.is_indexed ? "Indexed" : "Not Indexed" }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div :class="inner">
                        <p class="text-xs text-slate-500">Type</p>
                        <p class="text-sm font-semibold uppercase tabular-nums">
                            {{ source.type }}
                        </p>
                    </div>
                    <div :class="inner">
                        <p class="text-xs text-slate-500">Scope</p>
                        <p class="truncate text-sm font-semibold capitalize">
                            {{ scopeLabel(source.scope) }}
                        </p>
                    </div>
                    <div :class="inner">
                        <p class="text-xs text-slate-500">Chunks</p>
                        <p class="text-sm font-semibold tabular-nums">
                            {{ source.total_chunks }}
                        </p>
                    </div>
                    <div :class="inner">
                        <p class="text-xs text-slate-500">File</p>
                        <p class="text-sm font-semibold tabular-nums">
                            {{ formatSize(source.file_size) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 border-t border-white/10 pt-4">
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-400"
                    >
                        Linked posts
                    </p>
                    <div v-if="source.social_posts?.length" class="space-y-2">
                        <div
                            v-for="post in source.social_posts"
                            :key="post.id"
                            :class="[inner, 'flex items-center justify-between gap-3']"
                        >
                            <span
                                class="line-clamp-1 min-w-0 text-sm text-slate-300"
                            >
                                <span
                                    class="mr-1 inline-block h-2 w-2 rounded-full"
                                    :class="platformColor(post.platform)"
                                ></span>
                                <span class="capitalize">{{
                                    post.platform
                                }}</span>
                                · {{ post.content || post.platform_post_id }}
                            </span>
                            <LoadingButton
                                :loading="
                                    loading.unlink[`${source.id}-${post.id}`]
                                "
                                :loading-text="'Removing...'"
                                base-classes="shrink-0 text-xs text-red-300 transition-colors hover:text-red-200"
                                @click="unlinkPost(source, post)"
                            >
                                Unlink
                            </LoadingButton>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500">
                        No posts linked.
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <LoadingButton
                        :loading="loading.link[source.id]"
                        :loading-text="'Opening...'"
                        :base-classes="primaryBtn"
                        @click="openLinkModal(source)"
                    >
                        Link Posts
                    </LoadingButton>

                    <LoadingButton
                        :loading="loading.reindex[source.id]"
                        :loading-text="'Re-indexing...'"
                        :base-classes="ghostBtn"
                        @click="reindexSource(source)"
                    >
                        Re-index
                    </LoadingButton>

                    <LoadingButton
                        :loading="loading.delete[source.id]"
                        :loading-text="'Deleting...'"
                        :base-classes="dangerBtn"
                        @click="deleteSource(source)"
                    >
                        Delete
                    </LoadingButton>
                </div>
            </article>
        </div>

        <!-- Upload Modal -->
        <div v-if="showUploadModal" :class="overlay">
            <div
                :class="[
                    modal,
                    'thin-scroll max-h-[90vh] max-w-2xl overflow-y-auto p-6',
                ]"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold">Upload Knowledge Source</h2>
                    <button @click="showUploadModal = false" :class="closeBtn">
                        ×
                    </button>
                </div>

                <form @submit.prevent="uploadSource" class="space-y-4">
                    <input
                        v-model="form.name"
                        required
                        placeholder="Source name"
                        :class="field"
                    />

                    <textarea
                        v-model="form.description"
                        placeholder="Description"
                        rows="3"
                        :class="field"
                    ></textarea>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <select v-model="form.type" required :class="field">
                            <option value="">Select Type</option>
                            <option value="pdf">PDF</option>
                            <option value="docx">DOCX</option>
                            <option value="faq">FAQ</option>
                            <option value="script">Script</option>
                            <option value="policy">Policy</option>
                            <option value="template">Template</option>
                            <option value="brochure">Brochure</option>
                        </select>

                        <select v-model="form.scope" required :class="field">
                            <option value="global">Global</option>
                            <option value="post_specific">Post Specific</option>
                        </select>
                    </div>

                    <div
                        v-if="form.scope === 'post_specific'"
                        class="space-y-2"
                    >
                        <p class="text-sm font-medium text-slate-300">
                            Select posts for this source
                        </p>

                        <input
                            v-model="postSearch"
                            type="text"
                            placeholder="Search posts by platform, ID, or content..."
                            :class="field"
                        />

                        <div
                            :class="[
                                inner,
                                'thin-scroll max-h-60 space-y-2 overflow-y-auto',
                            ]"
                        >
                            <label
                                v-for="post in filteredPostsForSource"
                                :key="post.id"
                                :class="[inner, rowLabel]"
                            >
                                <input
                                    type="checkbox"
                                    :value="post.id"
                                    v-model="form.post_ids"
                                    :class="checkbox"
                                />

                                <div class="min-w-0">
                                    <p class="text-sm">
                                        {{ post.platform }} ·
                                        {{ post.platform_post_id }}
                                    </p>
                                    <p
                                        class="line-clamp-2 text-xs text-slate-400"
                                    >
                                        {{ post.content || "No content" }}
                                    </p>
                                </div>
                            </label>

                            <p
                                v-if="filteredPostsForSource.length === 0"
                                class="py-4 text-center text-sm text-slate-500"
                            >
                                No posts found.
                            </p>
                        </div>

                        <p
                            v-if="form.post_ids.length === 0"
                            class="text-xs text-yellow-300"
                        >
                            Select at least one post for post-specific
                            knowledge.
                        </p>
                    </div>

                    <input
                        type="file"
                        @change="handleFile"
                        class="w-full rounded-lg border border-white/10 bg-white/5 p-1.5 text-sm text-slate-300 file:mr-4 file:rounded-md file:border-0 file:bg-blue-500/20 file:px-3 file:py-1.5 file:text-sm file:text-blue-100 hover:file:bg-blue-500/30"
                    />

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showUploadModal = false"
                            :class="ghostBtn"
                        >
                            Cancel
                        </button>
                        <LoadingButton
                            :loading="uploading"
                            :loading-text="'Uploading...'"
                            :base-classes="primaryBtn"
                            type="submit"
                            :disabled="
                                form.scope === 'post_specific' &&
                                form.post_ids.length === 0
                            "
                        >
                            Upload
                        </LoadingButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Link Modal -->
        <div v-if="linkingSource" :class="overlay">
            <div :class="[modal, 'max-w-3xl p-6']">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold">Link Posts</h2>
                    <button @click="linkingSource = null" :class="closeBtn">
                        ×
                    </button>
                </div>

                <div class="thin-scroll max-h-96 space-y-2 overflow-y-auto">
                    <label
                        v-for="post in posts"
                        :key="post.id"
                        :class="[inner, rowLabel]"
                    >
                        <input
                            type="checkbox"
                            :value="post.id"
                            v-model="selectedPostIds"
                            :class="checkbox"
                        />
                        <div class="min-w-0">
                            <p class="text-sm">
                                {{ post.platform }} ·
                                {{ post.platform_post_id }}
                            </p>
                            <p class="line-clamp-2 text-sm text-slate-400">
                                {{ post.content || "No content" }}
                            </p>
                        </div>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="linkingSource = null" :class="ghostBtn">
                        Cancel
                    </button>
                    <LoadingButton
                        :loading="loading.link[linkingSource?.id]"
                        :loading-text="'Saving...'"
                        :base-classes="primaryBtn"
                        @click="linkPosts"
                    >
                        Save Links
                    </LoadingButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { MagnifyingGlassIcon } from "@heroicons/vue/24/outline";
import AppLayout from "@/Layouts/AppLayout.vue";
import { toast } from "@/composables/useToast";
import LoadingButton from "@/Components/LoadingButton.vue";

const page = usePage();

// ---------------------------------------------------------------- shared style tokens (same as Analytics / Inbox / Social Posts)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const inner = "rounded-xl border border-white/10 bg-white/[0.03] p-3";

const rowLabel =
    "flex cursor-pointer items-start gap-3 transition-colors hover:bg-white/[0.07]";

const checkbox =
    "mt-1 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40";

const field =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none [&>option]:bg-[#0b1230]";

const primaryBtn =
    "rounded-lg border border-blue-500/30 bg-blue-500/20 px-3 py-1.5 text-sm text-blue-100 transition-colors hover:bg-blue-500/30";

const ghostBtn =
    "rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10";

const dangerBtn =
    "rounded-lg border border-red-500/30 bg-red-500/15 px-3 py-1.5 text-sm text-red-200 transition-colors hover:bg-red-500/25";

const overlay =
    "fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm";

const modal =
    "w-full rounded-2xl border border-white/10 bg-[#0b1230]/95 backdrop-blur-md";

const closeBtn =
    "rounded-lg px-1.5 text-2xl leading-none text-slate-400 transition-colors hover:bg-white/10 hover:text-white";

// ---------------------------------------------------------------- state
const sources = computed(
    () => page.props.sources?.data || page.props.sources || [],
);
const posts = computed(() => page.props.posts || []);
const currentFilters = computed(() => page.props.filters || {});

const showUploadModal = ref(false);
const linkingSource = ref(null);
const selectedPostIds = ref([]);
const uploading = ref(false);
const postSearch = ref("");

const filters = ref({
    search: currentFilters.value.search || "",
    type: currentFilters.value.type || "",
    scope: currentFilters.value.scope || "",
    indexed: currentFilters.value.indexed || "",
});

const form = ref({
    name: "",
    description: "",
    type: "",
    scope: "global",
    file: null,
    post_ids: [],
});

const loading = reactive({
    upload: false,
    delete: {},
    link: {},
    reindex: {},
    unlink: {}, // was missing: unlinkPost() and the template both read loading.unlink[...]
});

// ---------------------------------------------------------------- actions
const applyFilters = () => {
    router.get("/knowledge-sources", filters.value, {
        preserveState: true,
        replace: true,
    });
};

const handleFile = (event) => {
    form.value.file = event.target.files[0];
};

const uploadSource = () => {
    uploading.value = true;
    loading.upload = true;

    router.post("/knowledge-sources", form.value, {
        forceFormData: true,
        onSuccess: () => {
            showUploadModal.value = false;
            toast.success("Knowledge source uploaded successfully");
            form.value = {
                name: "",
                description: "",
                type: "",
                scope: "global",
                file: null,
                post_ids: [],
            };
        },
        onFinish: () => {
            uploading.value = false;
            loading.upload = false;
        },
    });
};

const openLinkModal = (source) => {
    linkingSource.value = source;
    selectedPostIds.value = source.social_posts?.map((post) => post.id) || [];
    loading.link[source.id] = true;
    setTimeout(() => {
        loading.link[source.id] = false;
    }, 300);
};

const linkPosts = () => {
    if (!linkingSource.value) return;

    // capture the id: onSuccess clears linkingSource before onFinish runs,
    // which used to leave the loading flag stuck on true
    const sourceId = linkingSource.value.id;
    loading.link[sourceId] = true;
    router.post(
        `/knowledge-sources/${sourceId}/link-posts`,
        {
            post_ids: selectedPostIds.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                linkingSource.value = null;
                toast.success("Posts linked successfully");
            },
            onFinish: () => {
                loading.link[sourceId] = false;
            },
        },
    );
};

const unlinkPost = (source, post) => {
    const key = `${source.id}-${post.id}`;
    loading.unlink[key] = true;
    router.delete(`/knowledge-sources/${source.id}/posts/${post.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Post unlinked");
        },
        onFinish: () => {
            loading.unlink[key] = false;
        },
    });
};

const reindexSource = (source) => {
    loading.reindex[source.id] = true;
    router.post(
        `/knowledge-sources/${source.id}/reindex`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Re-indexing started");
            },
            onFinish: () => {
                loading.reindex[source.id] = false;
            },
        },
    );
};

const deleteSource = (source) => {
    if (!confirm("Delete this knowledge source?")) return;
    loading.delete[source.id] = true;
    router.delete(`/knowledge-sources/${source.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Knowledge source deleted");
        },
        onFinish: () => {
            loading.delete[source.id] = false;
        },
    });
};

// ---------------------------------------------------------------- formatting
const formatSize = (bytes) => {
    if (!bytes) return "0 KB";
    return `${(bytes / 1024).toFixed(1)} KB`;
};

const scopeLabel = (scope) => (scope || "global").replaceAll("_", " ");

// same dot colors as the Analytics "By platform" bars
const platformColor = (p) =>
    ({
        facebook: "bg-blue-500",
        instagram: "bg-pink-500",
        youtube: "bg-red-500",
        twitter: "bg-slate-300",
        linkedin: "bg-sky-600",
    })[p] ?? "bg-slate-500";

const filteredPostsForSource = computed(() => {
    const search = postSearch.value.toLowerCase().trim();

    if (!search) {
        return posts.value;
    }

    return posts.value.filter((post) => {
        return (
            String(post.platform || "")
                .toLowerCase()
                .includes(search) ||
            String(post.platform_post_id || "")
                .toLowerCase()
                .includes(search) ||
            String(post.content || "")
                .toLowerCase()
                .includes(search)
        );
    });
});
</script>

<style scoped>
/* thin, dark-theme scrollbar */
.thin-scroll {
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
.thin-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.thin-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.thin-scroll::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
}
.thin-scroll::-webkit-scrollbar-thumb:hover {
    background-color: rgba(255, 255, 255, 0.35);
}
</style>