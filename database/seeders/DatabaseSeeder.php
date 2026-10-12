<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Production: nothing is seeded. Create the first super admin with
     *   php artisan saas:create-superadmin you@example.com
     * Local / testing: a demo organization with known passwords for development.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipped: demo data is only seeded in local/testing. Use "php artisan saas:create-superadmin".');

            return;
        }

        $organization = Organization::firstOrCreate(['slug' => 'demo'], [
            'name' => 'Demo Organization',
            'description' => 'Demo organization for local development',
            'plan' => 'professional',
            'is_active' => true,
            'allow_custom_apps' => true,
        ]);

        foreach ([['Admin User', 'admin@example.com', 'admin'], ['Manager User', 'manager@example.com', 'manager'], ['Team Member', 'member@example.com', 'team_member']] as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill(['organization_id' => $organization->id, 'name' => $name, 'role' => $role,
                'password' => Hash::make('password'), 'email_verified_at' => now(), 'is_active' => true])->save();
        }

        $super = User::firstOrNew(['email' => 'super@example.com']);
        $super->forceFill(['name' => 'Super Admin', 'organization_id' => null, 'is_super_admin' => true, 'role' => 'admin',
            'password' => Hash::make('password'), 'email_verified_at' => now(), 'is_active' => true])->save();

        $this->command?->info('Demo data ready (password for all: "password"): super@example.com, admin@example.com, manager@example.com, member@example.com');
    }
}
