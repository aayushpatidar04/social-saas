<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'saas:create-superadmin
                            {email : Login email}
                            {--name=Super Admin : Display name}
                            {--password= : Password (a random one is generated and printed if omitted)}';

    protected $description = 'Create a platform super admin (or promote/reset an existing user)';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        $user = User::withTrashed()->where('email', $email)->first() ?? new User(['email' => $email]);

        if ($user->exists && $user->organization_id && ! $this->confirm("{$email} belongs to an organization. Convert to a platform super admin?")) {
            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $user->exists ? $user->name : $this->option('name'),
            'password' => Hash::make($password),
            'organization_id' => null,
            'is_super_admin' => true,
            'is_active' => true,
            'role' => 'admin',
            'email_verified_at' => $user->email_verified_at ?? now(),
            'deleted_at' => null,
        ])->save();

        $this->info("Super admin ready: {$email}");
        if (! $this->option('password')) {
            $this->warn("Generated password: {$password}   (change it after the first login)");
        }

        return self::SUCCESS;
    }
}
