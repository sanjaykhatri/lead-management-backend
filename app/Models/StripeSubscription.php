<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeSubscription extends Model
{
    protected $fillable = [
        'service_provider_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
        'current_period_end',
        'trial_ends_at',
        'subscription_plan_id',
    ];

    protected $casts = [
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
