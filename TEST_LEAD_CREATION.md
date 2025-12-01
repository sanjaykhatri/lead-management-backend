# Testing Lead Creation and Broadcasting

## Quick Test Steps

1. **Submit a lead from the public form**
2. **Check Laravel logs** (`storage/logs/laravel.log`) for:
   - `Firing LeadAssigned event`
   - `LeadAssigned event constructed`
   - `LeadAssigned event will broadcast`
   - `LeadAssigned broadcastOn called`
   - `LeadAssigned broadcastWith called`

3. **Check browser console** (admin panel) for:
   - `📨 Pusher event received` with event data

4. **Check Pusher Dashboard**:
   - Go to https://dashboard.pusher.com
   - Select your app
   - Go to "Debug Console"
   - You should see events being sent

## If Events Are Not Broadcasting

### Check 1: Is Pusher Enabled?
```bash
php artisan tinker
>>> \App\Models\Setting::get('pusher_enabled')
# Should return true
```

### Check 2: Are Credentials Set?
```bash
php artisan tinker
>>> \App\Models\Setting::get('pusher_app_key')
>>> \App\Models\Setting::get('pusher_app_id')
>>> \App\Models\Setting::get('pusher_app_secret')
>>> \App\Models\Setting::get('pusher_app_cluster')
```

### Check 3: Test Broadcasting
```bash
php artisan broadcast:test
```

### Check 4: Check Laravel Logs
```bash
tail -f storage/logs/laravel.log | grep -i "lead\|pusher\|broadcast"
```

Then submit a lead and watch for log entries.

### Check 5: Verify Queue Worker (if using queues)
If broadcasting is queued, you need to run:
```bash
php artisan queue:work
```

However, we're using `ShouldBroadcastNow` which broadcasts immediately without queues.

## Common Issues

1. **Events fire but not received**: Check channel names match (admin vs private-provider.X)
2. **No logs at all**: Check if event is being fired (see LeadController)
3. **Pusher not connecting**: Check credentials in /admin/settings
4. **Private channels fail**: Check /api/broadcasting/auth route and authentication

