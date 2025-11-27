<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Location;
use App\Models\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadAssignmentService
{
    /**
     * Assign a lead to a service provider based on the location's assignment algorithm
     */
    public function assignLead(Lead $lead, Location $location): ?ServiceProvider
    {
        $algorithm = $location->assignment_algorithm ?? 'round_robin';
        
        $eligibleProviders = $this->getEligibleProviders($location);

        if ($eligibleProviders->isEmpty()) {
            Log::warning("No eligible providers found for location {$location->id}");
            return null;
        }

        return match ($algorithm) {
            'round_robin' => $this->roundRobinAssignment($location, $eligibleProviders),
            'geographic' => $this->geographicAssignment($lead, $eligibleProviders),
            'load_balance' => $this->loadBalanceAssignment($eligibleProviders),
            'manual' => null, // Manual assignment - admin will assign
            default => $this->roundRobinAssignment($location, $eligibleProviders),
        };
    }

    /**
     * Get eligible providers (active subscription, assigned to location, active account)
     */
    protected function getEligibleProviders(Location $location)
    {
        return $location->serviceProviders()
            ->where('is_active', true)
            ->whereHas('stripeSubscription', function ($query) {
                $query->where(function ($q) {
                    $q->where('status', 'active')
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('trial_ends_at')
                             ->where('trial_ends_at', '>', now());
                      });
                });
            })
            ->get();
    }

    /**
     * Round-robin assignment: assign to provider with least recent assignment
     */
    protected function roundRobinAssignment(Location $location, $providers)
    {
        // Get the last assigned provider for this location
        $lastAssignedProviderId = Lead::where('location_id', $location->id)
            ->whereNotNull('service_provider_id')
            ->whereIn('service_provider_id', $providers->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->value('service_provider_id');

        if (!$lastAssignedProviderId) {
            return $providers->first();
        }

        // Find the index of the last assigned provider
        $lastIndex = $providers->search(function ($provider) use ($lastAssignedProviderId) {
            return $provider->id === $lastAssignedProviderId;
        });

        // Get the next provider in the list
        $nextIndex = ($lastIndex + 1) % $providers->count();
        return $providers->get($nextIndex);
    }

    /**
     * Geographic assignment: assign to nearest provider based on zip code
     */
    protected function geographicAssignment(Lead $lead, $providers)
    {
        if (empty($lead->zip_code)) {
            return $this->roundRobinAssignment($lead->location, $providers);
        }

        $leadZip = $lead->zip_code;
        $nearestProvider = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($providers as $provider) {
            if (!$provider->zip_code) {
                continue;
            }

            // Simple distance calculation based on zip code (you can enhance this with actual coordinates)
            $distance = $this->calculateZipDistance($leadZip, $provider->zip_code);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestProvider = $provider;
            }
        }

        return $nearestProvider ?? $providers->first();
    }

    /**
     * Load balance assignment: assign to provider with least active leads
     */
    protected function loadBalanceAssignment($providers)
    {
        $providerLoads = [];

        foreach ($providers as $provider) {
            $activeLeadsCount = Lead::where('service_provider_id', $provider->id)
                ->whereIn('status', ['new', 'contacted'])
                ->count();

            $providerLoads[$provider->id] = $activeLeadsCount;
        }

        // Sort by load (ascending) and return the provider with least load
        asort($providerLoads);
        $leastLoadedProviderId = array_key_first($providerLoads);

        return $providers->firstWhere('id', $leastLoadedProviderId) ?? $providers->first();
    }

    /**
     * Calculate approximate distance between two zip codes
     * This is a simplified version - in production, use a proper geocoding service
     */
    protected function calculateZipDistance($zip1, $zip2): float
    {
        // Extract numeric parts
        $zip1Num = (int) preg_replace('/[^0-9]/', '', $zip1);
        $zip2Num = (int) preg_replace('/[^0-9]/', '', $zip2);

        // Simple difference calculation (not accurate but works for sorting)
        return abs($zip1Num - $zip2Num);
    }
}

