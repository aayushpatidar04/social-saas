<?php

// app/Models/AnalyticsCache.php
namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AnalyticsCache extends Model
{
    use BelongsToOrganization;
    protected $fillable = [
        'organization_id',
        'metric_type',
        'data',
        'last_updated_at'
    ];

    protected $casts = [
        'data' => 'array',
        'last_updated_at' => 'datetime',
    ];

}
