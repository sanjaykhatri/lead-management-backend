# Fixing Pusher 404 NOT FOUND Error

## Error Description
```
Pusher error: 404 NOT FOUND
```

This error occurs when Laravel tries to broadcast an event to Pusher, but Pusher returns a 404 error. This typically means:

1. **Invalid Pusher App ID** - The `pusher_app_id` in your database doesn't exist in your Pusher account
2. **Mismatched Credentials** - The key, secret, and app_id don't match
3. **Wrong Cluster** - The cluster specified doesn't match your Pusher app's cluster

## Steps to Fix

### 1. Verify Pusher Credentials in Database

Check your database settings table:
```sql
SELECT * FROM settings WHERE `key` LIKE 'pusher_%';
```

Or use Laravel Tinker:
```bash
php artisan tinker
>>> \App\Models\Setting::where('key', 'like', 'pusher_%')->get();
```

### 2. Verify Credentials in Pusher Dashboard

1. Go to https://dashboard.pusher.com
2. Select your app
3. Go to "App Keys" tab
4. Verify:
   - **App ID** matches `pusher_app_id` in database
   - **Key** matches `pusher_app_key` in database
   - **Secret** matches `pusher_app_secret` in database
   - **Cluster** matches `pusher_app_cluster` in database

### 3. Test Pusher Connection

Use the test endpoint in admin panel:
1. Go to `/admin/settings`
2. Click on "Pusher" tab
3. Click "Test Connection" button
4. If it fails, check the error message

Or use the API directly:
```bash
curl -X POST https://your-domain.com/api/admin/settings/pusher/test \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 4. Common Issues

#### Issue: App ID doesn't exist
**Solution**: 
- Check if you're using the correct Pusher account
- Verify the app_id in Pusher dashboard matches the database

#### Issue: Credentials from different apps
**Solution**: 
- Make sure all credentials (key, secret, app_id, cluster) are from the SAME Pusher app
- Don't mix credentials from different apps

#### Issue: Cluster mismatch
**Solution**: 
- Check your Pusher app's cluster in the dashboard
- Common clusters: `us2`, `eu`, `ap1`, `ap2`, `ap3`, `ap4`
- Update `pusher_app_cluster` in database to match

### 5. Check Laravel Logs

After making changes, check the logs:
```bash
tail -f storage/logs/laravel.log | grep -i pusher
```

Look for:
- `Pusher config updated from database` - Should show correct cluster
- `Pusher config loaded before broadcast` - Should show credentials are loaded
- Any error messages about missing credentials

### 6. Verify Config is Loaded

The config should be loaded in `BroadcastingServiceProvider` and also right before broadcasting in `LeadController`. Check logs for:
- `Pusher config updated from database` (from BroadcastingServiceProvider)
- `Pusher config loaded before broadcast` (from LeadController)

### 7. Test Broadcasting Manually

Use Laravel Tinker to test:
```bash
php artisan tinker
>>> $lead = \App\Models\Lead::first();
>>> event(new \App\Events\LeadAssigned($lead));
```

Check logs for any errors.

## After Fixing

Once credentials are correct:
1. Clear config cache: `php artisan config:clear`
2. Test creating a new lead
3. Check browser console for Pusher connection
4. Check Pusher dashboard Debug Console for events

## Still Having Issues?

If the error persists after verifying credentials:
1. Check if Pusher package is installed: `composer show pusher/pusher-php-server`
2. Check Laravel version compatibility
3. Try using the Pusher test endpoint in admin panel
4. Check network/firewall settings (Pusher API should be accessible)

