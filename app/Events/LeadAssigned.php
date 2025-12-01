<?php

namespace App\Events;

use App\Models\Lead;
use App\Services\BroadcastingConfigService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead->load(['location', 'serviceProvider']);
    }

    public function shouldBroadcast()
    {
        $enabled = BroadcastingConfigService::isPusherEnabled();
        
        if ($enabled) {
            \Log::info('LeadAssigned event will broadcast', [
                'lead_id' => $this->lead->id,
                'provider_id' => $this->lead->service_provider_id,
            ]);
        } else {
            \Log::info('LeadAssigned event will NOT broadcast (Pusher disabled)', [
                'lead_id' => $this->lead->id,
            ]);
        }
        
        return $enabled;
    }

    public function broadcastOn()
    {
        $channels = [new Channel('admin')]; // Admin receives all lead assignments
        
        if ($this->lead->service_provider_id) {
            $channels[] = new PrivateChannel('provider.' . $this->lead->service_provider_id);
        }
        
        \Log::info('LeadAssigned broadcastOn called', [
            'lead_id' => $this->lead->id,
            'channels' => array_map(function($channel) {
                if ($channel instanceof PrivateChannel) {
                    return 'private-provider.' . str_replace('provider.', '', $channel->name);
                }
                return $channel->name;
            }, $channels),
        ]);
        
        return $channels;
    }

    public function broadcastAs()
    {
        return 'lead.assigned';
    }

    public function broadcastWith()
    {
        $data = [
            'type' => 'lead_assigned',
            'lead' => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'phone' => $this->lead->phone,
                'email' => $this->lead->email,
                'status' => $this->lead->status,
                'location' => $this->lead->location->name,
                'service_provider_id' => $this->lead->service_provider_id,
                'service_provider_name' => $this->lead->serviceProvider->name ?? null,
                'created_at' => $this->lead->created_at->toIso8601String(),
            ],
            'message' => $this->lead->serviceProvider 
                ? "New lead '{$this->lead->name}' assigned to {$this->lead->serviceProvider->name}"
                : "New lead '{$this->lead->name}' created",
        ];
        
        \Log::info('LeadAssigned broadcastWith called', [
            'lead_id' => $this->lead->id,
            'event_name' => $this->broadcastAs(),
            'data_keys' => array_keys($data),
        ]);
        
        return $data;
    }
}

