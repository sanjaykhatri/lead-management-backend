#!/bin/bash
#
# Simple Queue Worker Script for Shared Hosting
# 
# This script runs the Laravel queue worker directly.
# 
# Usage:
# 1. Make executable: chmod +x run-queue.sh
# 2. Add to cron: * * * * * /path/to/run-queue.sh
# 
# Or run directly: ./run-queue.sh
#

# Get the directory where this script is located
cd "$(dirname "$0")"

# Find PHP (try common paths)
if [ -f "/usr/bin/php" ]; then
    PHP_BIN="/usr/bin/php"
elif [ -f "/usr/local/bin/php" ]; then
    PHP_BIN="/usr/local/bin/php"
else
    PHP_BIN="php"
fi

# Run queue worker (processes all jobs and stops)
$PHP_BIN artisan queue:work --stop-when-empty --tries=3 --timeout=60 --max-time=60

