<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id');
            $table->string('event_type'); // created, updated, canceled, upgraded, downgraded, renewed
            $table->string('status'); // active, canceled, past_due, incomplete, trialing
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('usd');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Store additional context
            $table->timestamp('event_date');
            $table->timestamps();
            
            $table->index(['service_provider_id', 'event_date']);
            $table->index('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
    }
};
