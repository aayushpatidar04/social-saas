<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('ai_prompts')->nullable()->after('settings');
        });

        DB::table('organizations')->whereNull('ai_prompts')->update([
            'ai_prompts' => json_encode(config('ai_prompts'), JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('ai_prompts');
        });
    }
};
