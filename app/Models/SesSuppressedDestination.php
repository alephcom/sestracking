<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesSuppressedDestination extends Model
{
    protected $fillable = [
        'project_id',
        'email',
        'reason',
        'last_update_time',
        'synced_at',
    ];

    protected $casts = [
        'last_update_time' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
