<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'lead_id',
        'event_type',
        'performed_by_type',
        'performed_by_id',
        'performed_by_name',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Helper method to log an activity
     */
    public static function log($leadId, $eventType, $performedByType, $performedById, $performedByName, $description, $metadata = null)
    {
        return self::create([
            'lead_id' => $leadId,
            'event_type' => $eventType,
            'performed_by_type' => $performedByType,
            'performed_by_id' => $performedById,
            'performed_by_name' => $performedByName,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
