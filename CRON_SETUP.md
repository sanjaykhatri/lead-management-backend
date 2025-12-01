# Laravel Queue Worker Setup for Shared Hosting

This guide explains how to set up Laravel queue workers on shared hosting using cron jobs.

## Method 1: Using Laravel Scheduler (Recommended)

Laravel's scheduler runs queue workers automatically via cron. This is the recommended approach.

### Step 1: For cPanel Shared Hosting

1. Log in to cPanel
2. Go to **Cron Jobs**
3. Add a new cron job with these settings:
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `/usr/bin/php /home/username/public_html/lead-management-backend/artisan schedule:run`

   **Important**: Replace `/home/username/public_html/lead-management-backend` with your actual project path.

### Step 2: Alternative - Using cron.php

If you prefer using the `cron.php` script:

1. Go to cPanel > Cron Jobs
2. Add new cron job:
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `/usr/bin/php /home/username/public_html/lead-management-backend/cron.php`

### Step 3: Verify PHP Path

To find your PHP path, create a test file `phpinfo.php`:

```php
<?php phpinfo();
```

Look for the "System" section to find the PHP path, or run:
```bash
which php
```

Common PHP paths on shared hosting:
- `/usr/bin/php`
- `/usr/local/bin/php`
- `/opt/cpanel/ea-php81/root/usr/bin/php` (for cPanel with EasyApache)

## Method 2: Direct Queue Worker Script (Alternative)

If you want to run the queue worker directly without the scheduler:

### Step 1: For cPanel Cron Jobs

1. Go to cPanel > Cron Jobs
2. Add new cron job:
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `/usr/bin/php /home/username/public_html/lead-management-backend/artisan queue:work --stop-when-empty --tries=3 --timeout=60 --max-time=60`

Or use the provided script:
   - **Command**: `/bin/bash /home/username/public_html/lead-management-backend/run-queue.sh`

## Configuration

The queue configuration is in `config/queue.php`. The default connection is `database`, which means jobs are stored in the `jobs` table.

### Queue Table Migration

Make sure the jobs table exists:

```bash
php artisan queue:table
php artisan migrate
```

## Testing

### Test the Scheduler

Run this command manually to test:

```bash
php artisan schedule:run
```

### Test Queue Worker

Run this command manually:

```bash
php artisan queue:work --once
```

### Check Queue Status

```bash
php artisan queue:monitor
```

## Monitoring

### View Queue Jobs

Check the `jobs` table in your database:

```sql
SELECT * FROM jobs;
```

### View Failed Jobs

```bash
php artisan queue:failed
```

### Retry Failed Jobs

```bash
php artisan queue:retry all
```

## Troubleshooting

### Cron Job Not Running

1. Check cron logs (usually in `/var/log/cron` or cPanel cron logs)
2. Verify the PHP path is correct
3. Check file permissions (should be executable)
4. Test the command manually first

### Queue Jobs Not Processing

1. Verify the cron job is running: Check cron logs
2. Check if jobs table exists: `php artisan migrate:status`
3. Check queue connection: Verify `QUEUE_CONNECTION` in `.env` is set to `database`
4. Check for errors: `php artisan queue:failed`

### Permission Issues

Make sure the cron user has permission to:
- Read project files
- Write to `storage/` directory
- Execute PHP

## Recommended Settings

For shared hosting, we recommend:

- **Queue Connection**: `database` (no Redis/Beanstalkd needed)
- **Cron Frequency**: Every minute (`* * * * *`)
- **Worker Timeout**: 60 seconds
- **Max Tries**: 3 attempts

These settings are already configured in `bootstrap/app.php`.

## Notes

- The scheduler runs queue workers with `--stop-when-empty` flag, meaning it processes all pending jobs and stops
- This prevents workers from running indefinitely and consuming resources
- Jobs are processed every minute when the cron runs
- For high-traffic sites, consider running a persistent worker instead

