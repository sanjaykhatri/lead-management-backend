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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // note_created, status_updated, assigned, reassigned, etc.
            $table->string('performed_by_type'); // admin, provider
            $table->unsignedBigInteger('performed_by_id'); // user_id or service_provider_id
            $table->string('performed_by_name'); // For easy reference
            $table->text('description');
            $table->json('metadata')->nullable(); // Store additional context
            $table->timestamps();
            
            $table->index(['lead_id', 'created_at']);
            $table->index(['performed_by_type', 'performed_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
