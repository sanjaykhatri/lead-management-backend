<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists
        if (Schema::hasTable('provider_performance_metrics')) {
            // Get all indexes on the table
            $indexes = DB::select("SHOW INDEX FROM `provider_performance_metrics` WHERE Key_name LIKE '%unique%' OR (Non_unique = 0 AND Column_name IN ('service_provider_id', 'metric_date'))");
            
            // Drop any existing unique constraints on these columns
            $droppedIndexes = [];
            foreach ($indexes as $index) {
                if (!in_array($index->Key_name, $droppedIndexes)) {
                    try {
                        DB::statement("ALTER TABLE `provider_performance_metrics` DROP INDEX `{$index->Key_name}`");
                        $droppedIndexes[] = $index->Key_name;
                    } catch (\Exception $e) {
                        // Ignore if already dropped or doesn't exist
                    }
                }
            }
            
            // Add the new constraint with shorter name
            try {
                Schema::table('provider_performance_metrics', function (Blueprint $table) {
                    $table->unique(['service_provider_id', 'metric_date'], 'provider_metrics_provider_date_unique');
                });
            } catch (\Exception $e) {
                // Constraint might already exist, that's okay
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('provider_performance_metrics')) {
            Schema::table('provider_performance_metrics', function (Blueprint $table) {
                $table->dropUnique('provider_metrics_provider_date_unique');
            });
        }
    }
};

