<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Organization;
use App\Models\SocialComment;
use Illuminate\Support\Facades\DB;

class LeadCaptureService
{
    private const VALID_INTENTS = [
        'sales',
        'support',
        'partnership',
        'feedback',
        'complaint',
        'lead',
        'general',
        'question',
    ];

    public function capture(SocialComment $comment, array $analysis): ?Lead
    {
        $authorId = trim((string) $comment->platform_author_id);
        $intent = strtolower(trim((string) ($analysis['intent'] ?? '')));
        $isLead = filter_var($analysis['is_lead'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (
            $authorId === ''
            || preg_match('/^unknown(?:$|[_\s-])/i', $authorId)
            || !in_array($intent, self::VALID_INTENTS, true)
            || (!$isLead && !in_array($intent, ['sales', 'support'], true))
        ) {
            return null;
        }

        $leadType = match ($intent) {
            'sales' => 'sales',
            'support' => 'support',
            'partnership' => 'partnership',
            'feedback', 'complaint' => 'feedback',
            default => 'other',
        };
        $organizationId = (int) $comment->organization_id;
        $score = (int) ($analysis['lead_score'] ?? 0);

        return DB::transaction(function () use ($comment, $authorId, $intent, $leadType, $organizationId, $score) {
            Organization::query()
                ->whereKey($organizationId)
                ->lockForUpdate()
                ->firstOrFail();

            $existingLead = Lead::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('platform_author_id', $authorId)
                ->whereHas('socialComment', fn ($query) => $query->where('platform', $comment->platform))
                ->latest('id')
                ->first();

            if (!$existingLead) {
                return Lead::create([
                    'organization_id' => $organizationId,
                    'social_comment_id' => $comment->id,
                    'platform_author_id' => $authorId,
                    'author_name' => $comment->author_name,
                    'author_profile_url' => $comment->author_profile_url ?: $comment->author_avatar_url,
                    'initial_message' => substr($comment->content, 0, 500),
                    'lead_type' => $leadType,
                    'lead_score' => $score,
                    'lead_status' => 'new',
                ]);
            }

            $timestamp = ($comment->commented_at ?? now())->toDateTimeString();
            $entry = "[{$timestamp}] status: {$existingLead->lead_status}; intent: {$intent}; "
                . "lead type: {$leadType}; score: {$score}: "
                . substr($comment->content, 0, 500);

            $existingLead->update([
                'social_comment_id' => $comment->id,
                'author_name' => $comment->author_name ?: $existingLead->author_name,
                'author_profile_url' => $comment->author_profile_url
                    ?: $comment->author_avatar_url
                    ?: $existingLead->author_profile_url,
                'lead_type' => $leadType,
                'lead_score' => max($existingLead->lead_score, $score),
                'notes' => trim(($existingLead->notes ? $existingLead->notes . "\n\n" : '') . $entry),
            ]);

            return $existingLead;
        });
    }
}
