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

class LeadAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead->load(['location', 'serviceProvider']);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('provider.' . $this->lead->service_provider_id);
    }

    public function broadcastAs()
    {
        return 'lead.assigned';
    }

    public function broadcastWith()
    {
        return [
            'lead' => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'phone' => $this->lead->phone,
                'email' => $this->lead->email,
                'status' => $this->lead->status,
                'location' => $this->lead->location->name,
                'created_at' => $this->lead->created_at->toIso8601String(),
            ],
        ];
    }
}

