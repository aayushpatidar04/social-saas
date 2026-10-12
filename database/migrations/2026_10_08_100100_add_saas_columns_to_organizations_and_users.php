<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // NULL = fall back to the plan default in config/plans.php
            $table->unsignedInteger('max_users')->nullable()->after('plan_expires_at');
            $table->unsignedInteger('max_social_accounts')->nullable()->after('max_users');
            // May this organization register its own Meta/Google/X/LinkedIn app?
            $table->boolean('allow_custom_apps')->default(false)->after('max_social_accounts');
            $table->timestamp('suspended_at')->nullable()->after('is_active');
            $table->string('suspended_reason')->nullable()->after('suspended_at');
            $table->foreignId('created_by')->nullable()->after('suspended_reason')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            // Super admins belong to the platform, not to an organization.
            $table->foreignId('organization_id')->nullable()->change();
            $table->boolean('is_super_admin')->default(false)->after('role')->index();
        });
    }

    public function down(): void
    {
        // users.organization_id stays nullable on purpose: re-adding NOT NULL would
        // fail as soon as a super admin (no organization) exists.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn('is_super_admin');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'max_users', 'max_social_accounts', 'allow_custom_apps',
                'suspended_at', 'suspended_reason',
            ]);
        });
    }
};
