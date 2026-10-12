<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * social_posts, social_comments, ai_conversations and knowledge_chunks only knew
 * their organization indirectly (through social_accounts / knowledge_sources).
 * Several controllers and RAGService already query `organization_id` on them,
 * which failed because the column never existed. Add it, backfill it, and let
 * the models keep it filled (see App\Models\Concerns\BelongsToOrganization).
 */
return new class extends Migration
{
    private array $viaAccount = ['social_posts', 'social_comments', 'ai_conversations'];

    public function up(): void
    {
        foreach (array_merge($this->viaAccount, ['knowledge_chunks']) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            });
        }

        foreach ($this->viaAccount as $tableName) {
            DB::statement(
                "UPDATE {$tableName} SET organization_id = (
                    SELECT social_accounts.organization_id FROM social_accounts
                    WHERE social_accounts.id = {$tableName}.social_account_id
                )"
            );
        }

        DB::statement(
            'UPDATE knowledge_chunks SET organization_id = (
                SELECT knowledge_sources.organization_id FROM knowledge_sources
                WHERE knowledge_sources.id = knowledge_chunks.knowledge_source_id
            )'
        );
    }

    public function down(): void
    {
        foreach (array_merge($this->viaAccount, ['knowledge_chunks']) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }
};
