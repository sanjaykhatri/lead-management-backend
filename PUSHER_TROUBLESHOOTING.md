# Pusher Notifications Troubleshooting Guide

## Common Issues and Solutions

### 1. Not Receiving Notifications

**Checklist:**
- [ ] Pusher is enabled in admin settings (`pusher_enabled = true`)
- [ ] Pusher credentials are correctly entered in admin settings
- [ ] Pusher package is installed: `composer require pusher/pusher-php-server`
- [ ] Frontend has pusher-js installed: `npm install pusher-js`
- [ ] Browser console shows no errors
- [ ] Laravel logs show no broadcasting errors

### 2. Broadcasting Configuration

**Check `.env`:**
```env
BROADCAST_DRIVER=pusher
```

**Verify Database Settings:**
- Go to `/admin/settings`
- Check Pusher tab
- Ensure all fields are filled:
  - `pusher_enabled` = true
  - `pusher_app_id`
  - `pusher_app_key`
  - `pusher_app_secret`
  - `pusher_app_cluster`

### 3. Test Pusher Connection

1. Go to `/admin/settings`
2. Click "Test Connection" button
3. Should see "Pusher connection successful"

### 4. Frontend Setup

**Check Browser Console:**
- Open browser DevTools (F12)
- Go to Console tab
- Look for:
  - "Pusher connected" message
  - Any error messages
  - Connection status

**Verify Pusher Client:**
- Check Network tab for requests to `/api/broadcasting/auth`
- Should return 200 status
- If 401/403, check authentication token

### 5. Channel Authorization

**Provider Channels:**
- Channel name: `private-provider.{providerId}`
- Must be authenticated as that provider
- Check `routes/channels.php` for authorization logic

**Admin Channels:**
- Channel name: `admin`
- Must be authenticated admin user

### 6. Event Broadcasting

**Check if events are being broadcast:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Look for broadcasting errors
3. Verify `shouldBroadcast()` returns true in events

**Test Event:**
```php
// In tinker or controller
event(new \App\Events\LeadAssigned($lead));
```

### 7. Queue Configuration

If using queues, ensure queue worker is running:
```bash
php artisan queue:work
```

### 8. CORS Issues

If seeing CORS errors:
- Check `config/cors.php`
- Ensure Pusher domain is allowed
- Check browser console for CORS errors

### 9. Debug Steps

1. **Check Pusher Dashboard:**
   - Go to https://dashboard.pusher.com/
   - Check "Debug Console"
   - See if events are being received

2. **Enable Logging:**
   ```php
   // In config/broadcasting.php
   'default' => 'log', // Temporarily use log driver
   ```

3. **Check Network:**
   - Browser DevTools > Network tab
   - Filter by "pusher" or "ws"
   - Check WebSocket connection status

4. **Laravel Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### 10. Common Errors

**"Pusher connection failed"**
- Check credentials
- Verify cluster matches Pusher dashboard
- Check firewall/network restrictions

**"Unauthorized" on channel subscription**
- Check authentication token
- Verify channel authorization logic
- Check user has permission for that channel

**"Event not received"**
- Check `shouldBroadcast()` returns true
- Verify event is being fired
- Check queue worker if using queues
- Verify channel name matches

## Quick Test

1. Open provider dashboard in browser
2. Open browser console (F12)
3. Create a new lead from admin panel
4. Should see:
   - "Pusher connected" in console
   - Real-time notification appear
   - No errors in console

## Still Not Working?

1. Check all items in checklist above
2. Review Laravel logs
3. Check Pusher dashboard for events
4. Verify all credentials are correct
5. Test with Pusher's test tool in dashboard

