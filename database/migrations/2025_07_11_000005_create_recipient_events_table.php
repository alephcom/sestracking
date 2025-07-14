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
        Schema::create('recipient_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('email_recipients')->cascadeOnDelete();
            $table->uuid('sns_message_id');
            $table->enum('type', [
                'send','delivery','bounce','complaint',
                'reject','rendering_failure','open','click'
            ]);
            $table->timestamp('event_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique('sns_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipient_events');
    }
};