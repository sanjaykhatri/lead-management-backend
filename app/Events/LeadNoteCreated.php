<?php

namespace App\Events;

use App\Models\LeadNote;
use App\Services\BroadcastingConfigService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadNoteCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $note;

    public function __construct(LeadNote $note)
    {
        $this->note = $note->load(['lead.location', 'lead.serviceProvider', 'user', 'serviceProvider']);
    }

    public function shouldBroadcast()
    {
        $enabled = BroadcastingConfigService::isPusherEnabled();
        
        if ($enabled) {
            \Log::info('LeadNoteCreated event will broadcast (queued)', [
                'note_id' => $this->note->id,
                'lead_id' => $this->note->lead_id,
            ]);
        } else {
            \Log::info('LeadNoteCreated event will NOT broadcast (Pusher disabled)', [
                'note_id' => $this->note->id,
            ]);
        }
        
        return $enabled;
    }

    public function broadcastOn()
    {
        $channels = [new Channel('admin')]; // Admin receives all notes
        
        // If note is for a lead with an assigned provider, broadcast to that provider
        if ($this->note->lead->service_provider_id) {
            $channels[] = new PrivateChannel('provider.' . $this->note->lead->service_provider_id);
        }

        \Log::info('LeadNoteCreated broadcastOn called', [
            'note_id' => $this->note->id,
            'lead_id' => $this->note->lead_id,
            'channels' => array_map(function($channel) {
                return $channel->name;
            }, $channels),
        ]);

        return $channels;
    }

    public function broadcastAs()
    {
        return 'lead.note.created';
    }

    public function broadcastWith()
    {
        $createdBy = $this->note->user 
            ? $this->note->user->name 
            : ($this->note->serviceProvider ? $this->note->serviceProvider->name : 'System');

        return [
            'type' => 'lead_note_created',
            'note' => [
                'id' => $this->note->id,
                'lead_id' => $this->note->lead_id,
                'note' => $this->note->note,
                'type' => $this->note->type,
                'created_by' => $createdBy,
                'created_by_type' => $this->note->user_id ? 'admin' : 'provider',
                'created_at' => $this->note->created_at->toIso8601String(),
            ],
            'lead' => [
                'id' => $this->note->lead->id,
                'name' => $this->note->lead->name,
                'service_provider_id' => $this->note->lead->service_provider_id,
            ],
            'message' => "New note added to lead '{$this->note->lead->name}' by {$createdBy}",
        ];
    }
}

