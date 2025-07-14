<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipientEvent extends Model
{
    use HasFactory;
    protected $fillable = [
        'recipient_id',
        'sns_message_id',
        'type',
        'event_at',
        'payload',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Get the recipient that owns this event
     */
    public function recipient()
    {
        return $this->belongsTo(EmailRecipient::class, 'recipient_id');
    }

    /**
     * Get the email through the recipient
     */
    public function email()
    {
        return $this->hasOneThrough(Email::class, EmailRecipient::class, 'id', 'id', 'recipient_id', 'email_id');
    }
}