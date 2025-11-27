<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;
    public $oldStatus;

    public function __construct(Lead $lead, $oldStatus)
    {
        $this->lead = $lead->load(['location', 'serviceProvider']);
        $this->oldStatus = $oldStatus;
    }

    public function broadcastOn()
    {
        $channels = [new Channel('admin')];
        
        if ($this->lead->service_provider_id) {
            $channels[] = new PrivateChannel('provider.' . $this->lead->service_provider_id);
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'lead.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'lead' => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'status' => $this->lead->status,
                'old_status' => $this->oldStatus,
            ],
        ];
    }
}

