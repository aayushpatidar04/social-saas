<?php

namespace App\Services\Credentials;

use App\Models\PlatformCredential;
use App\Services\Meta\MetaApi;
use Illuminate\Support\Facades\Http;

/** Live check that stored credentials are accepted by the provider (where that is possible without a user login). */
class CredentialTester
{
    /** @return array{ok: bool, message: string} */
    public function test(PlatformCredential $c): array
    {
        try {
            $result = match ($c->provider) {
                'meta' => $this->meta($c),
                'openai' => $this->openai($c),
                'ollama' => $this->ollama($c),
                default => ['ok' => true, 'message' => 'Saved. This provider is verified the first time a user connects an account.'],
            };
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($result['ok']) {
            $c->forceFill(['last_verified_at' => now()])->saveQuietly();
        }

        return $result;
    }

    private function meta(PlatformCredential $c): array
    {
        if (! $c->supports('facebook')) {
            return ['ok' => true, 'message' => 'Only the Instagram pair is set. It cannot be checked without a login; connect an Instagram account to verify it.'];
        }

        // An "app access token" is simply {app-id}|{app-secret}.
        $r = Http::get(MetaApi::facebookBase($c).'/'.$c->get('facebook_app_id'), [
            'fields' => 'name',
            'access_token' => $c->get('facebook_app_id').'|'.$c->get('facebook_app_secret'),
        ]);

        return $r->successful() && $r->json('name')
            ? ['ok' => true, 'message' => "Meta accepted the credentials (app: {$r->json('name')})."]
            : ['ok' => false, 'message' => 'Meta rejected the credentials: '.MetaApi::errorMessage($r)];
    }

    private function openai(PlatformCredential $c): array
    {
        $r = Http::withToken($c->get('api_key'))->get('https://api.openai.com/v1/models');

        return $r->successful()
            ? ['ok' => true, 'message' => 'OpenAI accepted the API key.']
            : ['ok' => false, 'message' => 'OpenAI rejected the key: '.($r->json('error.message') ?? 'HTTP '.$r->status())];
    }

    private function ollama(PlatformCredential $c): array
    {
        $r = Http::timeout(10)->get(rtrim($c->get('base_url'), '/').'/api/tags');

        return $r->successful()
            ? ['ok' => true, 'message' => 'Ollama server reachable ('.count($r->json('models', [])).' models installed).']
            : ['ok' => false, 'message' => 'Ollama server answered HTTP '.$r->status()];
    }
}
