<?php

// app/Models/Organization.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if ($organization->ai_prompts === null) {
                $organization->ai_prompts = config('ai_prompts');
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'website',
        'plan',
        'plan_expires_at',
        'max_users',
        'max_social_accounts',
        'allow_custom_apps',
        'suspended_at',
        'suspended_reason',
        'created_by',
        'settings',
        'ai_prompts',
        'is_active'
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'settings' => 'array',
        'ai_prompts' => 'array',
        'is_active' => 'boolean',
        'allow_custom_apps' => 'boolean',
        'suspended_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function socialComments()
    {
        return $this->hasMany(SocialComment::class);
    }

    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }

    public function credentials()
    {
        return $this->hasMany(PlatformCredential::class);
    }

    public function invitations()
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /** Active, not suspended, plan not expired. */
    public function isUsable(): bool
    {
        return $this->is_active
            && $this->suspended_at === null
            && ($this->plan_expires_at === null || $this->plan_expires_at->isFuture());
    }

    public function unusableReason(): ?string
    {
        if ($this->suspended_at) {
            return $this->suspended_reason ?: 'This organization has been suspended.';
        }
        if (! $this->is_active) {
            return 'This organization is inactive.';
        }
        if ($this->plan_expires_at && $this->plan_expires_at->isPast()) {
            return 'This organization\'s plan has expired.';
        }

        return null;
    }

    /** Limit for 'users' or 'social_accounts'; null = unlimited. */
    public function limit(string $key): ?int
    {
        $override = $this->{'max_'.$key};

        return $override ?? config("plans.{$this->plan}.max_{$key}");
    }

    public function canAddUser(): bool
    {
        $max = $this->limit('users');
        if ($max === null) {
            return true;
        }
        $pending = $this->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->count();

        return ($this->users()->count() + $pending) < $max;
    }

    public function canAddSocialAccount(): bool
    {
        $max = $this->limit('social_accounts');

        return $max === null
            || $this->socialAccounts()->where('status', '!=', 'disconnected')->count() < $max;
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function knowledgeSources()
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}