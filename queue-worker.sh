#!/bin/bash
#
# Laravel Queue Worker Script for Shared Hosting
# 
# This script runs the Laravel queue worker as a background process.
# 
# Setup Instructions:
# 1. Make this file executable: chmod +x queue-worker.sh
# 2. Run it: ./queue-worker.sh
# 3. To stop: pkill -f "queue:work"
# 
# For persistent running, you can add this to cron:
# @reboot /path/to/your/project/queue-worker.sh
# 
# Or use nohup to run in background:
# nohup ./queue-worker.sh > /dev/null 2>&1 &
#

# Get the absolute path to the project root
cd "$(dirname "$0")"

# Path to PHP (adjust if needed)
PHP_BIN="/usr/bin/php"

# Check if PHP is available
if [ ! -f "$PHP_BIN" ]; then
    PHP_BIN="php"
fi

# Run queue worker
$PHP_BIN artisan queue:work \
    --tries=3 \
    --timeout=60 \
    --max-time=3600 \
    --sleep=3 \
    --rest=0

