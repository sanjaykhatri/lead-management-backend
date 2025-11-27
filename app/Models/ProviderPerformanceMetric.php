<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPerformanceMetric extends Model
{
    protected $fillable = [
        'service_provider_id',
        'metric_date',
        'total_leads',
        'new_leads',
        'contacted_leads',
        'closed_leads',
        'conversion_rate',
        'avg_response_time_minutes',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'conversion_rate' => 'decimal:2',
        'avg_response_time_minutes' => 'integer',
    ];

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}

