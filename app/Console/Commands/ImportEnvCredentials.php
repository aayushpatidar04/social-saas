<?php

namespace App\Console\Commands;

use App\Services\Credentials\CredentialManager;
use Dotenv\Dotenv;
use Illuminate\Console\Command;

/**
 * One-off migration helper: copy the integration settings that used to live in
 * .env into the database as the platform-wide apps. Reads the .env FILE directly,
 * so it also works when the configuration is cached.
 */
class ImportEnvCredentials extends Command
{
    protected $signature = 'saas:import-env {--file= : Path to the .env to read (default: base .env)} {--force : Overwrite providers that already exist}';

    protected $description = 'Import integration credentials from .env into the database';

    /** provider => [credential field => ENV VAR] */
    private const MAP = [
        'meta' => [
            'facebook_app_id' => 'FACEBOOK_APP_ID',
            'facebook_app_secret' => 'FACEBOOK_APP_SECRET',
            'instagram_app_id' => 'INSTAGRAM_APP_ID',
            'instagram_app_secret' => 'INSTAGRAM_APP_SECRET',
            'webhook_verify_token' => 'META_WEBHOOK_VERIFY_TOKEN',
            'graph_version' => 'FACEBOOK_GRAPH_VERSION',
        ],
        'youtube' => ['client_id' => 'YOUTUBE_CLIENT_ID', 'client_secret' => 'YOUTUBE_CLIENT_SECRET', 'redirect_uri' => 'YOUTUBE_REDIRECT_URI'],
        'twitter' => ['client_id' => 'TWITTER_CLIENT_ID', 'client_secret' => 'TWITTER_CLIENT_SECRET', 'redirect_uri' => 'TWITTER_REDIRECT_URI'],
        'linkedin' => ['client_id' => 'LINKEDIN_CLIENT_ID', 'client_secret' => 'LINKEDIN_CLIENT_SECRET', 'redirect_uri' => 'LINKEDIN_REDIRECT_URI'],
        'openai' => ['api_key' => 'OPENAI_API_KEY'],
        'ollama' => ['base_url' => 'OLLAMA_URL', 'model' => 'OLLAMA_MODEL', 'timeout' => 'OLLAMA_TIMEOUT', 'embedding_model' => 'KB_EMBEDDING_MODEL'],
    ];

    public function handle(CredentialManager $manager): int
    {
        $file = $this->option('file') ?: base_path('.env');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $env = Dotenv::parse(file_get_contents($file));

        foreach (self::MAP as $provider => $map) {
            $fields = [];
            foreach ($map as $field => $var) {
                if (($env[$var] ?? '') !== '') {
                    $fields[$field] = $env[$var];
                }
            }

            if (! $fields) {
                $this->line("- {$provider}: nothing in .env, skipped");

                continue;
            }

            $exists = \App\Models\PlatformCredential::where('scope_key', 'platform')->where('provider', $provider)->exists();
            if ($exists && ! $this->option('force')) {
                $this->line("- {$provider}: already configured, skipped (use --force to overwrite)");

                continue;
            }

            try {
                $manager->save($provider, null, ['label' => 'Imported from .env', 'fields' => $fields]);
                $this->info("✓ {$provider}: imported ".count($fields).' value(s)');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->warn("! {$provider}: incomplete in .env (".collect($e->errors())->flatten()->implode(' ').')');
            }
        }

        $this->newLine();
        $this->comment('Next: open Admin → Integrations, press "Test", then remove these values from .env.');

        return self::SUCCESS;
    }
}
