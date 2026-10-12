<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationInvitation extends Model
{
    protected $fillable = ['organization_id', 'invited_by', 'email', 'role', 'token_hash', 'expires_at', 'accepted_at'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    protected $hidden = ['token_hash'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
