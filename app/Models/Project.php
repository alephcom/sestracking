<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'ses_aws_secret_access_key',
    ];

    protected $fillable = [
        'name',
        'token',
        'alert_bounce_rate',
        'alert_complaint_rate',
        'ses_suppression_auto_push_enabled',
        'ses_suppression_push_complaints',
        'ses_suppression_push_soft_bounces',
        'ses_aws_access_key_id',
        'ses_aws_secret_access_key',
        'ses_aws_default_region',
    ];

    protected $casts = [
        'alert_bounce_rate' => 'float',
        'alert_complaint_rate' => 'float',
        'ses_suppression_auto_push_enabled' => 'boolean',
        'ses_suppression_push_complaints' => 'boolean',
        'ses_suppression_push_soft_bounces' => 'boolean',
        'ses_aws_secret_access_key' => 'encrypted',
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

    public function hasSesSuppressionAwsCredentials(): bool
    {
        return filled($this->ses_aws_access_key_id) && filled($this->ses_aws_secret_access_key);
    }

    public function resolvedSesSuppressionRegion(): string
    {
        return trim((string) ($this->ses_aws_default_region ?: config('services.ses.region') ?: ''));
    }

    public function canRunSesSuppressionApi(): bool
    {
        return $this->hasSesSuppressionAwsCredentials() && $this->resolvedSesSuppressionRegion() !== '';
    }

    public function sesSuppressedDestinations()
    {
        return $this->hasMany(SesSuppressedDestination::class);
    }
}
