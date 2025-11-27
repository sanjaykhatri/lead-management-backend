<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('provider_performance_metrics')) {
            Schema::create('provider_performance_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_provider_id')->constrained()->onDelete('cascade');
                $table->date('metric_date');
                $table->integer('total_leads')->default(0);
                $table->integer('new_leads')->default(0);
                $table->integer('contacted_leads')->default(0);
                $table->integer('closed_leads')->default(0);
                $table->decimal('conversion_rate', 5, 2)->default(0);
                $table->integer('avg_response_time_minutes')->nullable();
                $table->timestamps();
                
                $table->unique(['service_provider_id', 'metric_date'], 'provider_metrics_provider_date_unique');
            });
        } else {
            // Table exists, just fix the constraint if needed
            Schema::table('provider_performance_metrics', function (Blueprint $table) {
                // Try to drop old constraint if it exists
                try {
                    $table->dropUnique(['service_provider_id', 'metric_date']);
                } catch (\Exception $e) {
                    // Constraint might not exist or have different name, try dropping by name
                    try {
                        DB::statement('ALTER TABLE `provider_performance_metrics` DROP INDEX `provider_performance_metrics_service_provider_id_metric_date_unique`');
                    } catch (\Exception $e2) {
                        // Ignore if doesn't exist
                    }
                }
                
                // Add new constraint with shorter name
                $table->unique(['service_provider_id', 'metric_date'], 'provider_metrics_provider_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_performance_metrics');
    }
};

