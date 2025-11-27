# Settings Management Guide

## Overview

Admins can now configure Pusher and Twilio settings directly from the admin panel instead of using environment variables. Settings are stored in the database and can be updated through the UI.

## Features

### Pusher Configuration
- Enable/disable Pusher
- Configure App ID, App Key, App Secret, and Cluster
- Test connection directly from admin panel

### Twilio Configuration
- Enable/disable Twilio SMS
- Configure Account SID, Auth Token, and Phone Number
- Test SMS sending with a test phone number

## Setup

### 1. Run Migration
```bash
cd lead-management-backend
php artisan migrate
```

This creates the `settings` table and populates default settings.

### 2. Access Settings Page
Navigate to `/admin/settings` in the admin panel.

### 3. Configure Pusher
1. Go to "Pusher Settings" tab
2. Enable Pusher by checking the checkbox
3. Enter your Pusher credentials:
   - App ID
   - App Key
   - App Secret
   - Cluster (default: us2)
4. Click "Test Connection" to verify
5. Click "Save Settings"

### 4. Configure Twilio
1. Go to "Twilio Settings" tab
2. Enable Twilio by checking the checkbox
3. Enter your Twilio credentials:
   - Account SID
   - Auth Token
   - Phone Number (from Twilio)
4. Enter a test phone number and click "Test SMS"
5. Click "Save Settings"

## How It Works

### Database Storage
- Settings are stored in the `settings` table
- Each setting has a key, value, type, group, and description
- Settings are grouped by `pusher` and `twilio`

### Application Usage
- The application checks database settings first
- Falls back to environment variables if database setting is empty
- Settings are cached for performance (can be cleared with `php artisan cache:clear`)

### API Endpoints
- `GET /api/admin/settings` - Get all settings
- `GET /api/admin/settings/group/{group}` - Get settings by group
- `PUT /api/admin/settings/group/{group}` - Update settings by group
- `POST /api/admin/settings/pusher/test` - Test Pusher connection
- `POST /api/admin/settings/twilio/test` - Test Twilio SMS

## Security

- Settings page is only accessible to authenticated admins
- Sensitive fields (secrets, tokens) are masked in the UI
- Settings are validated before saving

## Notes

- Pusher package must be installed: `composer require pusher/pusher-php-server`
- Twilio package must be installed: `composer require twilio/sdk`
- Test functions will show helpful error messages if packages are missing

