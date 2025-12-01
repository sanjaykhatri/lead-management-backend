<?php

namespace App\Events;

use App\Models\Lead;
use App\Services\BroadcastingConfigService;
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

    public function shouldBroadcast()
    {
        $enabled = BroadcastingConfigService::isPusherEnabled();
        
        if ($enabled) {
            \Log::info('LeadStatusUpdated event will broadcast (queued)', [
                'lead_id' => $this->lead->id,
                'provider_id' => $this->lead->service_provider_id,
            ]);
        } else {
            \Log::info('LeadStatusUpdated event will NOT broadcast (Pusher disabled)', [
                'lead_id' => $this->lead->id,
            ]);
        }
        
        return $enabled;
    }

    public function broadcastOn()
    {
        $channels = [new Channel('admin')];
        
        if ($this->lead->service_provider_id) {
            $channels[] = new PrivateChannel('provider.' . $this->lead->service_provider_id);
        }

        \Log::info('LeadStatusUpdated broadcastOn called', [
            'lead_id' => $this->lead->id,
            'channels' => array_map(function($channel) {
                return $channel->name;
            }, $channels),
        ]);

        return $channels;
    }

    public function broadcastAs()
    {
        return 'lead.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'type' => 'lead_status_updated',
            'lead' => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'status' => $this->lead->status,
                'old_status' => $this->oldStatus,
                'service_provider_id' => $this->lead->service_provider_id,
                'service_provider_name' => $this->lead->serviceProvider->name ?? null,
            ],
            'message' => "Lead '{$this->lead->name}' status changed from {$this->oldStatus} to {$this->lead->status}",
        ];
    }
}

