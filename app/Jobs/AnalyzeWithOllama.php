<?php

// app/Jobs/AnalyzeWithOllama.php - AI Analysis using Ollama

namespace App\Jobs;

use App\Models\SocialComment;
use App\Services\OllamaService;
use App\Services\LeadCaptureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeWithOllama implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(public SocialComment $comment)
    {
    }

    public function handle()
    {
        try {
            $service = new OllamaService();

            $analysisText = $this->comment->content;
            if (strlen($analysisText) > 1000) {
                $analysisText = substr($analysisText, 0, 1000);
            }

            // Run analysis calls
            $organizationId = $this->comment->organization_id;
            $sentimentAnalysis = $service->analyzeSentiment($organizationId, $analysisText);
            $intentAnalysis = $service->classifyIntent($organizationId, $analysisText);
            $leadAnalysis = $service->detectLead($organizationId, $analysisText);

            $analysis = [
                'sentiment' => $sentimentAnalysis['sentiment'],
                'sentiment_score' => $sentimentAnalysis['score'],
                'intent' => $intentAnalysis['intent'],
                'confidence' => $intentAnalysis['confidence'],
                'is_lead' => $leadAnalysis['is_lead'],
                'lead_score' => $leadAnalysis['lead_score'],
            ];

            // Update comment with analysis results
            $this->comment->update([
                'sentiment' => $analysis['sentiment'],
                'sentiment_score' => $analysis['sentiment_score'],
                'intent' => $analysis['intent'],
                'intent_confidence' => $analysis['confidence'],
                'lead_score' => $analysis['lead_score'],
                'is_lead' => $analysis['is_lead'],
                'ai_analysis_failed' => false,
                'ai_error_message' => null,
                'ai_analysis_completed_at' => now(),
            ]);

            app(LeadCaptureService::class)->capture($this->comment, $analysis);

            // Always generate AI response for the comment
            GenerateOllamaResponse::dispatch($this->comment)->onConnection('sync');

        } catch (\Exception $e) {
            Log::error('Ollama analysis failed: ' . $e->getMessage());

            $this->comment->update([
                'sentiment' => 'pending',
                'intent' => 'general',
                'lead_score' => 0,
                'is_lead' => false,
                'ai_analysis_failed' => true,
                'ai_error_message' => $e->getMessage(),
                'ai_analysis_completed_at' => now(),
            ]);
        }
    }

}
