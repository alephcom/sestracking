<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'token', 'alert_bounce_rate', 'alert_complaint_rate'];

    protected $casts = [
        'alert_bounce_rate' => 'float',
        'alert_complaint_rate' => 'float',
    ];

    /**
     * Get users that have access to this project
     */
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
    
    /**
     * Get admin users for this project
     */
    public function admins()
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', 'admin')
            ->withTimestamps();
    }

    /**
     * Get emails for this project
     */
    public function emails()
    {
        return $this->hasMany(Email::class);
    }
}
