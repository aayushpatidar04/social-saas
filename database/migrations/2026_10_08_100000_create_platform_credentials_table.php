<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration credentials stored in the database instead of .env.
 *
 *  - organization_id NULL  => platform-wide app, managed by the super admin and
 *                             shared by every organization that has no app of its own.
 *  - organization_id SET   => the organization's own app ("bring your own app"),
 *                             only honoured when organizations.allow_custom_apps is true.
 *
 * All values (client ids, secrets, verify tokens ...) live in one encrypted JSON
 * column so a provider can have any number of fields (Meta has two app id/secret pairs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            // 'platform' or the organization id. Gives us a real unique index even
            // though NULLs are distinct in unique indexes.
            $table->string('scope_key', 40);
            $table->string('provider', 30);          // meta | youtube | twitter | linkedin | openai | ollama
            $table->string('label')->nullable();
            $table->longText('data')->nullable();     // encrypted JSON (see PlatformCredential)
            $table->string('webhook_key', 40)->unique(); // path segment of /webhooks/meta/{key}
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope_key', 'provider']);
            $table->index(['organization_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
