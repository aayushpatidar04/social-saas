<?php

// app/Models/ActivityLog.php
namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'changes' => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
