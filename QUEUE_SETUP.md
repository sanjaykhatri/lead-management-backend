# Queue Setup for Pusher Broadcasting

## Overview

Pusher notifications are now sent using Laravel's queue system instead of broadcasting immediately. This improves performance by not blocking the main request.

## Changes Made

- `LeadAssigned` event now implements `ShouldBroadcast` (queued) instead of `ShouldBroadcastNow` (immediate)
- `LeadStatusUpdated` event now implements `ShouldBroadcast` (queued) instead of `ShouldBroadcastNow` (immediate)

## Queue Configuration

The default queue connection is set to `database` in `config/queue.php`. This means jobs are stored in the database `jobs` table.

### 1. Create Jobs Table (if not exists)

Run the migration to create the jobs table:

```bash
php artisan queue:table
php artisan migrate
```

This creates a `jobs` table in your database to store queued jobs.

### 2. Start Queue Worker

You need to run a queue worker to process the queued broadcasting jobs:

```bash
php artisan queue:work
```

For production, use a process manager like Supervisor to keep the queue worker running:

#### Using Supervisor (Recommended for Production)

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

#### Using systemd (Alternative)

Create `/etc/systemd/system/laravel-worker.service`:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/project/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
```

### 3. Alternative Queue Drivers

If you prefer Redis or other queue drivers:

1. Update `.env`:
```env
QUEUE_CONNECTION=redis
```

2. Install Redis (if not already installed):
```bash
# Ubuntu/Debian
sudo apt-get install redis-server

# macOS
brew install redis
```

3. Update `config/queue.php` if needed

4. Start queue worker:
```bash
php artisan queue:work redis
```

## Testing

### 1. Test Queue Processing

1. Start the queue worker:
```bash
php artisan queue:work
```

2. Create a new lead from the public form

3. Check the queue worker output - you should see:
```
Processing: Illuminate\Broadcasting\BroadcastEvent
Processed:  Illuminate\Broadcasting\BroadcastEvent
```

4. Check Laravel logs for broadcasting messages

5. Verify notifications appear in the frontend

### 2. Monitor Queue

Check pending jobs:
```bash
php artisan queue:monitor
```

Or check the database:
```sql
SELECT * FROM jobs;
```

### 3. Failed Jobs

If jobs fail, they'll be stored in the `failed_jobs` table. View them:
```bash
php artisan queue:failed
```

Retry failed jobs:
```bash
php artisan queue:retry all
```

## Troubleshooting

### Issue: Notifications not being sent

**Check:**
1. Is the queue worker running? `ps aux | grep "queue:work"`
2. Are there jobs in the queue? `SELECT COUNT(*) FROM jobs;`
3. Check queue worker logs for errors
4. Check Laravel logs: `tail -f storage/logs/laravel.log`

### Issue: Jobs piling up

**Solution:**
- Increase the number of queue workers
- Use a faster queue driver (Redis instead of database)
- Optimize your broadcasting logic

### Issue: Jobs failing

**Check:**
1. View failed jobs: `php artisan queue:failed`
2. Check the error message
3. Common issues:
   - Pusher credentials incorrect
   - Network connectivity issues
   - Memory limits

## Development vs Production

### Development
- You can run `php artisan queue:work` manually in a terminal
- Or use `QUEUE_CONNECTION=sync` in `.env` to process immediately (not recommended for production)

### Production
- **Always use a process manager** (Supervisor or systemd)
- Set `QUEUE_CONNECTION=database` or `redis`
- Monitor queue worker health
- Set up alerts for failed jobs

## Performance Tips

1. **Use Redis** for better performance than database queues
2. **Multiple workers** - Run multiple queue workers for parallel processing
3. **Monitor queue size** - Set up alerts if queue grows too large
4. **Retry logic** - Configure `--tries` and `--timeout` appropriately

## Commands Reference

```bash
# Start queue worker
php artisan queue:work

# Start with specific connection
php artisan queue:work database

# Start with options
php artisan queue:work --tries=3 --timeout=60

# Monitor queue
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry {id}

# Clear failed jobs
php artisan queue:flush
```

