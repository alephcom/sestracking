<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailEvent extends Model
{
    protected $fillable = [
        'email_id',
        'event_type',
        'event_data',
    ];

    protected $casts = [
        'event_data' => 'array',
    ];

    /**
     * Get the email that owns this event
     */
    public function email()
    {
        return $this->belongsTo(Email::class);
    }
}
