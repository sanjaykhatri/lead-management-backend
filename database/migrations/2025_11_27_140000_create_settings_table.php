<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, json
            $table->string('group')->default('general'); // pusher, twilio, general
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('settings')->insert([
            // Pusher Settings
            ['key' => 'pusher_enabled', 'value' => 'false', 'type' => 'boolean', 'group' => 'pusher', 'description' => 'Enable Pusher for real-time notifications', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pusher_app_id', 'value' => '', 'type' => 'string', 'group' => 'pusher', 'description' => 'Pusher App ID', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pusher_app_key', 'value' => '', 'type' => 'string', 'group' => 'pusher', 'description' => 'Pusher App Key', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pusher_app_secret', 'value' => '', 'type' => 'string', 'group' => 'pusher', 'description' => 'Pusher App Secret', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'pusher_app_cluster', 'value' => 'us2', 'type' => 'string', 'group' => 'pusher', 'description' => 'Pusher App Cluster', 'created_at' => now(), 'updated_at' => now()],
            
            // Twilio Settings
            ['key' => 'twilio_enabled', 'value' => 'false', 'type' => 'boolean', 'group' => 'twilio', 'description' => 'Enable Twilio for SMS notifications', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'twilio_account_sid', 'value' => '', 'type' => 'string', 'group' => 'twilio', 'description' => 'Twilio Account SID', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'twilio_auth_token', 'value' => '', 'type' => 'string', 'group' => 'twilio', 'description' => 'Twilio Auth Token', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'twilio_from', 'value' => '', 'type' => 'string', 'group' => 'twilio', 'description' => 'Twilio Phone Number', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

