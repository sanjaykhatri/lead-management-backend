<?php

namespace App\Console\Commands;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Services\BroadcastingConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestBroadcasting extends Command
{
    protected $signature = 'broadcast:test';
    protected $description = 'Test broadcasting configuration and send a test event';

    public function handle()
    {
        $this->info('Testing Broadcasting Configuration...');
        
        // Check if Pusher is enabled
        $enabled = BroadcastingConfigService::isPusherEnabled();
        $this->info('Pusher Enabled: ' . ($enabled ? 'Yes' : 'No'));
        
        if (!$enabled) {
            $this->error('Pusher is not enabled in database settings!');
            return 1;
        }
        
        // Get config
        $config = BroadcastingConfigService::getPusherConfig();
        $this->info('Pusher Config:');
        $this->line('  Key: ' . ($config['key'] ? '***' . substr($config['key'], -4) : 'MISSING'));
        $this->line('  Secret: ' . ($config['secret'] ? '***' : 'MISSING'));
        $this->line('  App ID: ' . ($config['app_id'] ?: 'MISSING'));
        $this->line('  Cluster: ' . ($config['options']['cluster'] ?? 'MISSING'));
        
        // Check Laravel config
        $broadcastDriver = config('broadcasting.default');
        $this->info('Broadcast Driver: ' . $broadcastDriver);
        
        // Try to fire a test event
        $this->info('Attempting to fire test event...');
        
        try {
            // Get a test lead or create a dummy one
            $lead = Lead::with(['location', 'serviceProvider'])->first();
            
            if ($lead) {
                event(new LeadAssigned($lead));
                $this->info('✅ Test event fired successfully!');
                $this->info('Check your Pusher dashboard and frontend console for the event.');
            } else {
                $this->warn('No leads found in database. Create a lead first.');
            }
        } catch (\Exception $e) {
            $this->error('Failed to fire event: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}

