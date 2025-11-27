# Pusher Notifications Debugging Guide

## Changes Made

1. **Registered BroadcastingServiceProvider** in `bootstrap/providers.php`
2. **Added logging** to all event firing points
3. **Enhanced BroadcastingServiceProvider** to register broadcast routes and load config from database
4. **Added test command** `php artisan broadcast:test` to test broadcasting

## Debugging Steps

### 1. Check if Pusher is Enabled in Database

```bash
php artisan tinker
>>> \App\Models\Setting::get('pusher_enabled')
```

Should return `true` or `'true'`.

### 2. Check Pusher Credentials

```bash
php artisan tinker
>>> \App\Models\Setting::get('pusher_app_key')
>>> \App\Models\Setting::get('pusher_app_secret')
>>> \App\Models\Setting::get('pusher_app_id')
>>> \App\Models\Setting::get('pusher_app_cluster')
```

All should have values.

### 3. Test Broadcasting Configuration

```bash
php artisan broadcast:test
```

This will:
- Check if Pusher is enabled
- Display configuration
- Fire a test event

### 4. Check Laravel Logs

After creating/updating a lead, check `storage/logs/laravel.log` for:
- `Firing LeadAssigned event`
- `LeadAssigned event will broadcast`
- `Pusher config updated from database`

### 5. Check Frontend Console

In browser console, you should see:
- `Pusher: Setting up...`
- `Pusher: Config received`
- `✅ Pusher connected successfully`
- `✅ Pusher channel subscribed successfully`
- `📨 Pusher event received`

### 6. Check Pusher Dashboard

1. Go to https://dashboard.pusher.com
2. Select your app
3. Go to "Debug Console"
4. Create/update a lead
5. You should see events being sent

### 7. Verify Event Broadcasting

Check if events are actually being broadcast:

```bash
php artisan tinker
>>> $lead = \App\Models\Lead::first();
>>> event(new \App\Events\LeadAssigned($lead));
```

Then check:
- Laravel logs for "Firing LeadAssigned event"
- Pusher dashboard for the event
- Frontend console for the event

## Common Issues

### Issue: Events not firing
**Solution**: Check Laravel logs for errors. Make sure `BroadcastingServiceProvider` is registered.

### Issue: Frontend not connecting
**Solution**: 
1. Check browser console for errors
2. Verify Pusher credentials in `/admin/settings`
3. Check network tab for WebSocket connections
4. Verify CORS settings if using different domains

### Issue: Private channels not working
**Solution**:
1. Check `/api/broadcasting/auth` route exists
2. Verify authentication token is being sent
3. Check `routes/channels.php` for authorization logic
4. Check Laravel logs for authentication errors

### Issue: Events firing but not received
**Solution**:
1. Check Pusher dashboard to see if events are sent
2. Verify channel names match (admin vs private-provider.X)
3. Check frontend is subscribed to correct channels
4. Verify event names match (`lead.assigned` vs `lead_assigned`)

## Event Names

- **Backend broadcasts as**: `lead.assigned` and `lead.status.updated`
- **Frontend listens for**: `lead.assigned` and `lead.status.updated`
- **Channels**:
  - Admin: `admin` (public channel)
  - Provider: `private-provider.{providerId}` (private channel)

## Testing

1. **Test Admin Notifications**:
   - Login as admin
   - Create a new lead
   - Should see notification in admin dashboard

2. **Test Provider Notifications**:
   - Login as provider
   - Admin assigns a lead to that provider
   - Provider should receive notification

3. **Test Status Updates**:
   - Provider updates lead status
   - Both admin and provider should receive notification

