<?php

namespace App\Notifications;

use App\Models\LeadNote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminLeadNoteCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $note;

    public function __construct(LeadNote $note)
    {
        $this->note = $note;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $createdBy = $this->note->user 
            ? $this->note->user->name 
            : ($this->note->serviceProvider ? $this->note->serviceProvider->name : 'System');

        return [
            'type' => 'admin_lead_note_created',
            'note_id' => $this->note->id,
            'lead_id' => $this->note->lead_id,
            'lead_name' => $this->note->lead->name,
            'created_by' => $createdBy,
            'created_by_type' => $this->note->user_id ? 'admin' : 'provider',
            'note_preview' => substr($this->note->note, 0, 100),
            'message' => "New note added to lead '{$this->note->lead->name}' by {$createdBy}",
        ];
    }
}

