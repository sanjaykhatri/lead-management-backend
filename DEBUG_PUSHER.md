# Debugging Pusher Connection Issues

## Steps to Debug

### 1. Check Browser Console
Open browser DevTools (F12) and look for:
- `Pusher: Setting up...` - Hook is being called
- `Pusher: Config received` - Config is fetched
- `Pusher: Config valid, initializing...` - Config is valid
- `🔄 Pusher connecting...` - Connection attempt started
- `✅ Pusher connected successfully` - Connection successful
- `✅ Pusher channel subscribed successfully` - Channel subscribed

### 2. Check Pusher Settings
1. Go to `/admin/settings`
2. Verify:
   - `pusher_enabled` is checked (true)
   - `pusher_app_id` is filled
   - `pusher_app_key` is filled
   - `pusher_app_secret` is filled
   - `pusher_app_cluster` is filled
3. Click "Test Connection" button
4. Should see "Pusher connection successful"

### 3. Check Network Tab
1. Open browser DevTools → Network tab
2. Filter by "pusher" or "ws"
3. Look for:
   - WebSocket connection to Pusher
   - `/api/broadcasting/auth` requests (for private channels)
   - Any failed requests

### 4. Common Issues

**Issue: "Pusher not enabled or not configured"**
- Check settings in admin panel
- Verify all fields are filled
- Check console for config values

**Issue: "Waiting for userId for private channel"**
- For providers: Check if provider ID is fetched
- Check `/provider/user` endpoint returns provider data
- For admin: Should use 'admin' as placeholder (already fixed)

**Issue: "Pusher subscription error"**
- Check authentication token
- Verify `/api/broadcasting/auth` endpoint works
- Check channel authorization in `routes/channels.php`

**Issue: No console logs at all**
- Check if component is mounted
- Verify `usePusherNotifications` is being called
- Check if channelName is provided

### 5. Manual Test

Open browser console and run:
```javascript
// Check if Pusher is loaded
console.log(typeof Pusher);

// Check localStorage token
console.log(localStorage.getItem('token'));

// Check API base URL
console.log(api.defaults.baseURL);
```

### 6. Verify Backend

Check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

Look for:
- Broadcasting errors
- Event firing
- Channel authorization errors

### 7. Test Pusher Connection Directly

In browser console:
```javascript
import Pusher from 'pusher-js';

const pusher = new Pusher('YOUR_APP_KEY', {
  cluster: 'YOUR_CLUSTER',
});

pusher.connection.bind('connected', () => {
  console.log('Direct Pusher test: Connected!');
});

pusher.connection.bind('error', (err) => {
  console.error('Direct Pusher test: Error', err);
});
```

## Expected Console Output

When working correctly, you should see:
```
Pusher: Setting up... {endpoint: "/admin/settings/group/pusher", channelName: "admin"}
Pusher: Config received {configKeys: Array(5), pusher_enabled: true, ...}
Pusher: Config valid, initializing... {cluster: "us2", channelName: "admin"}
Pusher: Subscribing to channel {channelName: "admin", isPrivateChannel: false}
🔄 Pusher connecting... {channelName: "admin"}
🔄 Pusher state changed: "initialized" -> "connecting" {channelName: "admin"}
🔄 Pusher state changed: "connecting" -> "connected" {channelName: "admin"}
✅ Pusher connected successfully {channelName: "admin", eventName: "lead.assigned"}
✅ Pusher channel subscribed successfully {channelName: "admin"}
Pusher: Binding to event {eventName: "lead.assigned", channelName: "admin"}
```

