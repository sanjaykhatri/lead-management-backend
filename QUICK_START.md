# Quick Start Guide - New Features

## 🚀 Setup Steps

### 1. Run Database Migrations
```bash
cd lead-management-backend
php artisan migrate
```

### 2. Install SMS Package (Optional)
```bash
composer require laravel-notification-channels/twilio
```

### 3. Configure Environment Variables

Add to `.env`:

```env
# SMS Notifications (Twilio)
TWILIO_ENABLED=false
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+1234567890

# Real-time Notifications (Pusher)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

### 4. Frontend Environment Variables

Add to frontend `.env.local`:

```env
NEXT_PUBLIC_PUSHER_KEY=your_app_key
NEXT_PUBLIC_PUSHER_CLUSTER=your_cluster
```

### 5. Install Frontend Dependencies (if using Pusher)
```bash
cd lead-management-front
npm install pusher-js
```

## 📋 New Features Available

### Admin Features
1. **Analytics Dashboard** - `/admin/analytics`
   - View lead statistics
   - Provider performance metrics
   - Daily trends

2. **CSV Export** - Button in admin dashboard
   - Export filtered leads to CSV

3. **User Management** - `/admin/users`
   - Create/edit/delete admin users
   - Assign roles (super_admin, admin, manager)

4. **Lead Notes** - In lead detail page
   - Add notes to leads
   - View history

5. **Assignment Algorithms** - In location edit
   - Round-robin
   - Geographic
   - Load balance
   - Manual

6. **Real-time Notifications** - Bell icon in header
   - See new lead assignments
   - Status updates

### Provider Features
1. **Real-time Notifications** - Bell icon in header
   - Get notified when leads are assigned
   - Status update notifications

2. **SMS Notifications** (if configured)
   - Receive SMS when leads are assigned

## 🔧 Testing

1. **Test Assignment Algorithms**:
   - Go to Locations
   - Edit a location
   - Change assignment algorithm
   - Create a new lead
   - Verify assignment

2. **Test Analytics**:
   - Go to `/admin/analytics`
   - Select date range
   - View statistics

3. **Test CSV Export**:
   - Go to admin dashboard
   - Apply filters
   - Click "Export CSV"
   - Verify download

4. **Test Notifications**:
   - Open provider dashboard
   - Create a lead from admin
   - Check notification bell

## 📝 Notes

- SMS requires Twilio account and credentials
- Real-time requires Pusher account or Redis setup
- All features work without SMS/real-time (they're optional)
- Database migrations must be run first

