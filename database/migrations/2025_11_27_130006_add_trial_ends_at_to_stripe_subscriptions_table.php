<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('current_period_end');
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->onDelete('set null')->after('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('stripe_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['trial_ends_at', 'subscription_plan_id']);
        });
    }
};

