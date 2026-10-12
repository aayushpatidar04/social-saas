<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformCredential extends Model
{
    protected $fillable = [
        'organization_id', 'scope_key', 'provider', 'label', 'data', 'webhook_key',
        'is_active', 'last_verified_at', 'last_webhook_at', 'created_by',
    ];

    protected $casts = [
        'data' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_verified_at' => 'datetime',
        'last_webhook_at' => 'datetime',
    ];

    protected $attributes = ['is_active' => true];

    /** Never serialise secrets into Inertia props / JSON. */
    protected $hidden = ['data'];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            $c->scope_key = $c->organization_id ? (string) $c->organization_id : 'platform';
            $c->webhook_key = $c->webhook_key ?: Str::random(32);
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function schema(): array
    {
        return config("integrations.providers.{$this->provider}", []);
    }

    /** Stored value, falling back to the schema default. */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = ($this->data ?? [])[$key] ?? null;
        if ($value === null || $value === '') {
            $value = $this->schema()['fields'][$key]['default'] ?? $default;
        }

        return $value ?? $default;
    }

    public function isConfigured(): bool
    {
        $schema = $this->schema();

        if (! empty($schema['groups'])) {
            return collect($schema['groups'])->contains(fn ($g) => $this->hasAll($g));
        }

        return $this->hasAll($schema['required'] ?? []);
    }

    /** Can this app serve the given social platform (facebook / instagram / ...)? */
    public function supports(string $platform): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($platform) {
            'facebook' => $this->hasAll(['facebook_app_id', 'facebook_app_secret']),
            'instagram' => $this->hasAll(['instagram_app_id', 'instagram_app_secret']),
            default => $this->isConfigured(),
        };
    }

    private function hasAll(array $keys): bool
    {
        foreach ($keys as $k) {
            if (($this->data[$k] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    public function redirectUri(string $platform): string
    {
        $override = $this->get('redirect_uri');
        if ($override) {
            return $override;
        }

        return route(match ($platform) {
            'facebook' => 'auth.facebook.callback',
            'instagram' => 'auth.instagram.callback',
            default => $platform.'.callback',
        });
    }

    public function webhookUrl(): ?string
    {
        if (! ($this->schema()['has_webhook'] ?? false)) {
            return null;
        }

        return route('webhooks.meta', ['key' => $this->webhook_key]);
    }

    /** Every app secret that may sign a webhook for this credential. */
    public function webhookSecrets(): array
    {
        return array_values(array_filter([
            $this->get('facebook_app_secret'),
            $this->get('instagram_app_secret'),
        ]));
    }

    /** Safe representation for the UI: secrets are reported as set/unset only. */
    public function toPresentation(): array
    {
        $fields = [];
        foreach ($this->schema()['fields'] ?? [] as $key => $def) {
            $raw = ($this->data ?? [])[$key] ?? null;
            $fields[$key] = ! empty($def['secret'])
                ? ['secret' => true, 'set' => $raw !== null && $raw !== '']
                : ['secret' => false, 'value' => $raw ?? ''];
        }

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'configured' => $this->isConfigured(),
            'fields' => $fields,
            'webhook_url' => $this->webhookUrl(),
            'last_verified_at' => $this->last_verified_at,
            'last_webhook_at' => $this->last_webhook_at,
            'redirect_uris' => collect($this->schema()['platforms'] ?? [])
                ->mapWithKeys(fn ($p) => [$p => $this->redirectUri($p)])->all(),
        ];
    }
}
