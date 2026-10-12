<?php

namespace App\Services\Credentials;

use App\Models\PlatformCredential;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Create / update credentials from a form, driven by config/integrations.php. */
class CredentialManager
{
    public function schema(string $provider): array
    {
        return config("integrations.providers.{$provider}")
            ?? throw ValidationException::withMessages(['provider' => 'Unknown provider.']);
    }

    public function rules(string $provider): array
    {
        $rules = ['label' => ['nullable', 'string', 'max:100'], 'is_active' => ['boolean']];
        foreach (array_keys($this->schema($provider)['fields']) as $key) {
            $rules["fields.{$key}"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    public function save(string $provider, ?int $organizationId, array $input, ?int $userId = null): PlatformCredential
    {
        $schema = $this->schema($provider);

        $credential = PlatformCredential::firstOrNew([
            'scope_key' => $organizationId ? (string) $organizationId : 'platform',
            'provider' => $provider,
        ]);

        $data = $credential->data ?? [];
        foreach ($schema['fields'] as $key => $def) {
            if (! array_key_exists($key, $input['fields'] ?? [])) {
                continue;
            }
            $value = trim((string) $input['fields'][$key]);

            if ($value === '') {
                // A blank secret means "keep what is stored"; a blank plain field clears it.
                if (empty($def['secret'])) {
                    unset($data[$key]);
                }

                continue;
            }
            $data[$key] = $value;
        }

        foreach ($schema['fields'] as $key => $def) {
            if (! empty($def['generate']) && empty($data[$key])) {
                $data[$key] = Str::random(32);
            }
        }

        $this->assertComplete($schema, $data);

        $credential->fill([
            'organization_id' => $organizationId,
            'label' => $input['label'] ?? $credential->label,
            'data' => $data,
            'is_active' => $input['is_active'] ?? ($credential->exists ? $credential->is_active : true),
        ]);
        if (! $credential->exists) {
            $credential->created_by = $userId;
        }
        $credential->save();

        return $credential;
    }

    private function assertComplete(array $schema, array $data): void
    {
        $filled = fn (array $keys) => collect($keys)->filter(fn ($k) => ($data[$k] ?? '') !== '')->count();

        if (! empty($schema['groups'])) {
            foreach ($schema['groups'] as $group) {
                $n = $filled($group);
                if ($n > 0 && $n < count($group)) {
                    throw ValidationException::withMessages([
                        'fields' => 'Fill in both values of each pair ('.implode(' + ', $group).') or leave both empty.',
                    ]);
                }
            }
            if (collect($schema['groups'])->every(fn ($g) => $filled($g) === 0)) {
                throw ValidationException::withMessages(['fields' => 'Fill in at least one complete App ID + secret pair.']);
            }

            return;
        }

        $missing = collect($schema['required'] ?? [])->filter(fn ($k) => ($data[$k] ?? '') === '');
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['fields' => 'Required: '.$missing->implode(', ')]);
        }
    }
}
