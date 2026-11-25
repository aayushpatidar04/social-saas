<?php

namespace App\Jobs;

use App\Models\SocialComment;
use App\Models\AiConversation;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateOllamaResponse implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 2;

    private SocialComment $comment;

    public function __construct(SocialComment $comment)
    {
        $this->comment = $comment;
    }

    public function handle()
    {
        try {
            Log::info('GenerateOllamaResponse started', [
                'comment_id' => $this->comment->id,
                'platform' => $this->comment->platform,
                'social_account_id' => $this->comment->social_account_id,
            ]);

            $account = $this->comment->socialAccount;

            // Only reply to comments since auto-reply was turned on
            // This uses auto_reply_started_at (the actual date), NOT a day window
            // so it correctly handles comments from any time period as long as
            // they came in after auto-reply was enabled
            if (!$account || !$account->auto_reply_started_at) {
                Log::warning('GenerateOllamaResponse skipped: Account not found or auto_reply_started_at not set', [
                    'comment_id' => $this->comment->id,
                    'account_id' => $account?->id,
                ]);
                return;
            }

            if (!$this->comment->commented_at || $this->comment->commented_at->lt($account->auto_reply_started_at)) {
                Log::info('GenerateOllamaResponse skipped: Commented before auto_reply_started_at', [
                    'comment_id' => $this->comment->id,
                    'commented_at' => $this->comment->commented_at?->toIso8601String(),
                    'auto_reply_started_at' => $account->auto_reply_started_at?->toIso8601String(),
                ]);
                return;
            }

            if ($this->comment->is_own_comment) {
                Log::info('GenerateOllamaResponse skipped: Own comment', [
                    'comment_id' => $this->comment->id,
                ]);
                return;
            }

            $service = new OllamaService();

            $conversationHistory = $this->buildConversationHistory($this->comment);

            $context = [
                'knowledge' => $this->getRelevantKnowledge($this->comment),
                'conversation_history' => $conversationHistory,
            ];

            Log::info('Generating Ollama response for comment', [
                'comment_id' => $this->comment->id,
                'platform' => $this->comment->platform,
                'organization_id' => $this->comment->organization_id,
            ]);

            $response = $service->generateResponse(
                $this->comment->organization_id,
                $this->comment,
                $context
            );

            if (empty($response)) {
                Log::warning('Empty response from AI', [
                    'comment_id' => $this->comment->id,
                ]);
                return;
            }

            Log::info('Ollama response generated successfully', [
                'comment_id' => $this->comment->id,
                'response_length' => strlen($response),
                'response_snippet' => substr($response, 0, 100),
            ]);

            $aiConversation = AiConversation::create([
                'original_comment' => $this->comment->content,
                'social_comment_id' => $this->comment->id,
                'social_account_id' => $this->comment->social_account_id,
                'ai_response' => $response,
                'response_status' => 'pending',
                'confidence_score' => $this->comment->intent_confidence ?? 80,
                'model_used' => 'ollama_gemma2',
            ]);

            $this->comment->update([
                'ai_response_text' => $response,
            ]);

            Log::info('Dispatching PublishAutoReply from GenerateOllamaResponse', [
                'comment_id' => $this->comment->id,
                'ai_conversation_id' => $aiConversation->id,
                'platform' => $this->comment->platform,
            ]);

            PublishAutoReply::dispatch($this->comment)->onConnection('sync');

        } catch (\Exception $e) {
            Log::error('Error generating response: ' . $e->getMessage(), [
                'comment_id' => $this->comment->id,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getRelevantKnowledge(SocialComment $comment): string
    {
        $post = $comment->socialPost;

        if (!$post) {
            return '';
        }

        $postSpecificKnowledge = KnowledgeChunk::whereHas('source.socialPosts', function ($q) use ($post) {
            $q->where('social_posts.id', $post->id)
                ->where('knowledge_source_social_post.is_active', true);
        })
            ->limit(5)
            ->pluck('content')
            ->join("\n\n");

        $globalKnowledge = KnowledgeChunk::whereHas('source', function ($q) use ($comment) {
            $q->where('organization_id', $comment->socialAccount->organization_id)
                ->where('scope', 'global')
                ->where('is_active', true);
        })
            ->limit(3)
            ->pluck('content')
            ->join("\n\n");

        return trim($postSpecificKnowledge . "\n\n" . $globalKnowledge);
    }

    private function buildConversationHistory(SocialComment $comment): string
    {
        $rootId = $comment->root_id ?: $comment->id;

        $messages = SocialComment::where('root_id', $rootId)
            ->where('id', '!=', $comment->id)
            ->orderBy('commented_at')
            ->limit(20)
            ->get();

        if ($messages->isEmpty()) {
            return 'No previous conversation history.';
        }

        $history = [];

        foreach ($messages as $message) {
            $role = $message->direction === 'outbound'
                ? 'Assistant'
                : 'Customer';

            $history[] = "{$role}: {$message->content}";
        }

        return implode("\n", $history);
    }
}
