<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceProvider extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'address'];

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function stripeSubscription(): HasOne
    {
        return $this->hasOne(StripeSubscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->stripeSubscription && $this->stripeSubscription->status === 'active';
    }
}
