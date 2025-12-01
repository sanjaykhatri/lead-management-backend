<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProviderLeadStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $lead;
    protected $oldStatus;

    public function __construct(Lead $lead, $oldStatus)
    {
        $this->lead = $lead;
        $this->oldStatus = $oldStatus;
    }

    public function via($notifiable)
    {
        $channels = ['database'];
        
        // Add SMS if Twilio is enabled and provider has phone
        $twilioEnabled = \App\Models\Setting::get('twilio_enabled', false);
        if ($notifiable->phone && $twilioEnabled) {
            $channels[] = \App\Channels\DatabaseTwilioChannel::class;
        }
        
        return $channels;
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'provider_lead_status_updated',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->lead->status,
            'message' => "Lead '{$this->lead->name}' status changed from {$this->oldStatus} to {$this->lead->status}",
        ];
    }

    public function toTwilio($notifiable)
    {
        $from = \App\Models\Setting::get('twilio_from', config('services.twilio.from'));
        return (new \NotificationChannels\Twilio\TwilioSmsMessage())
            ->from($from)
            ->content("Lead '{$this->lead->name}' status changed from {$this->oldStatus} to {$this->lead->status}");
    }
}

