<template>
    <AppLayout>
        <Head title="Social Posts" />

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                Social Posts
            </h1>
            <p class="mt-1 text-slate-400">
                Manage post-specific knowledge for AI replies
            </p>
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
                        placeholder="Search posts..."
                        :class="[field, 'pl-9']"
                    />
                </div>

                <select
                    v-model="filters.platform"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Platforms</option>
                    <option value="facebook">Facebook</option>
                    <option value="instagram">Instagram</option>
                    <option value="youtube">YouTube</option>
                    <option value="twitter">X/Twitter</option>
                    <option value="linkedin">LinkedIn</option>
                </select>

                <select
                    v-model="filters.has_knowledge"
                    @change="applyFilters"
                    :class="field"
                >
                    <option value="">All Posts</option>
                    <option value="1">With Knowledge</option>
                    <option value="0">Without Knowledge</option>
                </select>

                <button @click="applyFilters" :class="primaryBtn">Filter</button>
            </div>
        </section>

        <!-- Posts -->
        <section
            v-if="posts.length === 0"
            :class="[card, 'py-12 text-center']"
        >
            <p class="text-slate-400">No social posts found.</p>
        </section>

        <div v-else class="space-y-3">
            <article
                v-for="post in posts"
                :key="post.id"
                class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-md transition-all hover:border-white/20 hover:bg-white/10"
            >
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="mb-1 text-xs capitalize text-slate-400">
                            <span
                                class="mr-1 inline-block h-2 w-2 rounded-full"
                                :class="platformColor(post.platform)"
                            ></span>
                            {{ platformLabel(post.platform) }}
                            · {{ formatDate(post.posted_at) }}
                        </p>
                        <h3 class="line-clamp-2 text-lg font-semibold">
                            {{ post.content || "No caption/content" }}
                        </h3>
                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ post.platform_post_id }}
                        </p>
                    </div>

                    <span
                        class="h-fit shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            post.knowledge_sources?.length
                                ? 'bg-emerald-500/15 text-emerald-300'
                                : 'bg-slate-500/20 text-slate-300'
                        "
                    >
                        {{ post.knowledge_sources?.length || 0 }} sources
                    </span>
                </div>

                <div class="border-t border-white/10 pt-4">
                    <p
                        class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-400"
                    >
                        Linked knowledge
                    </p>

                    <div
                        v-if="post.knowledge_sources?.length"
                        class="grid grid-cols-1 gap-2 md:grid-cols-2"
                    >
                        <div
                            v-for="source in post.knowledge_sources"
                            :key="source.id"
                            :class="[inner, 'flex justify-between gap-3']"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ source.name }}
                                </p>
                                <p class="text-xs capitalize text-slate-500">
                                    {{ source.type }} ·
                                    {{ source.scope || "global" }}
                                </p>
                            </div>

                            <LoadingButton
                                :loading="
                                    loading.unlink[`${post.id}-${source.id}`]
                                "
                                :loading-text="'Removing...'"
                                base-classes="text-xs text-red-300 transition-colors hover:text-red-200"
                                @click="unlinkSource(post, source)"
                            >
                                Unlink
                            </LoadingButton>
                        </div>
                    </div>

                    <p v-else class="text-sm text-slate-500">
                        No knowledge source linked.
                    </p>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <LoadingButton
                        :loading="loading.attach[post.id]"
                        :loading-text="'Opening...'"
                        :base-classes="primaryBtn"
                        @click="openAttachModal(post)"
                    >
                        Attach Existing Source
                    </LoadingButton>

                    <LoadingButton
                        :loading="loading.upload[post.id]"
                        :loading-text="'Uploading...'"
                        :base-classes="successBtn"
                        @click="openUploadForPost(post)"
                    >
                        Upload Source For This Post
                    </LoadingButton>

                    <LoadingButton
                        :base-classes="ghostBtn"
                        @click="openPostDetail(post)"
                    >
                        View Details
                    </LoadingButton>
                </div>
            </article>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination.links?.length > 3"
            class="mt-8 flex flex-wrap justify-center gap-2"
        >
            <button
                v-for="link in pagination.links"
                :key="link.label"
                v-html="link.label"
                :disabled="!link.url"
                @click="goToPage(link.url)"
                :class="[
                    'rounded-lg border px-3 py-1.5 text-sm transition-colors',
                    link.active
                        ? 'border-blue-500/40 bg-blue-500/20 text-blue-200'
                        : 'border-white/10 bg-white/5 text-slate-300',
                    !link.url
                        ? 'cursor-not-allowed opacity-50'
                        : 'hover:bg-white/10',
                ]"
            />
        </div>

        <!-- Attach Existing Modal -->
        <div v-if="attachPost" :class="overlay">
            <div :class="[modal, 'max-w-3xl p-6']">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold">Attach Knowledge Source</h2>
                    <button @click="attachPost = null" :class="closeBtn">
                        ×
                    </button>
                </div>

                <div class="thin-scroll max-h-96 space-y-2 overflow-y-auto">
                    <label
                        v-for="source in sources"
                        :key="source.id"
                        :class="[
                            inner,
                            'flex cursor-pointer gap-3 transition-colors hover:bg-white/[0.07]',
                        ]"
                    >
                        <input
                            type="checkbox"
                            :value="source.id"
                            v-model="selectedSourceIds"
                            class="mt-1 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40"
                        />
                        <div class="min-w-0">
                            <p class="text-sm">{{ source.name }}</p>
                            <p class="text-xs capitalize text-slate-500">
                                {{ source.type }} ·
                                {{ source.scope || "global" }}
                            </p>
                        </div>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="attachPost = null" :class="ghostBtn">
                        Cancel
                    </button>
                    <LoadingButton
                        :loading="loading.attach[attachPost.id]"
                        :loading-text="'Attaching...'"
                        :base-classes="primaryBtn"
                        @click="attachSources"
                    >
                        Attach
                    </LoadingButton>
                </div>
            </div>
        </div>

        <!-- Upload For Post Modal -->
        <div v-if="uploadPost" :class="overlay">
            <div :class="[modal, 'max-w-2xl p-6']">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold">Upload Source For Post</h2>
                    <button @click="uploadPost = null" :class="closeBtn">
                        ×
                    </button>
                </div>

                <form @submit.prevent="uploadSourceForPost" class="space-y-4">
                    <input
                        v-model="uploadForm.name"
                        required
                        placeholder="Source name"
                        :class="field"
                    />

                    <textarea
                        v-model="uploadForm.description"
                        placeholder="Description"
                        rows="3"
                        :class="field"
                    ></textarea>

                    <select v-model="uploadForm.type" required :class="field">
                        <option value="">Select Type</option>
                        <option value="pdf">PDF</option>
                        <option value="docx">DOCX</option>
                        <option value="faq">FAQ</option>
                        <option value="script">Script</option>
                        <option value="policy">Policy</option>
                        <option value="template">Template</option>
                        <option value="brochure">Brochure</option>
                    </select>

                    <input
                        type="file"
                        @change="handleUploadFile"
                        class="w-full rounded-lg border border-white/10 bg-white/5 p-1.5 text-sm text-slate-300 file:mr-4 file:rounded-md file:border-0 file:bg-blue-500/20 file:px-3 file:py-1.5 file:text-sm file:text-blue-100 hover:file:bg-blue-500/30"
                    />

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="uploadPost = null"
                            :class="ghostBtn"
                        >
                            Cancel
                        </button>
                        <LoadingButton
                            :loading="loading.upload[uploadPost?.id]"
                            :loading-text="'Uploading...'"
                            :base-classes="successBtn"
                            type="submit"
                        >
                            Upload & Link
                        </LoadingButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="detailPost" :class="overlay" @click.self="detailPost = null">
            <div
                :class="[
                    modal,
                    'thin-scroll max-h-[90vh] max-w-4xl overflow-y-auto p-6',
                ]"
            >
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold">Post Detail</h2>
                        <p class="text-sm text-slate-400">
                            {{ platformLabel(detailPost.platform) }} ·
                            {{ detailPost.platform_post_id }}
                        </p>
                    </div>
                    <button @click="detailPost = null" :class="closeBtn">
                        ×
                    </button>
                </div>

                <div :class="[inner, 'mb-5 p-4']">
                    <p class="whitespace-pre-line text-slate-200">
                        {{ detailPost.content || "No content" }}
                    </p>
                </div>

                <h3 class="mb-3 text-lg font-bold">Linked knowledge sources</h3>
                <p
                    v-if="!detailPost.knowledge_sources?.length"
                    class="text-sm text-slate-500"
                >
                    No knowledge source linked.
                </p>
                <div v-else class="space-y-2">
                    <div
                        v-for="source in detailPost.knowledge_sources"
                        :key="source.id"
                        :class="inner"
                    >
                        <p>{{ source.name }}</p>
                        <p class="text-sm text-slate-400">
                            {{ source.description }}
                        </p>
                    </div>
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

// ---------------------------------------------------------------- shared style tokens (same as Analytics / Inbox)
const card =
    "rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-md";

const inner = "rounded-xl border border-white/10 bg-white/[0.03] p-3";

const field =
    "w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500/50 focus:bg-white/10 focus:outline-none [&>option]:bg-[#0b1230]";

const primaryBtn =
    "rounded-lg border border-blue-500/30 bg-blue-500/20 px-3 py-1.5 text-sm text-blue-100 transition-colors hover:bg-blue-500/30";

const successBtn =
    "rounded-lg border border-emerald-500/30 bg-emerald-500/15 px-3 py-1.5 text-sm text-emerald-200 transition-colors hover:bg-emerald-500/25";

const ghostBtn =
    "rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200 transition-colors hover:bg-white/10";

const overlay =
    "fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm";

const modal =
    "w-full rounded-2xl border border-white/10 bg-[#0b1230]/95 backdrop-blur-md";

const closeBtn =
    "rounded-lg px-1.5 text-2xl leading-none text-slate-400 transition-colors hover:bg-white/10 hover:text-white";

// ---------------------------------------------------------------- state
const posts = computed(() => page.props.posts.data || []);
const pagination = computed(() => page.props.posts);
const sources = computed(() => page.props.sources || []);
const currentFilters = computed(() => page.props.filters || {});

const loading = reactive({
    attach: {},
    upload: {},
    unlink: {},
});

const filters = ref({
    search: currentFilters.value.search || "",
    platform: currentFilters.value.platform || "",
    has_knowledge: currentFilters.value.has_knowledge || "",
});

const attachPost = ref(null);
const uploadPost = ref(null);
const detailPost = ref(null);
const selectedSourceIds = ref([]);

const uploadForm = ref({
    name: "",
    description: "",
    type: "",
    file: null,
});

// ---------------------------------------------------------------- actions
const applyFilters = () => {
    router.get("/social-posts", filters.value, {
        preserveState: true,
        replace: true,
    });
};

const openAttachModal = (post) => {
    attachPost.value = post;
    selectedSourceIds.value =
        post.knowledge_sources?.map((source) => source.id) || [];
    loading.attach[post.id] = true;
    setTimeout(() => {
        loading.attach[post.id] = false;
    }, 300);
};

const attachSources = () => {
    // capture the id: onSuccess clears attachPost before onFinish runs
    const postId = attachPost.value.id;
    loading.attach[postId] = true;
    router.post(
        `/social-posts/${postId}/knowledge-sources`,
        {
            source_ids: selectedSourceIds.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                attachPost.value = null;
                toast.success("Sources attached successfully");
            },
            onFinish: () => {
                loading.attach[postId] = false;
            },
        },
    );
};

const goToPage = (url) => {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

const unlinkSource = (post, source) => {
    const key = `${post.id}-${source.id}`;
    loading.unlink[key] = true;
    router.delete(`/social-posts/${post.id}/knowledge-sources/${source.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Source unlinked");
        },
        onFinish: () => {
            loading.unlink[key] = false;
        },
    });
};

const openUploadForPost = (post) => {
    uploadPost.value = post;
};

const handleUploadFile = (event) => {
    uploadForm.value.file = event.target.files[0];
};

const uploadSourceForPost = () => {
    // capture the id: onSuccess clears uploadPost before onFinish runs
    const postId = uploadPost.value.id;
    loading.upload[postId] = true;
    router.post(
        `/social-posts/${postId}/knowledge-sources/upload`,
        {
            ...uploadForm.value,
            scope: "post_specific",
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadPost.value = null;
                toast.success("Source uploaded & linked");
                uploadForm.value = {
                    name: "",
                    description: "",
                    type: "",
                    file: null,
                };
            },
            onFinish: () => {
                loading.upload[postId] = false;
            },
        },
    );
};

const openPostDetail = (post) => {
    detailPost.value = post;
};

// ---------------------------------------------------------------- formatting
const formatDate = (date) => {
    if (!date) return "";
    return (
        new Date(date).toLocaleDateString() +
        " " +
        new Date(date).toLocaleTimeString()
    );
};

const platformLabel = (platform) => {
    const labels = {
        facebook: "Facebook",
        instagram: "Instagram",
        youtube: "YouTube",
        twitter: "X/Twitter",
        linkedin: "LinkedIn",
    };

    return labels[platform] || platform;
};

// same dot colors as the Analytics "By platform" bars
const platformColor = (p) =>
    ({
        facebook: "bg-blue-500",
        instagram: "bg-pink-500",
        youtube: "bg-red-500",
        twitter: "bg-slate-300",
        linkedin: "bg-sky-600",
    })[p] ?? "bg-slate-500";
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