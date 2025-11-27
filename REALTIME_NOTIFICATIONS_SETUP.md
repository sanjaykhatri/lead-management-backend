# Real-time Notifications Setup - Complete Guide

## Overview

The system now supports real-time notifications for:
- **Admin**: Receives ALL notifications (new leads, status changes)
- **Provider**: Receives notifications for their assigned leads (new assignments, status changes)

## Events Broadcast

### 1. LeadAssigned Event
- **When**: New lead is created and assigned to a provider
- **Broadcasts to**:
  - `admin` channel (public) - All admins receive this
  - `private-provider.{providerId}` channel - Assigned provider receives this
- **Event name**: `lead.assigned`

### 2. LeadStatusUpdated Event
- **When**: Lead status is changed (by admin or provider)
- **Broadcasts to**:
  - `admin` channel (public) - All admins receive this
  - `private-provider.{providerId}` channel - Provider who owns the lead receives this
- **Event name**: `lead.status.updated`

## Frontend Implementation

### Admin Notifications
- Component: `components/NotificationsBell.tsx`
- Listens to: `admin` channel
- Events: `lead.assigned`, `lead.status.updated`
- Auto-refreshes: Admin dashboard when events received

### Provider Notifications
- Component: `components/ProviderNotificationsBell.tsx`
- Listens to: `private-provider.{providerId}` channel
- Events: `lead.assigned`, `lead.status.updated`
- Auto-refreshes: Provider dashboard when events received

## Testing

### Test Admin Notifications
1. Open admin dashboard in browser
2. Open browser console (F12)
3. Create a new lead (from public form or admin panel)
4. Should see:
   - Notification appear in admin notification bell
   - "Pusher connected" in console
   - Admin dashboard refreshes automatically

### Test Provider Notifications
1. Open provider dashboard in browser
2. Open browser console (F12)
3. Create a new lead assigned to that provider (from admin panel)
4. Should see:
   - Notification appear in provider notification bell
   - "Pusher connected" in console
   - Provider dashboard refreshes automatically

### Test Status Updates
1. Open both admin and provider dashboards
2. Change lead status from either panel
3. Both should receive notification
4. Both dashboards should refresh

## Channel Types

### Public Channels (No Auth Required)
- `admin` - All admins can subscribe
- Used for: All lead assignments, all status updates

### Private Channels (Auth Required)
- `private-provider.{providerId}` - Only that specific provider
- Used for: Lead assignments to that provider, status updates for their leads
- Requires: Authentication via `/api/broadcasting/auth`

## Troubleshooting

### Not Receiving Notifications

1. **Check Pusher Settings**
   - Go to `/admin/settings`
   - Verify `pusher_enabled` is true
   - Verify all credentials are correct
   - Click "Test Connection"

2. **Check Browser Console**
   - Open DevTools (F12)
   - Look for "Pusher connected" message
   - Check for errors

3. **Check Laravel Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   - Look for broadcasting errors
   - Verify events are being fired

4. **Verify Channel Subscription**
   - Admin: Should subscribe to `admin` channel
   - Provider: Should subscribe to `private-provider.{id}` channel
   - Check console for subscription success

5. **Check Event Firing**
   - Verify events are being dispatched
   - Check `shouldBroadcast()` returns true
   - Verify Pusher is enabled

## Event Data Structure

### LeadAssigned Event
```json
{
  "type": "lead_assigned",
  "lead": {
    "id": 1,
    "name": "John Doe",
    "phone": "1234567890",
    "email": "john@example.com",
    "status": "new",
    "location": "Location Name",
    "service_provider_id": 1,
    "service_provider_name": "Provider Name",
    "created_at": "2025-11-27T12:00:00Z"
  },
  "message": "New lead 'John Doe' assigned to Provider Name"
}
```

### LeadStatusUpdated Event
```json
{
  "type": "lead_status_updated",
  "lead": {
    "id": 1,
    "name": "John Doe",
    "status": "contacted",
    "old_status": "new",
    "service_provider_id": 1,
    "service_provider_name": "Provider Name"
  },
  "message": "Lead 'John Doe' status changed from new to contacted"
}
```

## Features

✅ Admin receives all lead assignments
✅ Admin receives all status updates
✅ Provider receives assignments for their leads
✅ Provider receives status updates for their leads
✅ Real-time notification bell updates
✅ Dashboard auto-refreshes on events
✅ Works with database-configured Pusher settings

