<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ServiceProvider extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'address', 'password', 'is_active', 'zip_code', 'latitude', 'longitude'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

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
        if (!$this->stripeSubscription) {
            return false;
        }

        // Check if in trial period
        if ($this->stripeSubscription->trial_ends_at && $this->stripeSubscription->trial_ends_at->isFuture()) {
            return true;
        }

        return $this->stripeSubscription->status === 'active';
    }

    public function performanceMetrics()
    {
        return $this->hasMany(ProviderPerformanceMetric::class);
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class);
    }

    public function subscriptionHistory()
    {
        return $this->hasMany(SubscriptionHistory::class)->orderBy('event_date', 'desc');
    }
}
