<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminLeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Lead $lead)
    {
        $this->lead->loadMissing(['location', 'serviceProvider']);
    }

    public function via($notifiable): array
    {
        // Admin notifications are stored in database only
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'admin_lead_assigned',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'lead_phone' => $this->lead->phone,
            'lead_email' => $this->lead->email,
            'status' => $this->lead->status,
            'location' => $this->lead->location?->name,
            'service_provider_id' => $this->lead->service_provider_id,
            'service_provider_name' => $this->lead->serviceProvider->name ?? null,
            'message' => $this->lead->serviceProvider
                ? "New lead '{$this->lead->name}' assigned to {$this->lead->serviceProvider->name}"
                : "New lead '{$this->lead->name}' created",
        ];
    }
}


