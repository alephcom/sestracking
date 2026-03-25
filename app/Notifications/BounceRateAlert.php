<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BounceRateAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  'bounce'|'complaint'  $metricType
     */
    public function __construct(
        public Project $project,
        public string $metricType,
        public float $rate,
        public float $threshold,
    ) {
        $this->onQueue(config('queue.default'));
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $metricLabel = $this->metricType === 'complaint' ? 'Complaint' : 'Bounce';
        $thresholdPct = number_format($this->threshold, 2).'%';
        $dashboardUrl = route('dashboard.index');

        return (new MailMessage)
            ->subject('['.config('app.name').'] '.$metricLabel.' rate alert for '.$this->project->name)
            ->line('The '.strtolower($metricLabel).' rate for project "'.$this->project->name.'" has crossed your configured alert threshold.')
            ->line('Current rate: '.number_format($this->rate, 2).'% (threshold: '.$thresholdPct.')')
            ->action('View dashboard', $dashboardUrl)
            ->line('High '.strtolower($metricLabel).' rates can lead to AWS pausing your sending account. Please review your list quality and sending practices.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'metric_type' => $this->metricType,
            'rate' => $this->rate,
            'threshold' => $this->threshold,
        ];
    }
}
