<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $lead;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    public function via($notifiable)
    {
        $channels = ['database'];
        
        // Add SMS if phone number is available
        if ($notifiable->phone && config('services.twilio.enabled', false)) {
            $channels[] = TwilioChannel::class;
        }

        return $channels;
    }

    public function toTwilio($notifiable)
    {
        return (new TwilioSmsMessage())
            ->content("New lead assigned: {$this->lead->name} - {$this->lead->phone}. Location: {$this->lead->location->name}");
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'lead_assigned',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'lead_phone' => $this->lead->phone,
            'location' => $this->lead->location->name,
            'message' => "New lead assigned: {$this->lead->name}",
        ];
    }
}

