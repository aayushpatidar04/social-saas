<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstagramAuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\FacebookAuthController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\KnowledgeSourceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LinkedInController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SocialPostController;
use App\Http\Controllers\TwitterController;
use App\Http\Controllers\YoutubeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ============================================
// PUBLIC ROUTES (No Auth Required)
// ============================================

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'time' => now()]);
});

// Accepting an emailed invitation (public, token-protected)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

// ============================================
// AUTHENTICATED ROUTES (Requires Login)
// ============================================

Route::middleware(['auth', 'verified'])->group(function () {
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])
                ->name('edit');
            Route::patch('/', [ProfileController::class, 'update'])
                ->name('update');
            Route::delete('/', [ProfileController::class, 'destroy'])
                ->name('destroy');
        });

    // Stop impersonating (the signed-in user is the tenant user here, so it sits outside /admin)
    Route::post('/impersonation/leave', [Admin\ImpersonationController::class, 'stop'])->name('impersonation.leave');

    // ============================================
    // SUPER ADMIN (platform owner)
    // ============================================
    Route::prefix('admin')->name('admin.')->middleware('superadmin')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/organizations', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/create', [Admin\OrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations', [Admin\OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations/{organization}', [Admin\OrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('/organizations/{organization}', [Admin\OrganizationController::class, 'update'])->name('organizations.update');
        Route::post('/organizations/{organization}/suspend', [Admin\OrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::post('/organizations/{organization}/activate', [Admin\OrganizationController::class, 'activate'])->name('organizations.activate');
        Route::post('/organizations/{organization}/invite', [Admin\OrganizationController::class, 'invite'])->name('organizations.invite');
        Route::delete('/organizations/{organization}', [Admin\OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::post('/organizations/{organization}/impersonate/{user}', [Admin\ImpersonationController::class, 'start'])->name('organizations.impersonate');

        Route::get('/credentials', [Admin\CredentialController::class, 'index'])->name('credentials.index');
        Route::put('/credentials/{provider}', [Admin\CredentialController::class, 'update'])->name('credentials.update');
        Route::delete('/credentials/{provider}', [Admin\CredentialController::class, 'destroy'])->name('credentials.destroy');
        Route::post('/credentials/{provider}/test', [Admin\CredentialController::class, 'test'])->name('credentials.test');
    });
});

// ============================================
// ORGANIZATION APP (requires an active organization)
// ============================================
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {

    // Connect Facebook Pages / Instagram accounts (login required: the account is attached to the signed-in organization)
    Route::get('/auth/facebook', [FacebookAuthController::class, 'login'])->name('auth.facebook');
    Route::get('/auth/facebook/callback', [FacebookAuthController::class, 'callback'])->name('auth.facebook.callback');
    Route::get('/auth/facebook/select', [FacebookAuthController::class, 'select'])->name('auth.facebook.select');
    Route::post('/auth/facebook/select', [FacebookAuthController::class, 'store'])->name('auth.facebook.store');

    Route::get('/auth/instagram', [InstagramAuthController::class, 'login'])->name('auth.instagram');
    Route::get('/auth/instagram/callback', [InstagramAuthController::class, 'callback'])->name('auth.instagram.callback');


    // ============================================
    // DASHBOARD
    // ============================================

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // ============================================
    // SOCIAL INBOX & COMMENTS
    // ============================================

    Route::prefix('inbox')->name('comments.')->group(function () {
        Route::get('/', [CommentController::class, 'inbox'])
            ->name('index');
        Route::get('/filter', [CommentController::class, 'filter'])
            ->name('filter');
        Route::get('/{comment}', [CommentController::class, 'show'])
            ->name('show');
        Route::post('/{comment}/reply', [CommentController::class, 'sendReply'])
            ->name('reply');
        Route::post('/{comment}/approve', [CommentController::class, 'approveAIResponse'])
            ->name('approve');
        Route::post('/{comment}/reject', [CommentController::class, 'rejectAIResponse'])
            ->name('reject');
        Route::post('/{comment}/mark-responded', [CommentController::class, 'markAsResponded'])
            ->name('mark-responded');
        Route::post('/{comment}/mark-reviewed', [CommentController::class, 'markAsReviewed'])
            ->name('mark-reviewed');
    });

    // ============================================
    // ANALYTICS
    // ============================================

    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('/metrics/{metricType}', [AnalyticsController::class, 'getMetrics'])
            ->name('metrics');
        Route::post('/export', [AnalyticsController::class, 'exportReport'])
            ->name('export');
    });

    // ============================================
    // LEADS MANAGEMENT
    // ============================================

    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/', [LeadController::class, 'index'])
            ->name('index');
        Route::get('/{lead}', [LeadController::class, 'show'])
            ->name('show');
        Route::post('/{lead}/assign', [LeadController::class, 'assign'])
            ->name('assign');
        Route::post('/{lead}/update-status', [LeadController::class, 'updateStatus'])
            ->name('update-status');
        Route::post('/{lead}/contact', [LeadController::class, 'logContact'])
            ->name('log-contact');
        Route::get('/filter', [LeadController::class, 'filter'])
            ->name('filter');
    });

    // ============================================
    // PROFILE SETTINGS
    // ============================================


    // ============================================
    // SETTINGS - SOCIAL ACCOUNTS
    // ============================================

    Route::prefix('settings/social-accounts')->name('settings.social-accounts.')->group(function () {
        Route::get('/', [SocialAccountController::class, 'index'])
            ->name('index');
        Route::post('/{account}/sync', [SocialAccountController::class, 'sync'])
            ->name('sync');
        Route::post('/{account}/disconnect', [SocialAccountController::class, 'disconnect'])
            ->name('disconnect');
        Route::post('/{account}/reconnect', [SocialAccountController::class, 'reconnect'])
            ->name('reconnect');
        Route::post('/{account}/test', [SocialAccountController::class, 'test'])
            ->name('test');
        Route::post('/{account}/resubscribe', [SocialAccountController::class, 'resubscribe'])
            ->name('resubscribe');
    });

    // ============================================
    // SETTINGS - TEAM
    // ============================================

    Route::prefix('settings/team')->name('settings.team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/invite', [TeamController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [TeamController::class, 'cancelInvitation'])->name('invitations.cancel');
        Route::patch('/{user}/role', [TeamController::class, 'updateRole'])->name('role');
        Route::delete('/{user}', [TeamController::class, 'remove'])->name('remove');
    });

    // ============================================
    // SETTINGS - ORGANIZATION
    // ============================================

    Route::prefix('settings/organization')->name('settings.organization.')->group(function () {
        Route::get('/', [OrganizationSettingsController::class, 'index'])->name('index');
        Route::patch('/', [OrganizationSettingsController::class, 'update'])->name('update');
    });

    // ============================================
    // SETTINGS - INTEGRATIONS (organization's own apps, if enabled by the platform)
    // ============================================

    Route::prefix('settings/integrations')->name('settings.integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::put('/{provider}', [IntegrationController::class, 'update'])->name('update');
        Route::delete('/{provider}', [IntegrationController::class, 'destroy'])->name('destroy');
        Route::post('/{provider}/test', [IntegrationController::class, 'test'])->name('test');
    });

    // ============================================
    // SETTINGS - API
    // ============================================

    Route::prefix('settings/api')->name('settings.api.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Settings/API');
        })->name('index');
        Route::post('/keys/generate', function () {
            // Generate API key logic here
        })->name('generate-key');
        Route::delete('/keys/{key}', function () {
            // Revoke API key logic here
        })->name('revoke-key');
    });

    // ============================================
    // SETTINGS - KNOWLEDGE BASE
    // ============================================

    Route::prefix('settings/knowledge-base')->name('settings.knowledge-base.')->group(function () {
        Route::get('/', [KnowledgeBaseController::class, 'index'])
            ->name('index');
        Route::post('/upload', [KnowledgeBaseController::class, 'upload'])
            ->name('upload');
        Route::delete('/{source}', [KnowledgeBaseController::class, 'delete'])
            ->name('delete');
    });

    // ============================================
    // YouTube OAUTH & SYNC ROUTES
    // ============================================

    Route::get('/auth/youtube/login', [YoutubeController::class, 'login'])
        ->name('youtube.login');
    Route::get('/auth/youtube/callback', [YoutubeController::class, 'callback'])
        ->name('youtube.callback');
    Route::post('/settings/social-accounts/{account}/youtube-sync', [YoutubeController::class, 'sync'])
        ->name('youtube.sync');
    Route::post('/youtube/comments/{comment}/reply', [YoutubeController::class, 'reply'])
        ->name('youtube.comment.reply');
    Route::post('/youtube/subscribe/{account}', [YoutubeController::class, 'subscribe'])
        ->name('youtube.subscribe');
    Route::post('/youtube/unsubscribe/{account}', [YoutubeController::class, 'unsubscribe'])
        ->name('youtube.unsubscribe');

    // ============================================
    // Twitter OAUTH & SYNC ROUTES
    // ============================================

    Route::get('/auth/twitter/login', [TwitterController::class, 'login'])->name('twitter.login');
    Route::get('/auth/twitter/callback', [TwitterController::class, 'callback'])->name('twitter.callback');
    Route::post('/settings/social-accounts/{account}/twitter-sync', [TwitterController::class, 'sync'])->name('twitter.sync');

    // ============================================
    // LinkedIn OAUTH & SYNC ROUTES
    // ============================================

    Route::get('/auth/linkedin/login', [LinkedInController::class, 'login'])->name('linkedin.login');
    Route::get('/auth/linkedin/callback', [LinkedInController::class, 'callback'])->name('linkedin.callback');
    Route::post('/settings/social-accounts/{account}/linkedin-sync', [LinkedInController::class, 'sync'])->name('linkedin.sync');
    Route::post('/linkedin/comments/{comment}/reply', [LinkedInController::class, 'reply'])->name('linkedin.comment.reply');

    Route::get('/knowledge-sources', [KnowledgeSourceController::class, 'index'])
        ->name('knowledge-sources.index');

    Route::post('/knowledge-sources', [KnowledgeSourceController::class, 'store'])
        ->name('knowledge-sources.store');

    Route::post('/knowledge-sources/{source}/link-posts', [KnowledgeSourceController::class, 'linkPosts'])
        ->name('knowledge-sources.link-posts');

    Route::delete('/knowledge-sources/{source}/posts/{post}', [KnowledgeSourceController::class, 'unlinkPost'])
        ->name('knowledge-sources.unlink-post');

    Route::post('/knowledge-sources/{source}/reindex', [KnowledgeSourceController::class, 'reindex'])
        ->name('knowledge-sources.reindex');

    Route::delete('/knowledge-sources/{source}', [KnowledgeSourceController::class, 'destroy'])
        ->name('knowledge-sources.destroy');

    Route::get('/social-posts', [SocialPostController::class, 'index'])
        ->name('social-posts.index');

    Route::post('/social-posts/{post}/knowledge-sources', [SocialPostController::class, 'attachKnowledgeSources'])
        ->name('social-posts.attach-knowledge');

    Route::delete('/social-posts/{post}/knowledge-sources/{source}', [SocialPostController::class, 'unlinkKnowledgeSource'])
        ->name('social-posts.unlink-knowledge');

    Route::post('/social-posts/{post}/knowledge-sources/upload', [SocialPostController::class, 'uploadKnowledgeSource'])
        ->name('social-posts.upload-knowledge');
});

// ============================================
// 404 & FALLBACK
// ============================================

Route::fallback(function () {
    // render the page, but answer with a real 404 (it used to be 200)
    return Inertia::render('404')->toResponse(request())->setStatusCode(404);
});

Route::match(['get', 'post'], '/webhooks/youtube', [App\Http\Controllers\YoutubeWebhookController::class, 'handle']);
Route::match(['get', 'post'], '/webhooks/linkedin', [App\Http\Controllers\LinkedInWebhookController::class, 'handle']);
Route::match(['get', 'post'], '/webhooks/meta/{key?}', [MetaWebhookController::class, 'handle'])->name('webhooks.meta');

require __DIR__ . '/auth.php';
