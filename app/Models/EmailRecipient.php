<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailRecipient extends Model
{
    use HasFactory;
    protected $fillable = [
        'email_id',
        'address',
        'status',
    ];

    /**
     * Get the email that owns this recipient
     */
    public function email()
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Get events for this recipient
     */
    public function events()
    {
        return $this->hasMany(RecipientEvent::class, 'recipient_id');
    }
}