<?php

// app/Models/SocialAccount.php
namespace App\Models;
 
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Encryption\Encrypter;
 
class SocialAccount extends Model
{
    use SoftDeletes, BelongsToOrganization;
 
    protected $fillable = [
        'organization_id', 'user_id', 'platform', 'platform_account_id',
        'platform_account_name', 'platform_account_handle', 'profile_picture_url',
        'access_token', 'refresh_token', 'token_expires_at', 'platform_data',
        'metadata', 'status', 'error_message', 'last_synced_at', 'is_active', 'auto_reply_started_at',
        'parent_account_id', 'credential_id', 'auth_mode', 'webhook_subscribed_at', 'token_refreshed_at'
    ];
 
    protected $casts = [
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'platform_data' => 'array',
        'is_active' => 'boolean',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'webhook_subscribed_at' => 'datetime',
        'token_refreshed_at' => 'datetime',
    ];
 
    protected $hidden = ['access_token', 'refresh_token'];
 
 
    public function user()
    {
        return $this->belongsTo(User::class);
    }
 
    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }
 
    public function socialComments()
    {
        return $this->hasMany(SocialComment::class);
    }
 
    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    /** The Facebook Page an Instagram account was connected through (if any). */
    public function parent()
    {
        return $this->belongsTo(SocialAccount::class, 'parent_account_id');
    }

    public function children()
    {
        return $this->hasMany(SocialAccount::class, 'parent_account_id');
    }

    public function credential()
    {
        return $this->belongsTo(PlatformCredential::class);
    }

    /** Connected with "Instagram API with Instagram Login" (no Facebook Page). */
    public function isInstagramLogin(): bool
    {
        return $this->platform === 'instagram' && $this->auth_mode === 'instagram_login';
    }

    public function markTokenInvalid(string $reason): void
    {
        $this->forceFill(['status' => 'expired', 'error_message' => mb_substr($reason, 0, 1000)])->save();
    }
}
