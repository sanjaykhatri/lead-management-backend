# SMS and Real-time Notifications Setup Guide

## SMS Notifications (Twilio)

### 1. Install Twilio Package
```bash
composer require laravel-notification-channels/twilio
```

### 2. Configure Environment Variables
Add to your `.env` file:
```env
TWILIO_ENABLED=true
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+1234567890  # Your Twilio phone number
```

### 3. Get Twilio Credentials
1. Sign up at https://www.twilio.com/
2. Get your Account SID and Auth Token from the dashboard
3. Get a phone number from Twilio

### 4. How It Works
- When a lead is assigned to a provider, an SMS notification is sent automatically
- SMS is only sent if:
  - Provider has a phone number
  - `TWILIO_ENABLED=true` in `.env`
  - Twilio credentials are configured

## Real-time Notifications (Laravel Broadcasting)

### 1. Install Broadcasting Driver
Choose one:
- **Pusher** (recommended for production): `composer require pusher/pusher-php-server`
- **Laravel Echo Server**: `npm install -g laravel-echo-server`
- **Redis** (for self-hosted): Already included with Laravel

### 2. Configure Broadcasting
Add to your `.env` file:

**For Pusher:**
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

**For Redis:**
```env
BROADCAST_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. Frontend Setup (Next.js)

Install Pusher client:
```bash
npm install pusher-js
```

Create a notifications hook:
```typescript
// hooks/useNotifications.ts
import { useEffect, useState } from 'react';
import Pusher from 'pusher-js';

export function useNotifications(userId: string) {
  const [notifications, setNotifications] = useState([]);

  useEffect(() => {
    const pusher = new Pusher(process.env.NEXT_PUBLIC_PUSHER_KEY!, {
      cluster: process.env.NEXT_PUBLIC_PUSHER_CLUSTER!,
      authEndpoint: '/api/broadcasting/auth',
    });

    const channel = pusher.subscribe(`private-provider.${userId}`);
    
    channel.bind('lead.assigned', (data: any) => {
      setNotifications(prev => [data, ...prev]);
    });

    return () => {
      pusher.unsubscribe(`private-provider.${userId}`);
    };
  }, [userId]);

  return notifications;
}
```

### 4. Broadcasting Auth Route
Create `routes/channels.php`:
```php
Broadcast::channel('provider.{providerId}', function ($user, $providerId) {
    return (int) $user->id === (int) $providerId;
});
```

### 5. Events
The following events are broadcast:
- `LeadAssigned` - When a lead is assigned to a provider
- `LeadStatusUpdated` - When a lead status changes

## Testing

### Test SMS
1. Ensure provider has a phone number
2. Create a new lead
3. Check provider's phone for SMS

### Test Real-time
1. Open provider dashboard in browser
2. Create a new lead from another browser/admin panel
3. Provider should see real-time notification

## Troubleshooting

### SMS Not Sending
- Check `TWILIO_ENABLED=true`
- Verify Twilio credentials
- Check provider has phone number
- Check Laravel logs for errors

### Real-time Not Working
- Verify broadcasting driver is set
- Check Pusher/Redis credentials
- Ensure frontend is connected to correct channel
- Check browser console for connection errors

