<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMetaWebhook;
use App\Models\PlatformCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Meta webhooks (Facebook Page feed + Instagram comments).
 *
 *   /webhooks/meta/{key}  one URL per Meta app. The key identifies the app whose
 *                         verify token and app secret apply. The platform-wide app
 *                         shows its URL (with key) in the super admin screen.
 *   /webhooks/meta        legacy URL, served by the platform-wide app only.
 *
 * Every POST must carry a valid X-Hub-Signature-256 (HMAC-SHA256 of the raw body
 * with an app secret of that Meta app) - otherwise anyone could inject comments.
 */
class MetaWebhookController extends Controller
{
    public function handle(Request $request, ?string $key = null)
    {
        $credential = $this->credential($key);

        if ($request->isMethod('get')) {
            return $this->verify($request, $credential);
        }

        if (! $credential || ! $this->signatureIsValid($request, $credential)) {
            Log::warning('Meta webhook rejected: bad or missing signature', ['key' => $key ? substr($key, 0, 6).'…' : null]);

            return response()->json(['error' => 'invalid signature'], 403);
        }

        $credential->forceFill(['last_webhook_at' => now()])->saveQuietly();
        \Log::info('Meta webhook received', ['key' => $key ? substr($key, 0, 6).'…' : null]);
        ProcessMetaWebhook::dispatch($request->json()->all() ?: $request->all());

        return response()->json(['success' => true]);
    }

    private function credential(?string $key): ?PlatformCredential
    {
        $query = PlatformCredential::where('provider', 'meta')->where('is_active', true);

        return $key
            ? $query->where('webhook_key', $key)->first()
            : $query->where('scope_key', 'platform')->first();
    }

    private function verify(Request $request, ?PlatformCredential $credential)
    {
        $expected = (string) $credential?->get('webhook_verify_token', '');
        $given = (string) $request->query('hub_mode') === 'subscribe' ? (string) $request->query('hub_verify_token') : '';

        if ($credential && $expected !== '' && hash_equals($expected, $given)) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        Log::warning('Meta webhook verification failed');

        return response('Verification failed', 403);
    }

    private function signatureIsValid(Request $request, PlatformCredential $credential): bool
    {
        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }
        $given = substr($header, 7);

        // The Facebook and Instagram products of one Meta app have different secrets;
        // a payload may be signed with either.
        foreach ($credential->webhookSecrets() as $secret) {
            if (hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $given)) {
                return true;
            }
        }

        return false;
    }
}
