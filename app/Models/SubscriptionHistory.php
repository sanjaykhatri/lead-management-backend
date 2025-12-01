<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    protected $table = 'subscription_history';

    protected $fillable = [
        'service_provider_id',
        'subscription_plan_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'event_type',
        'status',
        'amount',
        'currency',
        'description',
        'metadata',
        'event_date',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'event_date' => 'datetime',
    ];

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Log a subscription event
     */
    public static function log($providerId, $eventType, $status, $stripeSubscriptionId = null, $stripeCustomerId = null, $planId = null, $amount = null, $description = null, $metadata = null)
    {
        return self::create([
            'service_provider_id' => $providerId,
            'subscription_plan_id' => $planId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_customer_id' => $stripeCustomerId,
            'event_type' => $eventType,
            'status' => $status,
            'amount' => $amount,
            'currency' => 'usd',
            'description' => $description,
            'metadata' => $metadata,
            'event_date' => now(),
        ]);
    }
}
