<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            
            $table->unique(['service_provider_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_performance_metrics');
    }
};

