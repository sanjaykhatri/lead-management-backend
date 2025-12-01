<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminLeadStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Lead $lead,
        protected string $oldStatus,
    ) {
        $this->lead->loadMissing(['location', 'serviceProvider']);
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'admin_lead_status_updated',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->lead->status,
            'location' => $this->lead->location?->name,
            'service_provider_id' => $this->lead->service_provider_id,
            'service_provider_name' => $this->lead->serviceProvider->name ?? null,
            'message' => "Lead '{$this->lead->name}' status changed from {$this->oldStatus} to {$this->lead->status}",
        ];
    }
}


