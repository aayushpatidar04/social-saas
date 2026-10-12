<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // Instagram connected through a Facebook Page points at that Page's row.
            $table->foreignId('parent_account_id')->nullable()->after('user_id')
                ->constrained('social_accounts')->nullOnDelete();
            // The app (platform-wide or organization-owned) that issued the token.
            $table->foreignId('credential_id')->nullable()->after('parent_account_id')
                ->constrained('platform_credentials')->nullOnDelete();
            // facebook_login | instagram_login | oauth
            $table->string('auth_mode', 30)->nullable()->after('platform');
            $table->timestamp('webhook_subscribed_at')->nullable()->after('last_synced_at');
            $table->timestamp('token_refreshed_at')->nullable()->after('token_expires_at');

            // Long-lived tokens + encryption overhead do not fit in varchar(255).
            $table->text('access_token')->change();
            $table->text('refresh_token')->nullable()->change();
            $table->text('error_message')->nullable()->change();
        });

        // The same external account id may legitimately exist on two platforms,
        // but one platform account can only belong to one organization.
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropUnique(['platform_account_id']);
            $table->unique(['platform', 'platform_account_id']);
        });

        $this->backfillConnectionInfo();
        $this->encryptExistingTokens();
    }

    /** Everything that exists today was connected through Facebook or a plain OAuth app. */
    private function backfillConnectionInfo(): void
    {
        DB::table('social_accounts')->whereIn('platform', ['facebook', 'instagram'])
            ->update(['auth_mode' => 'facebook_login']);
        DB::table('social_accounts')->whereIn('platform', ['youtube', 'twitter', 'linkedin'])
            ->update(['auth_mode' => 'oauth']);

        DB::table('social_accounts')->where('platform', 'instagram')->orderBy('id')->each(function ($row) {
            $meta = json_decode($row->metadata ?? '[]', true) ?: [];
            if (! empty($meta['linked_to_facebook'])) {
                DB::table('social_accounts')->where('id', $row->id)
                    ->update(['parent_account_id' => $meta['linked_to_facebook']]);
            }
        });
    }

    /**
     * Tokens used to be stored as plain text (the old `->encrypted()` column
     * modifier does not exist in Laravel and was silently ignored).
     * The model now casts them with `encrypted`, so convert what is already there.
     */
    private function encryptExistingTokens(): void
    {
        DB::table('social_accounts')->orderBy('id')->each(function ($row) {
            $updates = [];
            foreach (['access_token', 'refresh_token'] as $column) {
                $value = $row->{$column};
                if ($value === null || $value === '' || $this->isEncrypted($value)) {
                    continue;
                }
                $updates[$column] = Crypt::encryptString($value);
            }
            if ($updates) {
                DB::table('social_accounts')->where('id', $row->id)->update($updates);
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function down(): void
    {
        // Decrypt tokens again so a rollback leaves a usable table.
        DB::table('social_accounts')->orderBy('id')->each(function ($row) {
            $updates = [];
            foreach (['access_token', 'refresh_token'] as $column) {
                $value = $row->{$column};
                if ($value && $this->isEncrypted($value)) {
                    $updates[$column] = Crypt::decryptString($value);
                }
            }
            if ($updates) {
                DB::table('social_accounts')->where('id', $row->id)->update($updates);
            }
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropUnique(['platform', 'platform_account_id']);
            $table->unique('platform_account_id');
            $table->dropConstrainedForeignId('parent_account_id');
            $table->dropConstrainedForeignId('credential_id');
            $table->dropColumn(['auth_mode', 'webhook_subscribed_at', 'token_refreshed_at']);
        });
    }
};
