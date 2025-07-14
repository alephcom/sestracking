<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_id',
        'message_id',
        'source',
        'subject',
        'sent_at',
        'opens',
        'clicks',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $appends = ['timestamp', 'destination', 'status'];

    public function getTimestampAttribute()
    {
        return $this->sent_at ? $this->sent_at->toDateTimeString() : '';
    }

    /**
     * Get the project that owns this email
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get recipients for this email
     */
    public function recipients()
    {
        return $this->hasMany(EmailRecipient::class);
    }

    /**
     * Get all events for this email through recipients
     */
    public function events()
    {
        return $this->hasManyThrough(
            RecipientEvent::class, 
            EmailRecipient::class,
            'email_id',        // Foreign key on email_recipients table
            'recipient_id',    // Foreign key on recipient_events table
            'id',              // Local key on emails table
            'id'               // Local key on email_recipients table
        );
    }

    /**
     * Get status from recipients (for backward compatibility)
     */
    public function getStatusAttribute()
    {
        $recipientStatuses = $this->recipients->pluck('status')->unique();
        
        if ($recipientStatuses->contains('bounced') || $recipientStatuses->contains('rejected')) {
            return 'bounced';
        }
        if ($recipientStatuses->contains('complained')) {
            return 'complained';
        }
        if ($recipientStatuses->contains('delivered')) {
            return 'delivered';
        }
        
        return 'sent';
    }

    /**
     * Get destination addresses (for backward compatibility)
     */
    public function getDestinationAttribute()
    {
        // Ensure we always return an array, even if recipients aren't loaded
        if (!$this->relationLoaded('recipients')) {
            return [];
        }
        return $this->recipients->pluck('address')->toArray();
    }
}