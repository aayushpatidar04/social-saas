<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Models\SocialComment;
use App\Models\SocialPost;
use App\Services\FacebookService;
use App\Services\InstagramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMetaWebhook implements ShouldQueue
{
    use CascadeDeleteComments, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(private array $payload)
    {
    }

    public function handle()
    {

        foreach ($this->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? null;

                if ($field === 'feed') {
                    $this->handleFacebookFeed($entry, $change);
                }

                if ($field === 'comments') {
                    $this->handleInstagramComment($entry, $change);
                }
            }
        }
    }

    private function handleFacebookFeed(array $entry, array $change): void
    {
        $value = $change['value'] ?? [];

        $verb = $value['verb'] ?? null;

        if ($verb === 'add') {
            $this->handleFacebookFeedAdd($entry, $value);
        } elseif ($verb === 'remove') {
            $this->handleFacebookFeedRemove($entry, $value);
        }
    }

    private function handleFacebookFeedAdd(array $entry, array $value): void
    {
        $pageId = $entry['id'] ?? null;

        if (!$pageId) {
            return;
        }

        $account = $this->usable(SocialAccount::where('platform_account_id', $pageId)
            ->where('platform', 'facebook')
            ->where('is_active', true)
            ->first());

        if (!$account) {
            Log::warning('Facebook webhook account not found', [
                'page_id' => $pageId,
            ]);
            return;
        }

        $item = $value['item'] ?? null;

        if ($item === 'comment') {
            app(FacebookService::class)->syncSingleCommentFromWebhook($account, $value);
            return;
        }

        if (in_array($item, ['status', 'photo', 'video', 'post', 'share'], true)) {
            app(FacebookService::class)->syncSinglePostFromWebhook($account, $value);
            return;
        }

    }

    private function handleFacebookFeedRemove(array $entry, array $value): void
    {
        $item = $value['item'] ?? null;
        $pageId = $entry['id'] ?? null;

        if (!$pageId) {
            return;
        }

        $account = $this->usable(SocialAccount::where('platform_account_id', $pageId)
            ->where('platform', 'facebook')
            ->where('is_active', true)
            ->first());

        if (!$account) {
            Log::warning('Facebook webhook account not found for remove', [
                'page_id' => $pageId,
            ]);
            return;
        }

        if ($item === 'comment') {
            $commentId = $value['comment_id'] ?? null;

            if ($commentId) {
                $this->cascadeDeleteComment('facebook', $commentId);
            }
        }

        if (in_array($item, ['status', 'photo', 'video', 'post', 'share'], true)) {
            $postId = $value['post_id'] ?? $value['id'] ?? null;

            if ($postId) {
                $this->cascadeDeletePost('facebook', $postId);
            }
        }
    }

    private function handleInstagramComment(array $entry, array $change): void
    {
        $value = $change['value'] ?? [];

        $commentId = $value['id'] ?? null;

        if (!$commentId) {
            return;
        }

        $instagramAccountId = $entry['id'] ?? null;

        $account = $this->findInstagramAccount($instagramAccountId);

        if (!$account) {
            Log::warning('Instagram webhook account not found', [
                'instagram_account_id' => $instagramAccountId,
                'comment_id' => $commentId,
            ]);
            return;
        }

        // Fetch full comment data from Instagram Graph API
        $instagramService = app(InstagramService::class);
        $fullCommentData = $instagramService->fetchCommentData($account, $commentId);

        if (!$fullCommentData) {
            Log::warning('Could not fetch Instagram comment data', [
                'comment_id' => $commentId,
            ]);
            return;
        }

        // Build the value array that InstagramService.syncSingleCommentFromWebhook expects
        $instagramValue = [
            'comment_id' => $fullCommentData['id'] ?? $commentId,
            'post_id' => $fullCommentData['media_id'] ?? null,
            'parent_id' => $fullCommentData['parent_id'] ?? null,
            'text' => $fullCommentData['text'] ?? '',
            'from' => $fullCommentData['from'] ?? [],
            'timestamp' => $fullCommentData['timestamp'] ?? null,
            'media' => $fullCommentData['media'] ?? [],
        ];

        app(InstagramService::class)->syncSingleCommentFromWebhook($account, $instagramValue);
        
    }

    /**
     * The webhook `entry.id` is the Instagram professional account id, which is the
     * platform_account_id of an `instagram` account (however it was connected).
     * One indexed query - no calls to Meta.
     */
    private function findInstagramAccount(?string $instagramAccountId): ?SocialAccount
    {
        if (! $instagramAccountId) {
            return null;
        }

        $account = SocialAccount::where('platform', 'instagram')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('platform_account_id', $instagramAccountId)
                ->orWhere('metadata->app_scoped_id', $instagramAccountId))
            ->first();

        return $this->usable($account);
    }

    /** Ignore events for organizations that are suspended / expired. */
    private function usable(?SocialAccount $account): ?SocialAccount
    {
        if (! $account) {
            return null;
        }

        $org = \App\Models\Organization::find($account->organization_id);

        return $org && $org->isUsable() ? $account : null;
    }
}
