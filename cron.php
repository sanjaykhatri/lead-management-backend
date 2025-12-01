<?php
/**
 * Laravel Queue Worker Cron Script for Shared Hosting
 * 
 * This script runs Laravel's scheduler which will execute queue workers.
 * 
 * Setup Instructions for cPanel:
 * 1. Go to cPanel > Cron Jobs
 * 2. Add new cron job:
 *    - Minute: *
 *    - Hour: *
 *    - Day: *
 *    - Month: *
 *    - Weekday: *
 *    - Command: /usr/bin/php /home/username/public_html/lead-management-backend/cron.php
 * 
 * Or use artisan directly:
 *    Command: /usr/bin/php /home/username/public_html/lead-management-backend/artisan schedule:run
 * 
 * Note: Replace /home/username/public_html/lead-management-backend with your actual project path
 * 
 * To find your PHP path, create a phpinfo.php file or check cPanel > Select PHP Version
 */

// Get the absolute path to the project root
$path = __DIR__;

// Change to project directory
chdir($path);

// Include the autoloader
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';

// Get the console kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run the scheduler
$exitCode = $kernel->call('schedule:run');

// Exit with the same code
exit($exitCode);

